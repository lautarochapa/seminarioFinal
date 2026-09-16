<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ScrapingExecutionGuard
{
    private $lock;
    private $fallbackKey;
    private $owner;

    /**
     * $ttlSeconds permite a un llamador (ej. recipe scraping) pedir un TTL
     * mas corto que el default compartido (scraping.lock_ttl_seconds),
     * sin afectar a otros sources que no lo pasan (product scraping sigue
     * usando el default de siempre). El TTL es, hoy, el unico mecanismo de
     * "antiguedad": el store de cache expira la entrada solo por si mismo,
     * asi que un lock nunca queda "stale" por mas tiempo que su propio TTL
     * — no hace falta una deteccion de antiguedad separada, alcanza con que
     * el TTL sea razonable para la duracion real de la corrida.
     */
    public function acquire(string $sourceCode, ?int $ttlSeconds = null): bool
    {
        $key = 'scraping:source:' . $sourceCode;
        $ttl = $ttlSeconds !== null
            ? max(60, $ttlSeconds)
            : max(60, (int) config('scraping.lock_ttl_seconds', 3600));
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
