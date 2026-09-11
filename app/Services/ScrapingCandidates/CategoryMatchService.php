<?php

namespace App\Services\ScrapingCandidates;

use App\ProductCategory;

class CategoryMatchService
{
    /**
     * Descompone una ruta de categoria de origen ("/Almacen/Arroz/",
     * "Almacen > Arroz", "Almacen | Arroz") en segmentos normalizados,
     * de raiz a hoja: ["Almacen", "Arroz"].
     *
     * @return string[]
     */
    public function splitPath(string $rawPath): array
    {
        $parts = preg_split('/[\/>|]+/u', $rawPath);
        $parts = array_map(function ($part) {
            return trim(preg_replace('/\s+/u', ' ', (string) $part));
        }, $parts ?: []);

        return array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));
    }

    /**
     * Acentos/dieresis comunes en espanol que VTEX (fuente scrapeada) y el
     * catalogo local no siempre escriben igual (ej. "Almacén" scrapeado vs
     * "Almacen" en el catalogo local): se pliegan antes de comparar para
     * que la igualdad exacta no falle solo por un tilde. Esto sigue siendo
     * una comparacion exacta (determinista, sin heuristicas de similitud):
     * no es fuzzy matching, es normalizacion de texto.
     */
    private const ACCENT_MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ];

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return strtr($value, self::ACCENT_MAP);
    }

    /**
     * Sugiere un ProductCategory local a partir de la ruta de categoria
     * scrapeada. Conservador: solo devuelve una sugerencia cuando hay
     * exactamente una categoria activa cuyo nombre coincide (normalizado:
     * minusculas + sin acentos) con algun nivel de la ruta. Prioriza el
     * nivel mas especifico (la hoja); si ese nivel no tiene match unico,
     * prueba el nivel inmediatamente superior, y asi hasta la raiz.
     *
     * Si un nivel tiene mas de una categoria local con el mismo nombre
     * normalizado (ambiguo), se detiene sin sugerir nada: nunca "adivina"
     * entre varias opciones posibles.
     *
     * @return array{category_id: ?int, category_name: ?string, confidence: ?string}
     */
    public function match(string $rawPath): array
    {
        $result = ['category_id' => null, 'category_name' => null, 'confidence' => null];

        $segments = $this->splitPath($rawPath);
        if (empty($segments)) {
            return $result;
        }

        // El catalogo local de categorias es chico y curado a mano (no miles
        // de filas): traerlo entero a PHP para normalizar-y-comparar es mas
        // simple y portable (funciona igual en Postgres y en SQLite de tests)
        // que depender de una funcion SQL de acentos especifica del motor.
        $categories = ProductCategory::where('status', 'active')->whereNull('deleted_at')->get(['id', 'name']);

        $leafToRoot = array_reverse($segments);

        foreach ($leafToRoot as $depth => $segment) {
            $normalizedSegment = $this->normalize($segment);

            $matches = $categories->filter(function ($category) use ($normalizedSegment) {
                return $this->normalize($category->name) === $normalizedSegment;
            });

            if ($matches->count() === 1) {
                $category = $matches->first();

                return [
                    'category_id'   => $category->id,
                    'category_name' => $category->name,
                    'confidence'    => $depth === 0 ? 'category_leaf' : 'category_ancestor',
                ];
            }

            if ($matches->count() > 1) {
                // Nombre ambiguo en el catalogo local: no elegir arbitrariamente.
                return $result;
            }
        }

        return $result;
    }
}
