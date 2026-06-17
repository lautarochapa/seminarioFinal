<?php

namespace App\Services\RecipeImportText;

class RecipeTextParser
{
    const SECTION_INGREDIENTS  = 'ingredients';
    const SECTION_STEPS        = 'steps';
    const SECTION_DESCRIPTION  = 'description';
    const SECTION_UNKNOWN      = 'unknown';

    const INGREDIENT_HEADERS = [
        'ingredientes', 'ingredients', 'materiales', 'necesitas',
    ];

    const STEP_HEADERS = [
        'preparacion', 'preparacion:', 'instrucciones', 'pasos', 'elaboracion',
        'modo de preparacion', 'como hacer', 'procedimiento', 'steps', 'directions',
    ];

    const UNIT_PATTERNS = [
        'kg'   => ['kg', 'kilogramo', 'kilogramos', 'kilo', 'kilos'],
        'g'    => ['g', 'gr', 'gramo', 'gramos'],
        'mg'   => ['mg', 'miligramo', 'miligramos'],
        'l'    => ['l', 'lt', 'litro', 'litros', 'liter'],
        'ml'   => ['ml', 'mililitro', 'mililitros', 'cc'],
        'tbsp' => ['cda', 'cdas', 'cucharada', 'cucharadas', 'tbsp', 'tablespoon'],
        'tsp'  => ['cdita', 'cditas', 'cucharadita', 'cucharaditas', 'tsp', 'teaspoon'],
        'cup'  => ['taza', 'tazas', 'cup', 'cups'],
        'unit' => ['unidad', 'unidades', 'pieza', 'piezas', 'pz', 'u', 'un'],
    ];

    /**
     * Parse plain text into structured recipe data.
     * Returns null if minimum structure cannot be determined.
     */
    public function parse(string $text): ?array
    {
        $lines    = $this->cleanLines($text);
        $title    = $this->extractTitle($lines);

        if (!$title) {
            return null;
        }

        $servings    = $this->extractServings($text);
        $prepMinutes = $this->extractTime($text, ['prep', 'preparacion', 'preparacion', 'remojo']);
        $cookMinutes = $this->extractTime($text, ['coccion', 'coccion', 'horno', 'fuego', 'cook']);

        $sections     = $this->splitIntoSections($lines);
        $ingredients  = $this->parseIngredients($sections[self::SECTION_INGREDIENTS] ?? []);
        $steps        = $this->parseSteps($sections[self::SECTION_STEPS] ?? []);
        $observations = $sections[self::SECTION_UNKNOWN] ?? [];

        $description  = implode(' ', $sections[self::SECTION_DESCRIPTION] ?? []) ?: null;

        $ambiguousFlags = [];
        if ($ingredients && count(array_filter($ingredients, fn ($i) => $i['unit_ambiguous'])) > 0) {
            $ambiguousFlags[] = 'ingredient_units_ambiguous';
        }
        if (empty($ingredients)) {
            $ambiguousFlags[] = 'no_ingredients_detected';
        }
        if (empty($steps)) {
            $ambiguousFlags[] = 'no_steps_detected';
        }

        return [
            'title'          => $title,
            'description'    => $description ? mb_substr($description, 0, 500) : null,
            'servings'       => $servings,
            'prep_minutes'   => $prepMinutes,
            'cook_minutes'   => $cookMinutes,
            'ingredients'    => $ingredients,
            'steps'          => $steps,
            'observations'   => $observations,
            'ambiguous_flags'=> $ambiguousFlags,
        ];
    }

    private function cleanLines(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }

    private function extractTitle(array $lines): ?string
    {
        foreach ($lines as $line) {
            $lower = $this->normalize($line);
            if ($this->isSectionHeader($lower)) {
                continue;
            }
            if (preg_match('/^(receta|titulo|title|nombre)[:\-\s]+(.+)/i', $line, $m)) {
                return mb_substr(trim($m[2]), 0, 200);
            }
            if (strlen($line) >= 3 && strlen($line) <= 200) {
                return $line;
            }
        }
        return null;
    }

