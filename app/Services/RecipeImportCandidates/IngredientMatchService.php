<?php

namespace App\Services\RecipeImportCandidates;

use App\Ingredient;
use App\ImportedRecipeCandidate;

class IngredientMatchService
{
    private const UNIT_TOKENS = [
        'kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos',
        'g', 'gr', 'grs', 'gramo', 'gramos',
        'mg', 'ml', 'cc', 'l', 'lt', 'lts', 'litro', 'litros',
        'taza', 'tazas', 'tacita', 'tacitas',
        'cda', 'cdas', 'cucharada', 'cucharadas', 'cucharadas soperas', 'cucharon', 'cucharones',
        'cdta', 'cdtas', 'cucharadita', 'cucharaditas',
        'pizca', 'pizcas', 'punado', 'punados', 'punadito',
        'diente', 'dientes', 'unidad', 'unidades', 'u', 'un', 'una',
        'lata', 'latas', 'paquete', 'paquetes', 'sobre', 'sobres',
        'rodaja', 'rodajas', 'feta', 'fetas', 'porcion', 'porciones', 'vaso', 'vasos',
    ];

    private const FRACTIONS = [
        '½' => ' 1/2 ', '¼' => ' 1/4 ', '¾' => ' 3/4 ',
        '⅓' => ' 1/3 ', '⅔' => ' 2/3 ', '⅛' => ' 1/8 ',
    ];

    /**
     * Devuelve una sugerencia por cada ingrediente crudo de la candidata.
     * Cada entrada: index, raw_text, parsed_quantity, parsed_unit_text,
     * suggested_ingredient_id, suggested_ingredient_name, confidence.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestForCandidate(ImportedRecipeCandidate $candidate): array
    {
        $raw = $candidate->raw_ingredients_json ?? [];
        $suggestions = [];

        foreach (array_values((array) $raw) as $index => $item) {
            $text = is_string($item) ? $item : (string) ($item['name'] ?? $item['text'] ?? '');
            $suggestions[] = array_merge(['index' => $index, 'raw_text' => $text], $this->matchText($text));
        }

        return $suggestions;
    }

    /**
     * Analiza un texto de ingrediente ("200 g de harina") y sugiere un ingredient_id
     * del catalogo. Conservador: exacto o cercano por nombre; si no hay match claro
     * devuelve suggested_ingredient_id = null.
     *
     * @return array<string, mixed>
     */
    public function matchText(string $rawText): array
    {
        $result = [
            'parsed_quantity'          => null,
            'parsed_unit_text'         => null,
            'suggested_ingredient_id'  => null,
            'suggested_ingredient_name' => null,
            'confidence'               => null,
        ];

        $phrase = $this->extract($rawText, $result);
        if ($phrase === '') {
            return $result;
        }

        $match = $this->lookup($phrase);
        if ($match === null && $this->depluralize($phrase) !== $phrase) {
            $match = $this->lookup($this->depluralize($phrase));
        }

        if ($match !== null) {
            $result['suggested_ingredient_id']   = $match['id'];
            $result['suggested_ingredient_name'] = $match['name'];
            $result['confidence']                = $match['confidence'];
        }

        return $result;
    }

    /**
     * Quita cantidad y unidad iniciales; devuelve la frase de ingrediente normalizada.
     * Escribe parsed_quantity / parsed_unit_text en $out.
     */
    private function extract(string $rawText, array &$out): string
    {
        $s = mb_strtolower(trim($rawText), 'UTF-8');
        $s = strtr($s, self::FRACTIONS);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim((string) $s);

        if ($s === '') {
            return '';
        }

        // Numero mixto: "1 1/2"
        if (preg_match('#^(\d+)\s+(\d+)\s*/\s*(\d+)\b\s*(.*)$#u', $s, $m)) {
            $out['parsed_quantity'] = (int) $m[1] + ((int) $m[3] !== 0 ? (int) $m[2] / (int) $m[3] : 0);
            $s = $m[4];
        } elseif (preg_match('#^(\d+)\s*/\s*(\d+)\b\s*(.*)$#u', $s, $m)) {
            $out['parsed_quantity'] = (int) $m[2] !== 0 ? (int) $m[1] / (int) $m[2] : null;
            $s = $m[3];
        } elseif (preg_match('#^(\d+(?:[.,]\d+)?)(?:\s*-\s*\d+(?:[.,]\d+)?)?\b\s*(.*)$#u', $s, $m)) {
            $out['parsed_quantity'] = (float) str_replace(',', '.', $m[1]);
            $s = $m[2];
        }

        $s = trim($s);

        $unitAlternation = implode('|', array_map(function ($u) {
            return preg_quote($u, '#');
        }, self::UNIT_TOKENS));

        if (preg_match('#^(' . $unitAlternation . ')\.?\b\s*(.*)$#u', $s, $m)) {
            $out['parsed_unit_text'] = $m[1];
            $s = $m[2];
        }

        $s = preg_replace('#^(de la |de las |del |de los |de |d\')\s*#u', '', trim($s));
        $s = preg_split('#[,(]#u', (string) $s)[0];
        $s = preg_replace('#\s+(picad[ao]s?|ralad[ao]s?|cortad[ao]s?|en cubos|en tiras|en rodajas|fresc[ao]s?|a gusto|c/n|opcional)\b.*$#u', '', trim($s));

        return trim((string) $s);
    }

    private function depluralize(string $phrase): string
    {
        if (preg_match('/(ces)$/u', $phrase)) {
            return preg_replace('/ces$/u', 'z', $phrase);
        }
        if (preg_match('/(es)$/u', $phrase) && mb_strlen($phrase) > 4) {
            return preg_replace('/es$/u', '', $phrase);
        }
        if (preg_match('/s$/u', $phrase) && mb_strlen($phrase) > 3) {
            return preg_replace('/s$/u', '', $phrase);
        }
        return $phrase;
    }

    /**
     * Busca en el catalogo activo por nombre. Devuelve ['id','name','confidence'] o null.
     */
    private function lookup(string $phrase): ?array
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return null;
        }

        $base = Ingredient::query()->where('status', 'active')->whereNull('deleted_at');

        $exact = (clone $base)
            ->where(function ($q) use ($phrase) {
                $q->where('normalized_name', $phrase)
                  ->orWhere('name', 'ILIKE', $phrase);
            })
            ->orderByRaw('char_length(name) asc')
            ->first();
        if ($exact) {
            return ['id' => $exact->id, 'name' => $exact->name, 'confidence' => 'exact'];
        }

        $like = $this->escapeLike($phrase);

        $strong = (clone $base)
            ->whereRaw('? ILIKE (\'%\' || normalized_name || \'%\')', [$phrase])
            ->orderByRaw('char_length(normalized_name) desc')
            ->first();
        if ($strong) {
            return ['id' => $strong->id, 'name' => $strong->name, 'confidence' => 'strong'];
        }

        $partial = (clone $base)
            ->where('normalized_name', 'ILIKE', '%' . $like . '%')
            ->orderByRaw('char_length(normalized_name) asc')
            ->first();
        if ($partial) {
            return ['id' => $partial->id, 'name' => $partial->name, 'confidence' => 'partial'];
        }

        return null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
