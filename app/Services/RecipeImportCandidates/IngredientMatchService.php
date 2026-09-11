<?php

namespace App\Services\RecipeImportCandidates;

use App\Ingredient;
use App\ImportedRecipeCandidate;
use App\UnitMeasure;

class IngredientMatchService
{
    /**
     * Mapeo conservador de texto de unidad detectado (parsed_unit_text) al
     * code de una UnitMeasure real del catalogo. Solo incluye unidades que
     * efectivamente existen como UnitMeasure hoy (g, kg, mg, ml, l, taza,
     * cucharada, paquete, unidad); el resto (cdta, pizca, diente, lata,
     * sobre, rodaja, feta, porcion, vaso, punado) no tiene equivalente en el
     * catalogo actual y se deja sin resolver a proposito -> esa linea sigue
     * requiriendo unidad manual aunque el ingrediente si se haya sugerido.
     */
    private const UNIT_TEXT_TO_CODE = [
        'g' => 'g', 'gr' => 'g', 'grs' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'kg' => 'kg', 'kgs' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
        'mg' => 'mg',
        'ml' => 'ml', 'cc' => 'ml',
        'l' => 'l', 'lt' => 'l', 'lts' => 'l', 'litro' => 'l', 'litros' => 'l',
        'taza' => 'cup', 'tazas' => 'cup', 'tacita' => 'cup', 'tacitas' => 'cup',
        'cda' => 'tbsp', 'cdas' => 'tbsp', 'cucharada' => 'tbsp', 'cucharadas' => 'tbsp',
        'paquete' => 'package', 'paquetes' => 'package',
        'unidad' => 'unit', 'unidades' => 'unit', 'u' => 'unit', 'un' => 'unit', 'una' => 'unit',
    ];

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
     * Sugiere un ingredient_id a partir de un nombre de producto scrapeado
     * ("Arroz largo fino Gallo 1 kg"). A diferencia de matchText() (pensado para
     * lineas de ingrediente de receta), solo compara contra el inicio del nombre:
     * el ingrediente debe ser la palabra/frase inicial, nunca una coincidencia en
     * cualquier parte del texto. Esto evita falsos positivos como asignar "Arroz"
     * a "Galletitas de arroz" o "Alfajor de arroz" solo porque la palabra aparece
     * en el nombre.
     *
     * @return array<string, mixed>
     */
    public function matchProductName(string $rawName): array
    {
        $result = [
            'suggested_ingredient_id'  => null,
            'suggested_ingredient_name' => null,
            'confidence'               => null,
        ];

        $phrase = $this->normalizeProductName($rawName);
        if ($phrase === '') {
            return $result;
        }

        $match = $this->lookupProductHead($phrase);
        if ($match !== null) {
            $result['suggested_ingredient_id']   = $match['id'];
            $result['suggested_ingredient_name'] = $match['name'];
            $result['confidence']                = $match['confidence'];
        }

        return $result;
    }

