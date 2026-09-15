<?php

namespace App\Console\Commands;

use App\AuditLog;
use App\Ingredient;
use App\ImportedRecipeCandidate;
use App\Product;
use App\RecipeIngredient;
use App\Repositories\Ingredients\IngredientRepository;
use App\Services\Ingredients\IngredientService;
use App\Services\RecipeImportCandidates\IngredientMatchService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saneamiento controlado del catalogo Ingredient para mejorar el mapeo
 * automatico de recetas scrapeadas: reporta duplicados, consolida los
 * duplicados explicitamente indicados (sin romper FK) y agrega solo los
 * ingredientes base explicitamente listados (verificando antes que no
 * existan). No crea nada por si solo si no se pasa --apply.
 */
class SanitizeIngredientCatalog extends Command
{
    protected $signature = 'ingredients:sanitize-catalog
        {--apply : Persistir los cambios. Sin esta opcion solo se reporta (dry-run).}
        {--consolidate=* : Pares "duplicado_id:canonico_id" a consolidar, ej. --consolidate=21:9}
        {--add=* : Nombres de ingredientes base a crear si no existen, ej. --add=Pimiento --add=Ajo}
        {--unit= : Code de UnitMeasure a usar como base_unit_id para los ingredientes creados con --add (default: g)}';

    protected $description = 'Audita duplicados/faltantes del catalogo Ingredient y, con --apply, consolida duplicados indicados y agrega ingredientes base indicados.';

    public function handle(IngredientMatchService $matcher): int
    {
        $apply = (bool) $this->option('apply');

        $this->reportDuplicates();
        $this->reportUnresolvedFrequency($matcher);

        $consolidatePairs = $this->option('consolidate');
        if ($consolidatePairs) {
            $this->consolidate($consolidatePairs, $apply);
        }

        $toAdd = $this->option('add');
        if ($toAdd) {
            $this->addMissing($toAdd, $apply);
        }

        if (!$apply) {
            $this->comment('Dry-run: no se modifico ningun dato. Ejecutar con --apply para persistir.');
        }

        return 0;
    }

    /**
     * Reporta (nunca modifica) ingredientes activos que comparten el mismo
     * name pero tienen normalized_name distinto -> duplicados reales que
     * generan ambiguedad en IngredientMatchService (ver Pechuga de pollo).
     */
    private function reportDuplicates(): void
    {
        $this->info('--- Duplicados por name (normalized_name distinto) ---');
        $rows = Ingredient::where('status', 'active')
            ->select('name', DB::raw('count(*) as total'))
            ->groupBy('name')
            ->having(DB::raw('count(*)'), '>', 1)
            ->get();

        if ($rows->isEmpty()) {
            $this->line('Ninguno.');
            return;
        }

        foreach ($rows as $row) {
            $this->line($row->name . ':');
            foreach (Ingredient::where('status', 'active')->where('name', $row->name)->orderBy('id')->get() as $dup) {
                $this->line('  #' . $dup->id . ' normalized_name="' . $dup->normalized_name . '" created_at=' . $dup->created_at);
            }
        }
    }

    /**
     * Reporta (nunca modifica) las frases de ingrediente que hoy quedan sin
     * sugerencia en TODAS las RecipeImportCandidate existentes, ordenadas
     * por frecuencia, con un ejemplo de receta real cada una.
     */
    private function reportUnresolvedFrequency(IngredientMatchService $matcher): void
    {
        $this->info('--- Ingredientes sin sugerencia (todas las candidatas), por frecuencia ---');

        $freq = [];
        $examples = [];
        foreach (ImportedRecipeCandidate::whereNotNull('raw_ingredients_json')->get() as $candidate) {
            foreach ((array) $candidate->raw_ingredients_json as $text) {
                $text = is_string($text) ? $text : (string) ($text['name'] ?? $text['text'] ?? '');
                if ($text === '') {
                    continue;
                }
                $match = $matcher->matchText($text);
                if ($match['suggested_ingredient_id']) {
                    continue;
                }
                $key = mb_strtolower(trim($text), 'UTF-8');
                $freq[$key] = ($freq[$key] ?? 0) + 1;
                if (!isset($examples[$key])) {
                    $examples[$key] = $candidate->raw_title . ' (#' . $candidate->id . ')';
                }
            }
        }

        arsort($freq);
        $shown = 0;
        foreach ($freq as $text => $count) {
            $this->line('x' . $count . "\t" . $text . "\t-> " . $examples[$text]);
            $shown++;
            if ($shown >= 30) {
                break;
            }
        }
        if (count($freq) > 30) {
            $this->line('... y ' . (count($freq) - 30) . ' mas (no mostradas).');
        }
    }

