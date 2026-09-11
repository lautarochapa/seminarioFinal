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
     * Sugiere un ProductCategory local a partir de la ruta de categoria
     * scrapeada. Conservador: solo devuelve una sugerencia cuando hay
     * exactamente una categoria activa cuyo nombre coincide (sin distinguir
     * mayusculas) con algun nivel de la ruta. Prioriza el nivel mas
     * especifico (la hoja); si ese nivel no tiene match unico, prueba el
     * nivel inmediatamente superior, y asi hasta la raiz.
     *
     * Si un nivel tiene mas de una categoria local con el mismo nombre
     * (ambiguo), se detiene sin sugerir nada: nunca "adivina" entre varias
     * opciones posibles.
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

        $leafToRoot = array_reverse($segments);

        foreach ($leafToRoot as $depth => $segment) {
            $normalized = mb_strtolower($segment, 'UTF-8');

            $matches = ProductCategory::where('status', 'active')
                ->whereNull('deleted_at')
                ->whereRaw('lower(name) = ?', [$normalized])
                ->limit(2)
                ->get();

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
