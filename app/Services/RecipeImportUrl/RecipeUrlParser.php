<?php

namespace App\Services\RecipeImportUrl;

class RecipeUrlParser
{
    const MAX_BODY_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * Parse recipe data from HTML.
     * Returns structured array or null if no recipe detected.
     */
    public function parse(string $html): ?array
    {
        $html = mb_substr($html, 0, self::MAX_BODY_BYTES);

        $fromJsonLd = $this->parseJsonLd($html);
        if ($fromJsonLd) {
            return $fromJsonLd;
        }

        return $this->parseFallback($html);
    }

    private function parseJsonLd(string $html): ?array
    {
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        if (empty($matches[1])) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);
            if (!$data) {
                continue;
            }

            $recipeNode = $this->findRecipeNode($data);
            if ($recipeNode) {
                return $this->extractFromJsonLd($recipeNode);
            }
        }

        return null;
    }

    private function findRecipeNode(array $data): ?array
    {
        if (isset($data['@type'])) {
            $type = is_array($data['@type']) ? $data['@type'] : [$data['@type']];
            if (in_array('Recipe', $type, true)) {
                return $data;
            }
        }

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $result = $this->findRecipeNode($node);
                    if ($result) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }

    private function extractFromJsonLd(array $node): array
    {
        $ingredients = [];
        if (isset($node['recipeIngredient']) && is_array($node['recipeIngredient'])) {
            $ingredients = array_values(array_filter($node['recipeIngredient'], 'is_string'));
        }

        $steps = [];
        if (isset($node['recipeInstructions']) && is_array($node['recipeInstructions'])) {
            $num = 1;
            foreach ($node['recipeInstructions'] as $step) {
                $text = '';
                if (is_string($step)) {
                    $text = $step;
                } elseif (is_array($step) && isset($step['text'])) {
                    $text = $step['text'];
                }
                $text = strip_tags($text);
                if ($text !== '') {
                    $steps[] = ['step_number' => $num++, 'description' => $text];
                }
            }
        }

        $image = null;
        if (isset($node['image'])) {
            if (is_string($node['image'])) {
                $image = $node['image'];
            } elseif (is_array($node['image']) && isset($node['image']['url'])) {
                $image = $node['image']['url'];
            } elseif (is_array($node['image']) && isset($node['image'][0])) {
                $img = $node['image'][0];
                $image = is_array($img) ? ($img['url'] ?? null) : $img;
            }
        }

        $servings = null;
        if (isset($node['recipeYield'])) {
            $raw = is_array($node['recipeYield']) ? ($node['recipeYield'][0] ?? '') : $node['recipeYield'];
            preg_match('/\d+/', (string) $raw, $m);
            $servings = isset($m[0]) ? (int) $m[0] : null;
        }

        $prepMinutes = $this->parseDuration($node['prepTime'] ?? null);
        $cookMinutes = $this->parseDuration($node['cookTime'] ?? null);

        return [
            'title'        => $this->text($node['name'] ?? null),
            'description'  => $this->text($node['description'] ?? null),
            'servings'     => $servings,
            'prep_minutes' => $prepMinutes,
            'cook_minutes' => $cookMinutes,
            'ingredients'  => $ingredients,
            'steps'        => $steps,
            'image_url'    => $image ? filter_var($image, FILTER_SANITIZE_URL) : null,
        ];
    }

    private function parseFallback(string $html): ?array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $title = $this->metaContent($xpath, 'og:title')
            ?: $this->metaContent($xpath, 'twitter:title')
            ?: $this->xpathText($xpath, '//h1[1]')
            ?: $this->xpathText($xpath, '//title[1]');

        if (!$title) {
            return null;
        }

        $description = $this->metaContent($xpath, 'og:description')
            ?: $this->metaContent($xpath, 'description');

        $image = $this->metaContent($xpath, 'og:image')
            ?: $this->metaContent($xpath, 'twitter:image');

        return [
            'title'        => $this->text($title),
            'description'  => $this->text($description),
            'servings'     => null,
            'prep_minutes' => null,
            'cook_minutes' => null,
            'ingredients'  => [],
            'steps'        => [],
            'image_url'    => $image ? filter_var($image, FILTER_SANITIZE_URL) : null,
        ];
    }

    private function parseDuration(?string $iso): ?int
    {
        if (!$iso) {
            return null;
        }
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m);
        $hours   = isset($m[1]) ? (int) $m[1] : 0;
        $minutes = isset($m[2]) ? (int) $m[2] : 0;
        $total   = $hours * 60 + $minutes;
        return $total > 0 ? $total : null;
    }

    private function text($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return mb_substr(trim(strip_tags((string) $value)), 0, 1000);
    }

    private function metaContent(\DOMXPath $xpath, string $name): ?string
    {
        $nodes = $xpath->query(
            "//meta[@property='" . $name . "']/@content | //meta[@name='" . $name . "']/@content"
        );
        return ($nodes && $nodes->length > 0) ? $nodes->item(0)->nodeValue : null;
    }

    private function xpathText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->textContent) : null;
    }
}
