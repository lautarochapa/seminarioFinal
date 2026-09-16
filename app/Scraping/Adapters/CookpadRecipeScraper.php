<?php

namespace App\Scraping\Adapters;

use App\Repositories\Scraping\ScrapingRepository;
use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\DTOs\RecipeScrapingResult;
use App\Scraping\DTOs\ScrapedRecipeDTO;
use Illuminate\Support\Facades\Log;
use App\Services\Scraping\ScrapingHttpClient;
use App\Services\Scraping\ScrapingHttpException;
use App\Services\Scraping\ScrapingMetrics;
use App\Services\Scraping\ScrapingRateLimiter;
use App\Services\Scraping\UrlSecurityValidator;

class CookpadRecipeScraper
{
    const MAX_REDIRECTS   = 3;
    const RECIPES_PER_PAGE = 20;
    const MAX_BODY_BYTES   = 2 * 1024 * 1024;

    private $http;
    private $rateLimiter;
    private $urlValidator;
    private $repo;
    private $blocked = false;
    private $deadline = null;

    public function __construct(ScrapingHttpClient $http, ScrapingRateLimiter $rateLimiter, UrlSecurityValidator $urlValidator, ScrapingRepository $repo)
    {
        $this->http = $http;
        $this->rateLimiter = $rateLimiter;
        $this->urlValidator = $urlValidator;
        $this->repo = $repo;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function scrape(ScrapingSource $source, ScrapingJob $job): RecipeScrapingResult
    {
        $result   = new RecipeScrapingResult();
        $metrics  = new ScrapingMetrics();
        $params   = $job->parameters_json ?? [];
        $requestedMaxPages = (int) ($params['max_pages'] ?? 1);
        $maxPages = min($requestedMaxPages, (int) config('scraping.limits.recipe_max_pages', 10));
        $maxRecipes = (int) config('scraping.limits.recipe_max_items', 200);
        if (isset($params['max_recipes']) && (int) $params['max_recipes'] > 0) {
            $maxRecipes = min($maxRecipes, (int) $params['max_recipes']);
        }
        $delayOverrideMs = isset($params['delay_ms']) && (int) $params['delay_ms'] >= 0
            ? (int) $params['delay_ms']
            : null;
        $baseUrl  = rtrim($source->base_url ?? 'https://cookpad.com/ar', '/');
        $searchTerm = $this->resolveSearchTerm($params);
        $seenUrls = [];
        $this->blocked = false;
        $budgetSeconds = (int) config('scraping.recipe_time_budget_seconds', 180);
        $this->deadline = $budgetSeconds > 0 ? (microtime(true) + $budgetSeconds) : null;

        for ($page = 1; $page <= $maxPages; $page++) {
            if (in_array($job->fresh()->status, ['cancelled', 'cancel_requested'], true)) {
                $result->finalReason = 'cancelled';
                break;
            }

            if ($this->deadlineExceeded()) {
                $result->finalReason = 'time_budget_exceeded';
                break;
            }

            $listingUrl = $baseUrl . '/buscar/' . rawurlencode($searchTerm) . '?page=' . $page;
            $html       = $this->fetchHtml($job, $source->code, $listingUrl, $metrics, true, 'listing');

            if ($this->blocked) {
                $result->errorMessage = 'Fuente bloqueada o con verificacion anti-bot. Corrida detenida.';
                $result->finalReason = 'blocked';
                break;
            }

            if ($html === null) {
                $result->errorMessage = 'Failed to fetch listing page ' . $page;
                $result->finalReason = 'listing_fetch_failed';
                break;
            }

            $recipeLinks = $this->extractRecipeLinks($html, $baseUrl);
            $result->pagesScraped++;
            $metrics->increment('pages_processed');
            $this->repo->addLog($job, 'info', 'page_parsed', [
                'page'          => $page,
                'recipes_found' => count($recipeLinks),
            ]);

            foreach ($recipeLinks as $recipeUrl) {
                if (in_array($job->fresh()->status, ['cancelled', 'cancel_requested'], true)) {
                    $result->finalReason = 'cancelled';
                    break 2;
                }

                if ($this->deadlineExceeded()) {
                    $result->finalReason = 'time_budget_exceeded';
                    break 2;
                }

                try {
                    $recipeUrl = $this->urlValidator->normalizeHttpUrl($recipeUrl, $baseUrl, true);
                } catch (\Throwable $e) {
                    $metrics->increment('items_skipped_duplicate');
                    continue;
                }

                if (isset($seenUrls[$recipeUrl])) {
                    $metrics->increment('items_skipped_duplicate');
                    continue;
                }
                $seenUrls[$recipeUrl] = true;

                $this->repo->addLog($job, 'info', 'recipe_detail_started', ['url' => mb_substr($recipeUrl, 0, 300)]);
                $recipeHtml = $this->fetchHtml($job, $source->code, $recipeUrl, $metrics, true, 'detail');

                if ($this->blocked) {
                    $result->errorMessage = 'Fuente bloqueada o con verificacion anti-bot. Corrida detenida.';
                    $result->finalReason = 'blocked';
                    break 2;
                }

                if ($recipeHtml === null) {
                    $this->repo->addLog($job, 'warning', 'recipe_detail_finished', ['url' => mb_substr($recipeUrl, 0, 300), 'parsed' => false]);
                    continue;
                }

                $dto = $this->parseRecipePage($recipeHtml, $recipeUrl);
                if ($dto !== null) {
                    $result->recipes[]  = $dto;
                    $result->totalFound++;
                    $metrics->increment('items_found');
                    $this->repo->addLog($job, 'info', 'recipe_detail_finished', ['url' => mb_substr($recipeUrl, 0, 300), 'parsed' => true]);
                } else {
                    $metrics->increment('parse_errors');
                    $this->repo->addLog($job, 'warning', 'recipe_detail_finished', ['url' => mb_substr($recipeUrl, 0, 300), 'parsed' => false]);
                }

                if ($result->totalFound >= $maxRecipes) {
                    $result->finalReason = 'max_items_reached';
                    break 2;
                }

                $this->rateLimiter->pauseForRecipe($delayOverrideMs);
            }

            if (count($recipeLinks) < self::RECIPES_PER_PAGE) {
                break;
            }

            if ($page < $maxPages) {
                $this->rateLimiter->pauseForRecipe($delayOverrideMs);
            }
        }

        if ($result->finalReason === 'completed' && $maxPages > 0 && $result->pagesScraped >= $maxPages) {
            $result->finalReason = 'max_pages_reached';
        }
        $result->successful = $result->errorMessage === null;
        $result->metrics = $metrics->all();
        return $result;
    }

    public function parseRecipePage(string $html, string $sourceUrl): ?ScrapedRecipeDTO
    {
        $html = mb_substr($html, 0, self::MAX_BODY_BYTES);

        // Try JSON-LD first
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $block) {
                $data = json_decode(trim($block), true);
                if ($data && isset($data['@type']) && $data['@type'] === 'Recipe') {
                    return $this->fromJsonLd($data, $sourceUrl);
                }
            }
        }

