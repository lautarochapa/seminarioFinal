<?php

namespace App\Services\RecipeImportUrl;

use App\AuditLog;
use App\Exceptions\RecipeImportUrl\RecipeImportUrlException;
use App\ImportedRecipeCandidate;
use App\Repositories\RecipeImportUrl\RecipeImportUrlRepository;
use App\Services\Scraping\UrlSecurityValidator;
use App\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecipeImportUrlService
{
    const MAX_REDIRECTS   = 3;
    const TIMEOUT_SECONDS = 15;
    const MAX_BODY_BYTES  = 2 * 1024 * 1024;

    /** Private/reserved IP prefixes to block for SSRF prevention. */
    const BLOCKED_HOSTS = [
        'localhost',
        'metadata.google.internal',
        '169.254.169.254',
    ];

    const BLOCKED_IP_PATTERNS = [
        '/^127\./',
        '/^0\./',
        '/^10\./',
        '/^192\.168\./',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^::1$/',
        '/^fd[0-9a-f]{2}:/i',
        '/\.local$/',
    ];

    private RecipeImportUrlRepository $repo;
    private RecipeUrlParser            $parser;
    private UrlSecurityValidator       $urlValidator;

    public function __construct(RecipeImportUrlRepository $repo, RecipeUrlParser $parser, UrlSecurityValidator $urlValidator)
    {
        $this->repo   = $repo;
        $this->parser = $parser;
        $this->urlValidator = $urlValidator;
    }

    public function import(User $user, string $rawUrl, string $ip, string $userAgent): ImportedRecipeCandidate
    {
        if (!$user->hasPermission('recipes.manage') && !$user->hasRole('super_admin') && !$user->hasRole('recipe_admin')) {
            throw RecipeImportUrlException::forbidden();
        }

        $url = $this->urlValidator->normalizeHttpUrl($rawUrl, null, true);
        $host = parse_url($url, PHP_URL_HOST);

        if ($this->repo->findByUrl($url)) {
            throw RecipeImportUrlException::duplicateUrl();
        }

        $candidate = $this->repo->createCandidate([
            'source_url'  => $url,
            'source_site' => SupportedSourceRegistry::labelForHost($host),
            'status'      => 'pending',
        ]);

        try {
            $html = $this->fetch($url);
            $data = $this->parser->parse($html);

            if (!$data || empty($data['title'])) {
                $this->repo->updateCandidate($candidate, ['status' => 'failed']);
                throw RecipeImportUrlException::parseFailed();
            }

            $this->repo->updateCandidate($candidate, [
                'raw_title'            => $data['title'],
                'raw_description'      => $data['description'],
                'raw_ingredients_json' => $data['ingredients'] ?: null,
                'raw_steps_json'       => $data['steps'] ?: null,
                'raw_image_url'        => $data['image_url'],
                'parsed_recipe_json'   => [
                    'name'              => $data['title'],
                    'description'       => $data['description'],
                    'servings'          => $data['servings'],
                    'prep_time_minutes' => $data['prep_minutes'],
                    'cook_time_minutes' => $data['cook_minutes'],
                ],
                'status' => 'parsed',
            ]);
        } catch (RecipeImportUrlException $e) {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'recipe_import_failed',
                'entity_name'=> 'imported_recipe_candidates',
                'entity_id'  => $candidate->id,
                'old_values' => null,
                'new_values' => ['url' => $url, 'reason' => $e->getMessage()],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('recipe_import_fetch_error', ['url' => $url, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            $this->repo->updateCandidate($candidate, ['status' => 'failed']);
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'recipe_import_failed',
                'entity_name'=> 'imported_recipe_candidates',
                'entity_id'  => $candidate->id,
                'old_values' => null,
                'new_values' => ['url' => $url, 'reason' => mb_substr($e->getMessage(), 0, 200)],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
            throw RecipeImportUrlException::fetchFailed();
        }

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_import_created',
            'entity_name'=> 'imported_recipe_candidates',
            'entity_id'  => $candidate->id,
            'old_values' => null,
            'new_values' => ['url' => $url, 'status' => 'parsed', 'title' => $candidate->raw_title],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $candidate->fresh();
    }

    private function fetch(string $url): string
    {
        $currentUrl = $url;
        $redirects = 0;

        while (true) {
            $response = Http::withHeaders([
                'User-Agent' => config('scraping.user_agent', 'ComidaControlBot/1.0'),
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->timeout((int) config('scraping.timeout_seconds', self::TIMEOUT_SECONDS))
                ->withOptions([
                    'allow_redirects' => false,
                    'connect_timeout' => (int) config('scraping.connect_timeout_seconds', 5),
                ])
                ->get($currentUrl);

            if ($response->status() >= 300 && $response->status() < 400 && $response->header('Location')) {
                $redirects++;
                if ($redirects > self::MAX_REDIRECTS) {
                    throw RecipeImportUrlException::fetchFailed('Too many redirects');
                }
                $currentUrl = $this->urlValidator->normalizeHttpUrl($response->header('Location'), $currentUrl, true);
                continue;
            }

            break;
        }

        if (!$response->successful()) {
            throw RecipeImportUrlException::fetchFailed('HTTP ' . $response->status());
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            $body = mb_substr($body, 0, self::MAX_BODY_BYTES);
        }

        return $body;
    }
}
