<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AndroidVersionController extends Controller
{
    public function __invoke(Request $request)
    {
        $release = config('mobile.android', []);
        $version = $release['version'] ?? null;
        $build = $release['build'] ?? null;
        $page = $release['download_page_url'] ?? null;
        $binary = $release['download_url'] ?? null;
        $valid = is_string($version)
            && preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $version)
            && is_int($build) && $build > 0
            && $this->isHttpsUrl($page) && $this->isHttpsUrl($binary);

        // Do not advertise a version before its downloadable binary is configured.
        $payload = $valid ? ['data' => [
            'platform' => 'android',
            'version' => $version,
            'build' => $build,
            'download_page_url' => $page,
        ]] : ['error' => [
            'code' => 'ANDROID_RELEASE_UNAVAILABLE',
            'message' => 'La informacion de la version no esta disponible.',
        ]];
        $payload['trace_id'] = $request->attributes->get('trace_id');

        return response()->json($payload, $valid ? 200 : 503)
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    private function isHttpsUrl($value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
            && parse_url($value, PHP_URL_SCHEME) === 'https'
            && parse_url($value, PHP_URL_USER) === null
            && parse_url($value, PHP_URL_PASS) === null;
    }
}
