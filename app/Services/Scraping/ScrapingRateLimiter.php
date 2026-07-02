<?php

namespace App\Services\Scraping;

class ScrapingRateLimiter
{
    public function pause(int $milliseconds): void
    {
        if ($milliseconds <= 0 || app()->environment('testing')) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    public function pauseForSupermarket(): void
    {
        $this->pause((int) config('scraping.request_delay_ms', 1500));
    }

    public function pauseForRecipe(): void
    {
        $this->pause((int) config('scraping.recipe_request_delay_ms', 2500));
    }

    public function pauseSeconds(int $seconds): void
    {
        $this->pause($seconds * 1000);
    }
}