    /**
     * Quita presentacion final (cantidad + unidad) del nombre de producto,
     * ya que no forma parte del nombre del ingrediente ("... 1 kg" -> "...").
     */
    private function normalizeProductName(string $name): string
    {
        $s = mb_strtolower(trim($name), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        $unitAlternation = implode('|', array_map(function ($u) {
            return preg_quote($u, '#');
        }, self::UNIT_TOKENS));
        $s = preg_replace(
            '#\s+\d+(?:[.,]\d+)?\s*(' . $unitAlternation . ')\.?$#u',
            '',
            trim((string) $s)
        );

        return trim((string) $s);
    }

    /**
     * Busca un ingrediente cuyo normalized_name sea exactamente la frase o
     * coincida con su inicio respetando limite de palabra. Prefiere la
     * coincidencia mas larga (mas especifica) cuando hay varias posibles.
     *
     * @return array<string, mixed>|null
     */
    private function lookupProductHead(string $phrase): ?array
    {
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

        $prefix = (clone $base)
            ->whereRaw('? ILIKE (normalized_name || \' %\')', [$phrase])
            ->orderByRaw('char_length(normalized_name) desc')
            ->first();
        if ($prefix) {
            return ['id' => $prefix->id, 'name' => $prefix->name, 'confidence' => 'strong'];
        }

        return null;
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
            'is_optional'              => $this->isOptionalText($rawText),
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
     * Resuelve un texto de unidad detectado (ej. "g", "cda", "un") a una
     * UnitMeasure activa real, solo para las unidades inequivocas listadas
     * en UNIT_TEXT_TO_CODE. Devuelve null para cualquier otra (no inventa
     * ni adivina): esa linea sigue necesitando que el admin elija unidad.
     */
    public function resolveUnitId(?string $unitText): ?int
    {
        if (!$unitText) {
            return null;
        }

        $code = self::UNIT_TEXT_TO_CODE[mb_strtolower(trim($unitText), 'UTF-8')] ?? null;
        if (!$code) {
            return null;
        }

        $unit = UnitMeasure::where('status', 'active')->where('code', $code)->first();
        return $unit ? $unit->id : null;
    }

    /**
     * Detecta opcionalidad solo por una senal clara al comienzo del texto
     * ORIGINAL de la linea ("Opcional ...", "Opcional: ...", "Opcionalmente
     * ..."). Nunca se infiere de frases ambiguas en otra posicion del texto.
     */
    public function isOptionalText(string $rawText): bool
    {
        return (bool) preg_match('/^\s*opcional(?:mente)?\s*:?\s*/iu', $rawText);
    }

    /**
     * Rango Unicode de emojis/pictogramas comunes en texto scrapeado
     * (ej. "1 cebolla morada 🧅"). Se descartan antes de cualquier otro
     * procesamiento: no aportan senal de matching y ensucian la frase.
     */
    private const EMOJI_PATTERN = '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u';

    /**
     * Quita cantidad y unidad iniciales; devuelve la frase de ingrediente normalizada.
     * Escribe parsed_quantity / parsed_unit_text en $out.
     */
    private function extract(string $rawText, array &$out): string
    {
        $s = mb_strtolower(trim($rawText), 'UTF-8');
        $s = strtr($s, self::FRACTIONS);
        $s = preg_replace(self::EMOJI_PATTERN, '', $s);
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

            // "tacita(s)/taza(s) (de) cafe" es una medida idiomatica (el
            // tamano de una taza de cafe), no un ingrediente: si el texto
            // sigue con "cafe" pegado a una unidad de taza, tambien se
            // descarta para no confundir "cafe" con el ingrediente real.
            if (in_array($m[1], ['taza', 'tazas', 'tacita', 'tacitas'], true)) {
                $s = preg_replace('#^(de\s+)?caf[eé]\s+#u', '', trim($s));
            }
        }

        $s = preg_replace('#^(de la |de las |del |de los |de |d\')\s*#u', '', trim($s));
        $s = preg_split('#[,(]#u', (string) $s)[0];
        $s = preg_replace('#\s+(picad[ao]s?|rallad[ao]s?|cortad[ao]s?|en cubos|en tiras|en rodajas|fresc[ao]s?|a gusto|c/n|opcional)\b.*$#u', '', trim($s));

        $phrase = trim((string) $s);

        // Cantidad contable sin unidad explicita ("1 cebolla morada"): se
        // asume unidad "u" (pieza), que es como se escriben habitualmente
        // las recetas en espanol. Nunca se inventa si no hubo cantidad
        // parseada o si ya se detecto una unidad explicita (ej. "250 g").
        if ($phrase !== '' && $out['parsed_quantity'] !== null && $out['parsed_unit_text'] === null) {
            $out['parsed_unit_text'] = 'u';
        }

        return $phrase;
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
     * Busca en el catalogo activo por nombre, de forma conservadora:
     *
     * 1) exact: la frase coincide exactamente con el nombre del ingrediente.
     * 2) conservative_prefix (hacia adelante): el nombre de un ingrediente
     *    es el INICIO de la frase, respetando limite de palabra (ej.
     *    "cebolla morada" empieza con "cebolla"). Nunca una coincidencia en
     *    cualquier parte del texto: eso es lo que producia falsos positivos
     *    como "aceite de oliva" -> "Oliva" (oliva no es el inicio de la
     *    frase) o "galletitas de arroz" -> "Arroz".
     * 3) conservative_prefix (hacia atras): la frase es el INICIO del
     *    nombre de un ingrediente (ej. "pechuga" -> "Pechuga de pollo").
     *    Solo se acepta si hay EXACTAMENTE un ingrediente activo que
     *    empiece asi; si hay mas de uno (ej. "Pechuga de pollo" y "Pechuga
     *    de pavo"), es ambiguo y no se sugiere nada.
     *
     * Sin nivel de alias/sinonimos: el catalogo de Ingredient no tiene hoy
     * una tabla de alias/sinonimos (a diferencia de Product/ProductAlias),
     * asi que ese nivel no aplica todavia.
     *
     * Devuelve ['id','name','confidence'] o null.
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

        $forward = (clone $base)
            ->whereRaw('? ILIKE (normalized_name || \' %\')', [$phrase])
            ->orderByRaw('char_length(normalized_name) desc')
            ->first();
        if ($forward) {
            return ['id' => $forward->id, 'name' => $forward->name, 'confidence' => 'conservative_prefix'];
        }

        $reverseMatches = (clone $base)
            ->whereRaw('normalized_name ILIKE (? || \' %\')', [$phrase])
            ->orderBy('normalized_name')
            ->limit(2)
            ->get();
        if ($reverseMatches->count() === 1) {
            $unique = $reverseMatches->first();
            return ['id' => $unique->id, 'name' => $unique->name, 'confidence' => 'conservative_prefix'];
        }

        return null;
    }
}
