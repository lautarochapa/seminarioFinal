<?php

namespace App\Services\Scraping;

class ScrapingMetrics
{
    private $values = [
        'requests_total' => 0,
        'requests_successful' => 0,
        'requests_failed' => 0,
        'responses_429' => 0,
        'responses_4xx' => 0,
        'responses_5xx' => 0,
        'pages_processed' => 0,
        'items_found' => 0,
        'candidates_created' => 0,
        'candidates_updated' => 0,
        'items_skipped_duplicate' => 0,
        'parse_errors' => 0,
        'duration_ms' => 0,
        'errors' => 0,
    ];

    private $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public function increment(string $key, int $amount = 1): void
    {
        if (!array_key_exists($key, $this->values)) {
            $this->values[$key] = 0;
        }

        $this->values[$key] += $amount;
    }

    public function set(string $key, $value): void
    {
        $this->values[$key] = $value;
    }

    public function all(): array
    {
        $this->values['duration_ms'] = (int) round((microtime(true) - $this->startedAt) * 1000);

        return $this->values;
    }
}