    private function extractServings(string $text): ?int
    {
        if (preg_match('/(?:porciones?|rinde|sirve\s+para|para\s+)\s*:?\s*(\d+)/i', $text, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function extractTime(string $text, array $keywords): ?int
    {
        $kwPattern = implode('|', array_map('preg_quote', $keywords));
        if (preg_match('/(?:' . $kwPattern . ')\s*:?\s*(\d+)\s*(?:h(?:ora)?s?)?(?:\s*(?:y|:|\+)?\s*(\d+)\s*min(?:uto)?s?)?/i', $text, $m)) {
            $hours   = isset($m[1]) ? (int) $m[1] : 0;
            $minutes = isset($m[2]) ? (int) $m[2] : 0;

            if ($minutes === 0 && $hours <= 5) {
                return $hours;
            }
            return $hours * 60 + $minutes;
        }

        if (preg_match('/(?:' . $kwPattern . ')\s*:?\s*(\d+)\s*min(?:uto)?s?/i', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function splitIntoSections(array $lines): array
    {
        $sections     = [
            self::SECTION_DESCRIPTION  => [],
            self::SECTION_INGREDIENTS  => [],
            self::SECTION_STEPS        => [],
            self::SECTION_UNKNOWN      => [],
        ];
        $currentSection = self::SECTION_DESCRIPTION;
        $firstLineSeen  = false;

        foreach ($lines as $line) {
            $lower = $this->normalize($line);

            if ($this->matchesHeader($lower, self::INGREDIENT_HEADERS)) {
                $currentSection = self::SECTION_INGREDIENTS;
                $firstLineSeen  = true;
                continue;
            }

            if ($this->matchesHeader($lower, self::STEP_HEADERS)) {
                $currentSection = self::SECTION_STEPS;
                $firstLineSeen  = true;
                continue;
            }

            if (!$firstLineSeen) {
                $firstLineSeen = true;
                continue; // skip title line from sections
            }

            $sections[$currentSection][] = $line;
        }

        return $sections;
    }

    private function matchesHeader(string $normalized, array $headers): bool
    {
        foreach ($headers as $h) {
            if ($normalized === $h || $normalized === $h . ':') {
                return true;
            }
        }
        return false;
    }

    private function isSectionHeader(string $normalized): bool
    {
        return $this->matchesHeader($normalized, self::INGREDIENT_HEADERS)
            || $this->matchesHeader($normalized, self::STEP_HEADERS);
    }

    private function parseIngredients(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $parsed = $this->parseIngredientLine($line);
            if ($parsed) {
                $result[] = $parsed;
            }
        }
        return $result;
    }

    private function parseIngredientLine(string $line): ?array
    {
        // Match: optional quantity, optional unit, ingredient name
        $pattern = '/^(?:[-*\•]\s*)?(?:(\d+(?:[.,]\d+)?(?:\/\d+)?)\s*)?([a-zA-ZáéíóúüÁÉÍÓÚÜ]+\.?)?\s+(.+)$/u';

        if (!preg_match($pattern, $line, $m)) {
            return [
                'raw_line'      => $line,
                'quantity'      => null,
                'unit_raw'      => null,
                'unit_code'     => null,
                'unit_ambiguous'=> true,
                'name_raw'      => $line,
            ];
        }

        $quantityRaw = $m[1] !== '' ? $m[1] : null;
        $unitRaw     = $m[2] !== '' ? $m[2] : null;
        $nameRaw     = trim($m[3]);

        $quantity   = $quantityRaw ? (float) str_replace(',', '.', str_replace('/', '/', $quantityRaw)) : null;
        $unitCode   = $unitRaw ? $this->resolveUnit($unitRaw) : null;
        $ambiguous  = $unitRaw !== null && $unitCode === null;

        return [
            'raw_line'      => $line,
            'quantity'      => $quantity,
            'unit_raw'      => $unitRaw,
            'unit_code'     => $unitCode,
            'unit_ambiguous'=> $ambiguous,
            'name_raw'      => $nameRaw,
        ];
    }

    private function resolveUnit(string $raw): ?string
    {
        $lower = $this->normalize($raw);
        $lower = rtrim($lower, '.');

        foreach (self::UNIT_PATTERNS as $code => $aliases) {
            foreach ($aliases as $alias) {
                if ($lower === $alias) {
                    return $code;
                }
            }
        }
        return null;
    }

    private function parseSteps(array $lines): array
    {
        $result = [];
        $num    = 1;
        foreach ($lines as $line) {
            $text = preg_replace('/^\d+[\.\)\-\s]+/', '', $line);
            $text = trim($text);
            if ($text !== '') {
                $result[] = ['step_number' => $num++, 'description' => $text];
            }
        }
        return $result;
    }

    private function normalize(string $s): string
    {
        return mb_strtolower(trim($s));
    }
}
