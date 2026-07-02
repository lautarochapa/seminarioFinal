<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ScrapingExecutionGuard
{
    private $lock;
    private $fallbackKey;
    private $owner;

    public function acquire(string $sourceCode): bool
    {
        $key = 'scraping:source:' . $sourceCode;
        $ttl = max(60, (int) config('scraping.lock_ttl_seconds', 3600));
        $this->owner = (string) Str::uuid();

        try {
            $store = Cache::store();
            if (method_exists($store, 'lock')) {
                $this->lock = $store->lock($key, $ttl, $this->owner);
                return (bool) $this->lock->get();
            }
        } catch (\Throwable $e) {
            $this->lock = null;
        }

        $this->fallbackKey = $key . ':owner';
        return Cache::add($this->fallbackKey, $this->owner, $ttl);
    }

    public function release(): void
    {
        if ($this->lock) {
            try {
                $this->lock->release();
            } catch (\Throwable $e) {
                // Some cache stores cannot release owner-aware locks. The TTL remains as fallback safety.
            }
            $this->lock = null;
            return;
        }

        if ($this->fallbackKey && Cache::get($this->fallbackKey) === $this->owner) {
            Cache::forget($this->fallbackKey);
        }
    }
}
