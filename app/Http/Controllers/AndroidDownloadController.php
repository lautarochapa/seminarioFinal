<?php

namespace App\Http\Controllers;

class AndroidDownloadController extends Controller
{
    public function __invoke()
    {
        $url = config('mobile.android.download_url');

        abort_unless(is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https', 503);

        // Keep the public link stable; only the versioned binary changes.
        return redirect()->away($url, 302, [
            'Cache-Control' => 'no-store, max-age=0',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
