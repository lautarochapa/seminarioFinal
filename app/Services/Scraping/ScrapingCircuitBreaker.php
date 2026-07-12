<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Cache;

class ScrapingCircuitBreaker
{
    public function isOpen(string $sourceCode): bool
    {
        $openUntil = (int) Cache::get($this->openUntilKey($sourceCode), 0);

        if ($openUntil <= 0) {
            return false;
        }

        if ($openUntil <= time()) {
            $this->close($sourceCode);
            return false;
        }

        return true;
    }

    public function secondsUntilClose(string $sourceCode): int
    {
        $openUntil = (int) Cache::get($this->openUntilKey($sourceCode), 0);
        return max(0, $openUntil - time());
    }

    public function recordSuccess(string $sourceCode): void
    {
        Cache::forget($this->errorsKey($sourceCode));
    }

    public function recordFailure(string $sourceCode): int
    {
        $key = $this->errorsKey($sourceCode);
        $errors = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $errors, $this->cooldownSeconds());

        if ($errors >= $this->maxErrors()) {
            $this->open($sourceCode);
        }

        return $errors;
    }

    public function open(string $sourceCode, ?int $seconds = null): void
    {
        $seconds = $seconds ?: $this->cooldownSeconds();
        Cache::put($this->openUntilKey($sourceCode), time() + $seconds, $seconds);
    }

    public function close(string $sourceCode): void
    {
        Cache::forget($this->openUntilKey($sourceCode));
        Cache::forget($this->errorsKey($sourceCode));
    }

    private function maxErrors(): int
    {
        return max(1, (int) config('scraping.circuit_breaker.max_errors', 5));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('scraping.circuit_breaker.cooldown_seconds', 900));
    }

    private function errorsKey(string $sourceCode): string
    {
        return 'scraping:circuit:' . $sourceCode . ':errors';
    }

    private function openUntilKey(string $sourceCode): string
    {
        return 'scraping:circuit:' . $sourceCode . ':open_until';
    }
}
