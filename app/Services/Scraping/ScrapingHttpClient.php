<?php

namespace App\Services\Scraping;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ScrapingHttpClient
{
    private $rateLimiter;
    private $circuitBreaker;
    private $urlValidator;

    public function __construct(ScrapingRateLimiter $rateLimiter, ScrapingCircuitBreaker $circuitBreaker, UrlSecurityValidator $urlValidator)
    {
        $this->rateLimiter = $rateLimiter;
        $this->circuitBreaker = $circuitBreaker;
        $this->urlValidator = $urlValidator;
    }

    public function getJson(string $sourceCode, string $url, array $query, ScrapingMetrics $metrics, array $context = []): Response
    {
        return $this->get($sourceCode, $url, $query, 'application/json', $metrics, $context);
    }

    public function getHtml(string $sourceCode, string $url, ScrapingMetrics $metrics, array $context = []): Response
    {
        return $this->get($sourceCode, $url, [], 'text/html,application/xhtml+xml', $metrics, $context);
    }

    private function get(string $sourceCode, string $url, array $query, string $accept, ScrapingMetrics $metrics, array $context): Response
    {
        if ($this->circuitBreaker->isOpen($sourceCode)) {
            throw new ScrapingHttpException(
                'Circuit breaker abierto para la fuente.',
                null,
                $this->circuitBreaker->secondsUntilClose($sourceCode),
                false
            );
        }

        $maxRetries = max(0, (int) config('scraping.max_retries', 2));
        $backoffs = config('scraping.retry_backoff_seconds', [10, 30]);
        $attempt = 0;
        $currentUrl = $url;
        $redirects = 0;
        $maxRedirects = 3;
        $queryForRequest = $query;

        while (true) {
            $attempt++;
            $metrics->increment('requests_total');
            $startedAt = microtime(true);

            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('scraping.user_agent', 'ComidaControlBot/1.0'),
                    'Accept' => $accept,
                ])
                    ->timeout((int) config('scraping.timeout_seconds', 20))
                    ->withOptions([
                        'allow_redirects' => false,
                        'connect_timeout' => (int) config('scraping.connect_timeout_seconds', 5),
                    ])
                    ->get($currentUrl, $queryForRequest);
            } catch (\Throwable $e) {
                $metrics->increment('requests_failed');
                $this->circuitBreaker->recordFailure($sourceCode);
                if ($attempt <= $maxRetries) {
                    $this->sleepBackoff($attempt, $backoffs);
                    continue;
                }
                throw new ScrapingHttpException('Error de red: ' . mb_substr($e->getMessage(), 0, 200), null, null, true);
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400 && $response->header('Location')) {
                $redirects++;
                if ($redirects > $maxRedirects) {
                    throw new ScrapingHttpException('Demasiados redirects.', $status, null, false);
                }

                $currentUrl = $this->nextUrl($response->header('Location'), $currentUrl, $context);
                $queryForRequest = [];
                continue;
            }

            if ($response->successful()) {
                $metrics->increment('requests_successful');
                $this->circuitBreaker->recordSuccess($sourceCode);
                return $response;
            }

            $metrics->increment('requests_failed');
            if ($status === 429) {
                $metrics->increment('responses_429');
            } elseif ($status >= 500) {
                $metrics->increment('responses_5xx');
            } elseif ($status >= 400) {
                $metrics->increment('responses_4xx');
            }

            $this->circuitBreaker->recordFailure($sourceCode);

            if ($status === 401 || $status === 403) {
                $this->circuitBreaker->open($sourceCode);
                throw new ScrapingHttpException('Fuente bloqueada o no autorizada. HTTP ' . $status, $status, null, false);
            }

            if ($status === 429) {
                $retryAfter = $this->retryAfterSeconds($response);
                throw new ScrapingHttpException('Rate limit de fuente. HTTP 429', 429, $retryAfter, true);
            }

            if (!$this->shouldRetryStatus($status) || $attempt > $maxRetries) {
                throw new ScrapingHttpException('HTTP ' . $status, $status, null, false);
            }

            $this->sleepBackoff($attempt, $backoffs);
        }
    }

    private function shouldRetryStatus(int $status): bool
    {
        return in_array($status, [500, 502, 503, 504], true);
    }

    private function sleepBackoff(int $attempt, array $backoffs): void
    {
        $index = max(0, $attempt - 1);
        $seconds = isset($backoffs[$index]) ? (int) $backoffs[$index] : (int) end($backoffs);
        $this->rateLimiter->pauseSeconds(max(0, $seconds));
    }

    private function retryAfterSeconds(Response $response): int
    {
        $value = $response->header('Retry-After');
        if ($value !== null && ctype_digit((string) $value)) {
            return max(1, (int) $value);
        }

        return max(1, (int) config('scraping.default_retry_after_seconds', 60));
    }

    private function nextUrl(string $location, string $currentUrl, array $context): string
    {
        $validateUrl = $context['validate_url'] ?? false;
        $requireSupported = $context['require_supported_source'] ?? false;

        if (!$validateUrl) {
            if (strpos($location, '/') === 0) {
                $parts = parse_url($currentUrl);
                return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $location;
            }
            return $location;
        }

        return $this->urlValidator->normalizeHttpUrl($location, $currentUrl, $requireSupported);
    }
}