    /**
     * Migra referencias (RecipeIngredient, Product.ingredient_id) del
     * duplicado hacia el canonico y desactiva el duplicado (status=inactive
     * + soft delete via IngredientService::delete(), no borrado fisico, no
     * rompe historial). Nunca toca el canonico.
     */
    private function consolidate(array $pairs, bool $apply): void
    {
        $this->info('--- Consolidacion de duplicados ---');

        foreach ($pairs as $pair) {
            [$duplicateId, $canonicalId] = array_map('intval', explode(':', $pair) + [null, null]);
            if (!$duplicateId || !$canonicalId) {
                $this->error('Formato invalido: "' . $pair . '" (esperado duplicado_id:canonico_id)');
                continue;
            }

            $duplicate = Ingredient::where('status', 'active')->find($duplicateId);
            $canonical = Ingredient::where('status', 'active')->find($canonicalId);
            if (!$duplicate || !$canonical) {
                $this->error('No se encontro Ingredient activo #' . $duplicateId . ' y/o #' . $canonicalId . '.');
                continue;
            }

            $recipeRefs = RecipeIngredient::where('ingredient_id', $duplicateId)->count();
            $productRefs = Product::where('ingredient_id', $duplicateId)->count();

            $this->line('#' . $duplicateId . ' "' . $duplicate->name . '" -> #' . $canonicalId . ' "' . $canonical->name . '"');
            $this->line('  RecipeIngredient a migrar: ' . $recipeRefs . '. Product a migrar: ' . $productRefs . '.');

            if (!$apply) {
                continue;
            }

            DB::transaction(function () use ($duplicateId, $canonicalId, $duplicate) {
                RecipeIngredient::where('ingredient_id', $duplicateId)->update(['ingredient_id' => $canonicalId]);
                Product::where('ingredient_id', $duplicateId)->update(['ingredient_id' => $canonicalId]);

                $actor = User::whereHas('roles', function ($q) {
                    $q->where('code', 'super_admin');
                })->first() ?: User::first();

                app(IngredientService::class)->delete(
                    $actor ? $actor->id : null,
                    $duplicateId,
                    '127.0.0.1',
                    'artisan ingredients:sanitize-catalog'
                );
            });

            $this->info('  Consolidado y #' . $duplicateId . ' desactivado.');
        }
    }

    /**
     * Crea unicamente los ingredientes indicados que NO existan ya
     * (busqueda case-insensitive por normalized_name).
     *
     * IMPORTANTE: NO usa IngredientService::create() a proposito.
     * IngredientService::normalizeName() genera normalized_name con
     * guiones bajos (ej. "Tomate fresco" -> "tomate_fresco") y rompe
     * acentos (ej. "Champiñones" -> "champi_ones") — comportamiento ya
     * cubierto por tests existentes (IngredientsTest::...'tomate_redondo'),
     * asi que es un contrato deliberado de esa API que no corresponde
     * tocar aca. Pero es INCOMPATIBLE con el resto del catalogo (25 de 27
     * filas activas usan normalized_name separado por espacios, sin guion
     * bajo) y con IngredientMatchService, que compara normalized_name
     * como frase con espacios. Usar el service tal cual habria creado
     * ingredientes que el matcher nunca podria encontrar.
     * Por eso se usa IngredientRepository::create() directamente, con el
     * mismo normalizado (minusculas + espacios colapsados, preservando
     * acentos) que ya usan ProductService/BrandService y que coincide con
     * las filas reales del catalogo, replicando manualmente la auditoria
     * que haria el service.
     */
    private function addMissing(array $names, bool $apply): void
    {
        $this->info('--- Alta de ingredientes base ---');

        $unitCode = $this->option('unit') ?: 'g';
        $unit = \App\UnitMeasure::where('status', 'active')->where('code', $unitCode)->first();
        if (!$unit) {
            $this->error('UnitMeasure con code="' . $unitCode . '" no encontrada; no se puede crear ningun ingrediente.');
            return;
        }

        $actor = User::whereHas('roles', function ($q) {
            $q->where('code', 'super_admin');
        })->first() ?: User::first();
        $repo = app(IngredientRepository::class);

        foreach ($names as $name) {
            $normalized = mb_strtolower(preg_replace('/\s+/', ' ', trim($name)), 'UTF-8');
            $exists = Ingredient::where('status', 'active')->where('normalized_name', $normalized)->exists();

            if ($exists) {
                $this->line('"' . $name . '" ya existe (normalized_name="' . $normalized . '"). Omitido.');
                continue;
            }

            $this->line('"' . $name . '" -> nuevo, normalized_name="' . $normalized . '" (unidad base: ' . $unit->name . ').');

            if (!$apply) {
                continue;
            }

            $ingredient = $repo->create([
                'name'            => trim($name),
                'normalized_name' => $normalized,
                'base_unit_id'    => $unit->id,
                'is_generic'      => true,
                'is_preparation'  => false,
                'is_supplement'   => false,
                'status'          => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actor ? $actor->id : null,
                'action'      => 'ingredient.created',
                'entity_name' => 'ingredients',
                'entity_id'   => (string) $ingredient->id,
                'old_values'  => null,
                'new_values'  => ['name' => $ingredient->name, 'normalized_name' => $ingredient->normalized_name],
                'ip_address'  => '127.0.0.1',
                'user_agent'  => 'artisan ingredients:sanitize-catalog',
            ]);
        }
    }
}
