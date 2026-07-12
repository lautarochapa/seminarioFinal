<?php

namespace App\Scraping\Adapters;

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

    public function __construct(ScrapingHttpClient $http, ScrapingRateLimiter $rateLimiter, UrlSecurityValidator $urlValidator)
    {
        $this->http = $http;
        $this->rateLimiter = $rateLimiter;
        $this->urlValidator = $urlValidator;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function scrape(ScrapingSource $source, ScrapingJob $job): RecipeScrapingResult
    {
        $result   = new RecipeScrapingResult();
        $metrics  = new ScrapingMetrics();
        $requestedMaxPages = (int) (($job->parameters_json['max_pages'] ?? 1));
        $maxPages = min($requestedMaxPages, (int) config('scraping.limits.recipe_max_pages', 10));
        $maxRecipes = (int) config('scraping.limits.recipe_max_items', 200);
        $baseUrl  = rtrim($source->base_url ?? 'https://cookpad.com/ar', '/');
        $seenUrls = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            if (in_array($job->fresh()->status, ['cancelled', 'cancel_requested'], true)) {
                $result->finalReason = 'cancelled';
                break;
            }

            $listingUrl = $baseUrl . '/busca/recetas?page=' . $page;
            $html       = $this->fetchHtml($source->code, $listingUrl, $metrics, true);

            if ($html === null) {
                $result->errorMessage = 'Failed to fetch listing page ' . $page;
                $result->finalReason = 'listing_fetch_failed';
                break;
            }

            $recipeLinks = $this->extractRecipeLinks($html, $baseUrl);
            $result->pagesScraped++;
            $metrics->increment('pages_processed');

            foreach ($recipeLinks as $recipeUrl) {
                if (in_array($job->fresh()->status, ['cancelled', 'cancel_requested'], true)) {
                    $result->finalReason = 'cancelled';
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

                $recipeHtml = $this->fetchHtml($source->code, $recipeUrl, $metrics, true);
                if ($recipeHtml === null) {
                    continue;
                }

                $dto = $this->parseRecipePage($recipeHtml, $recipeUrl);
                if ($dto !== null) {
                    $result->recipes[]  = $dto;
                    $result->totalFound++;
                    $metrics->increment('items_found');
                } else {
                    $metrics->increment('parse_errors');
                }

                if ($result->totalFound >= $maxRecipes) {
                    $result->finalReason = 'max_items_reached';
                    break 2;
                }

                $this->rateLimiter->pauseForRecipe();
            }

            if (count($recipeLinks) < self::RECIPES_PER_PAGE) {
                break;
            }

            if ($page < $maxPages) {
                $this->rateLimiter->pauseForRecipe();
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
        preg_match_all('/href=["\'](' . preg_quote($baseUrl, '/') . '\/recetas\/\d+[^"\']*)["\']/', $html, $matches);
        if (isset($matches[1])) {
            $links = array_unique($matches[1]);
        }

        // Also match relative links
        preg_match_all('/href=["\'](\/recetas\/\d+[^"\']*)["\']/', $html, $relMatches);
        if (isset($relMatches[1])) {
            foreach ($relMatches[1] as $rel) {
                $links[] = $baseUrl . $rel;
            }
        }

        return array_unique(array_values($links));
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

    private function fetchHtml(string $sourceCode, string $url, ScrapingMetrics $metrics, bool $validateUrl): ?string
    {
        try {
            $response = $this->http->getHtml($sourceCode, $url, $metrics, [
                'validate_url' => $validateUrl,
                'require_supported_source' => true,
            ]);

            return $response->body();
        } catch (ScrapingHttpException $e) {
            Log::warning('cookpad_scraper_fetch_error', [
                'url' => mb_substr($url, 0, 300),
                'status_code' => $e->statusCode(),
                'retry_after_seconds' => $e->retryAfterSeconds(),
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('cookpad_scraper_fetch_error', ['url' => $url, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            return null;
        }
    }
}
