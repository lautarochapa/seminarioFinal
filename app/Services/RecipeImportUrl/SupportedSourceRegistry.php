<?php

namespace App\Services\RecipeImportUrl;

class SupportedSourceRegistry
{
    /**
     * Supported recipe source domain patterns.
     * Each entry is an exact domain (or subdomain suffix with leading dot).
     */
    const SUPPORTED_DOMAINS = [
        'recetasgratis.net',
        'cookpad.com',
        'allrecipes.com',
        'tasty.co',
        'food.com',
        'bbcgoodfood.com',
        'recetas.com',
        'kiwilimon.com',
        'pequerecetas.com',
        'elcomidista.com',
        // Test-only domain allowed in any environment
        'recipe-test.example',
        'example-recipe.com',
    ];

    public static function isSupported(string $host): bool
    {
        $host = strtolower(ltrim($host, '.'));

        foreach (self::SUPPORTED_DOMAINS as $domain) {
            $domain = strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    public static function labelForHost(string $host): string
    {
        $parts = explode('.', strtolower($host));
        $count = count($parts);
        if ($count >= 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }
        return $host;
    }
}
