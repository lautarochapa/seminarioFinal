<?php

namespace App\Services\Scraping;

use App\Exceptions\RecipeImportUrl\RecipeImportUrlException;
use App\Services\RecipeImportUrl\SupportedSourceRegistry;

class UrlSecurityValidator
{
    const BLOCKED_HOSTS = [
        'localhost',
        'metadata.google.internal',
        '169.254.169.254',
    ];

    const TRACKING_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fbclid',
        'gclid',
    ];

    public function normalizeHttpUrl(string $rawUrl, ?string $baseUrl = null, bool $requireSupportedSource = true): string
    {
        $url = trim($rawUrl);

        if ($baseUrl && strpos($url, '/') === 0) {
            $parts = parse_url($baseUrl);
            $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $url;
        }

        $url = filter_var($url, FILTER_VALIDATE_URL);
        if (!$url) {
            throw RecipeImportUrlException::invalidUrl();
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw RecipeImportUrlException::invalidUrl();
        }

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            throw RecipeImportUrlException::invalidUrl();
        }

        $this->assertSafeHost($host);

        if ($requireSupportedSource && !SupportedSourceRegistry::isSupported($host)) {
            throw RecipeImportUrlException::unsupportedSource();
        }

        return $this->withoutFragmentAndTracking($parts);
    }

    public function assertSafeFinalUrl(string $url, bool $requireSupportedSource = true): void
    {
        $this->normalizeHttpUrl($url, null, $requireSupportedSource);
    }

    public function assertSafeHost(string $host): void
    {
        $lower = strtolower(trim($host, '.'));

        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($lower === $blocked) {
                throw RecipeImportUrlException::ssrfBlocked();
            }
        }

        if ($this->isBlockedIpLiteral($lower)) {
            throw RecipeImportUrlException::ssrfBlocked();
        }

        $records = @dns_get_record($lower, DNS_A + DNS_AAAA);
        if ($records === false || empty($records)) {
            $ip = @gethostbyname($lower);
            if ($ip && $ip !== $lower && $this->isBlockedIpLiteral($ip)) {
                throw RecipeImportUrlException::ssrfBlocked();
            }
            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? ($record['ipv6'] ?? null);
            if ($ip && $this->isBlockedIpLiteral($ip)) {
                throw RecipeImportUrlException::ssrfBlocked();
            }
        }
    }

    private function isBlockedIpLiteral(string $value): bool
    {
        $ip = trim($value, '[]');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return preg_match('/\.local$/i', $value) === 1;
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    private function withoutFragmentAndTracking(array $parts): string
    {
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (self::TRACKING_PARAMS as $param) {
                unset($query[$param]);
            }
        }

        $path = $parts['path'] ?? '';
        $url = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

        if (!empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
