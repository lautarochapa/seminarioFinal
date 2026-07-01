<?php

namespace App\Foundation;

use Illuminate\Foundation\Application as BaseApplication;

/**
 * Custom Application that optionally redirects bootstrap cache to sys_get_temp_dir().
 *
 * On Windows, project directories hosted inside OneDrive reparse points return
 * false from PHP's is_writable() even when the directory is actually writable.
 * Laravel's ProviderRepository and PackageManifest call is_writable() on
 * bootstrap/cache before writing the service-provider manifest, which causes a
 * fatal exception every time the manifest needs to be recompiled.
 *
 * Set LARAVEL_CACHE_TO_TEMP=true in .env (or the system environment) to activate
 * the redirect. When the variable is absent or false the standard bootstrap/cache
 * path is used, keeping production behaviour unchanged.
 */
class Application extends BaseApplication
{
    protected function shouldUseTempCache(): bool
    {
        // Read from all sources: getenv() (after .env load), $_ENV, $_SERVER (phpunit.xml <server>)
        $flag = getenv('LARAVEL_CACHE_TO_TEMP');
        if ($flag === false || $flag === null) {
            $flag = isset($_ENV['LARAVEL_CACHE_TO_TEMP']) ? $_ENV['LARAVEL_CACHE_TO_TEMP'] : null;
        }
        if ($flag === false || $flag === null) {
            $flag = isset($_SERVER['LARAVEL_CACHE_TO_TEMP']) ? $_SERVER['LARAVEL_CACHE_TO_TEMP'] : null;
        }
        if ($flag !== null) {
            return $flag === 'true' || $flag === '1';
        }

        // Check APP_ENV from any source (may not be loaded yet at constructor time)
        $env = getenv('APP_ENV');
        if ($env === false || $env === null) {
            $env = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : null;
        }
        if ($env === null) {
            $env = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : null;
        }
        if (in_array($env, ['local', 'testing'], true)) {
            return true;
        }

        // Last resort: if bootstrap/cache is not writable (OneDrive/Windows reparse point)
        $bootstrapCache = $this->basePath('bootstrap' . DIRECTORY_SEPARATOR . 'cache');
        return !is_writable($bootstrapCache);
    }

    protected function laravelCacheDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel_bootstrap_cache';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function getCachedServicesPath(): string
    {
        if ($this->shouldUseTempCache()) {
            return $this->laravelCacheDir() . DIRECTORY_SEPARATOR . 'services.php';
        }
        return parent::getCachedServicesPath();
    }

    public function getCachedPackagesPath(): string
    {
        if ($this->shouldUseTempCache()) {
            return $this->laravelCacheDir() . DIRECTORY_SEPARATOR . 'packages.php';
        }
        return parent::getCachedPackagesPath();
    }
}