        // Fallback: extract title from <h1> or <title>
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $titleNodes = $xpath->query('//h1[1]');
        $title      = ($titleNodes && $titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : null;

        if (!$title) {
            $titleNodes = $xpath->query('//title[1]');
            $title = ($titleNodes && $titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : null;
        }

        if (!$title) {
            return null;
        }

        $dto              = new ScrapedRecipeDTO();
        $dto->sourceUrl   = $sourceUrl;
        $dto->title       = mb_substr($title, 0, 200);
        $dto->externalId  = $this->extractIdFromUrl($sourceUrl);
        return $dto;
    }

    public function extractRecipeLinks(string $html, string $baseUrl): array
    {
        $links = [];

        if (preg_match_all('#href=["\']([^"\']*/recetas/\d+[^"\']*)["\']#', $html, $matches)) {
            $host = $this->schemeHost($baseUrl);
            foreach ($matches[1] as $href) {
                $href = html_entity_decode($href, ENT_QUOTES);
                if (strpos($href, 'http') === 0) {
                    $links[] = $href;
                } elseif (strpos($href, '/') === 0 && $host !== '') {
                    $links[] = $host . $href;
                }
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * El proceso corre en linea (QUEUE_CONNECTION=sync) dentro del request
     * HTTP/CLI que lo dispara: no hay un worker de cola que lo mate por
     * timeout ni que reintente si el servidor lo corta primero. Este limite
     * de tiempo evita que la corrida siga acumulando requests mas alla de lo
     * que el proceso puede terminar de forma segura, para que el job siempre
     * cierre con un estado final propio en vez de quedar "running" hasta que
     * algo externo mate el proceso a mitad de camino.
     */
    private function deadlineExceeded(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }

    private function schemeHost(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }

    private function resolveSearchTerm(array $params): string
    {
        $term = isset($params['search_term']) ? trim((string) $params['search_term']) : '';
        if ($term === '') {
            $term = trim((string) config('scraping.recipe_search_term', 'comida'));
        }

        return $term !== '' ? $term : 'comida';
    }

    private function fromJsonLd(array $data, string $sourceUrl): ScrapedRecipeDTO
    {
        $dto            = new ScrapedRecipeDTO();
        $dto->sourceUrl = $sourceUrl;
        $dto->title     = mb_substr(strip_tags((string) ($data['name'] ?? '')), 0, 200);
        $dto->description = isset($data['description']) ? mb_substr(strip_tags((string) $data['description']), 0, 500) : null;
        $dto->externalId  = $this->extractIdFromUrl($sourceUrl);

        if (isset($data['recipeYield'])) {
            $raw = is_array($data['recipeYield']) ? ($data['recipeYield'][0] ?? '') : $data['recipeYield'];
            preg_match('/\d+/', (string) $raw, $m);
            $dto->servings = isset($m[0]) ? (int) $m[0] : null;
        }

        $dto->prepMinutes = $this->parseDuration($data['prepTime'] ?? null);
        $dto->cookMinutes = $this->parseDuration($data['cookTime'] ?? null);

        if (isset($data['recipeIngredient']) && is_array($data['recipeIngredient'])) {
            $dto->ingredients = array_values(array_filter($data['recipeIngredient'], 'is_string'));
        }

        if (isset($data['recipeInstructions']) && is_array($data['recipeInstructions'])) {
            $num = 1;
            foreach ($data['recipeInstructions'] as $step) {
                $text = is_string($step) ? $step : ($step['text'] ?? '');
                $text = strip_tags($text);
                if ($text) {
                    $dto->steps[] = ['step_number' => $num++, 'description' => $text];
                }
            }
        }

        if (isset($data['image'])) {
            $img = is_array($data['image']) ? ($data['image']['url'] ?? $data['image'][0] ?? null) : $data['image'];
            $dto->imageUrl = $img ? filter_var($img, FILTER_SANITIZE_URL) : null;
        }

        return $dto;
    }

    private function parseDuration(?string $iso): ?int
    {
        if (!$iso) {
            return null;
        }
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m);
        $hours   = isset($m[1]) ? (int) $m[1] : 0;
        $minutes = isset($m[2]) ? (int) $m[2] : 0;
        $total   = $hours * 60 + $minutes;
        return $total > 0 ? $total : null;
    }

    private function extractIdFromUrl(string $url): ?string
    {
        if (preg_match('/\/recetas\/(\d+)/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Instrumenta cada request HTTP con eventos en ScrapingJobLog
     * (page_request_started/finished) para poder distinguir, ante un job
     * que no avanza, si esta esperando una respuesta de Cookpad, si la
     * respuesta llego bloqueada/con error, o si el problema esta despues
     * (parseo). No loguea el HTML completo, solo status/duracion/bytes.
     */
    private function fetchHtml(ScrapingJob $job, string $sourceCode, string $url, ScrapingMetrics $metrics, bool $validateUrl, string $kind): ?string
    {
        $this->repo->addLog($job, 'info', 'page_request_started', [
            'kind' => $kind,
            'url'  => mb_substr($url, 0, 300),
        ]);
        $startedAt = microtime(true);

        try {
            $response = $this->http->getHtml($sourceCode, $url, $metrics, [
                'validate_url' => $validateUrl,
                'require_supported_source' => true,
            ]);

            $body = $response->body();
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->repo->addLog($job, 'info', 'page_request_finished', [
                'kind'        => $kind,
                'status'      => $response->status(),
                'duration_ms' => $durationMs,
                'bytes'       => strlen($body),
            ]);

            if ($this->looksBlocked($body)) {
                $this->blocked = true;
                Log::warning('cookpad_scraper_blocked', ['url' => mb_substr($url, 0, 300)]);
                return null;
            }

            return $body;
        } catch (ScrapingHttpException $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if (in_array($e->statusCode(), [401, 403, 429], true)) {
                $this->blocked = true;
            }
            $this->repo->addLog($job, 'warning', 'page_request_finished', [
                'kind'        => $kind,
                'status'      => $e->statusCode(),
                'duration_ms' => $durationMs,
                'error'       => mb_substr($e->getMessage(), 0, 200),
            ]);
            Log::warning('cookpad_scraper_fetch_error', [
                'url' => mb_substr($url, 0, 300),
                'status_code' => $e->statusCode(),
                'retry_after_seconds' => $e->retryAfterSeconds(),
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return null;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->repo->addLog($job, 'warning', 'page_request_finished', [
                'kind'        => $kind,
                'status'      => null,
                'duration_ms' => $durationMs,
                'error'       => mb_substr($e->getMessage(), 0, 200),
            ]);
            Log::warning('cookpad_scraper_fetch_error', ['url' => $url, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            return null;
        }
    }

    private function looksBlocked(string $body): bool
    {
        $head = mb_strtolower(mb_substr($body, 0, 4096), 'UTF-8');

        foreach (['captcha', 'unusual traffic', 'access denied', 'verify you are human',
                  'cf-browser-verification', 'attention required', 'are you a robot'] as $marker) {
            if (mb_strpos($head, $marker) !== false) {
                return true;
            }
        }

        return false;
    }
}
