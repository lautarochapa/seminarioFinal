<?php

namespace App\Services\Admin;

use App\User;

/** Read-only counts for destinations the current administrator can actually open. */
class AdminDashboardService
{
    public function build(User $user, array $screens): array
    {
        $sections = [];
        $jobCounts = [];
        foreach ($this->definitions() as $section) {
            $cards = [];
            foreach ($section['cards'] as $card) {
                $screen = $card['screen'];
                if (!isset($screens[$screen])) continue;
                $permission = $screens[$screen]['permission'] ?? 'web.admin.'.$screen;
                if (!$user->hasPermission($permission)) continue;
                // Related job counts additionally require access to that scraping module.
                if (isset($card['job_screen'])) {
                    $jobScreen = $card['job_screen'];
                    if (!isset($screens[$jobScreen]) || !$user->hasPermission($screens[$jobScreen]['permission'] ?? 'web.admin.'.$jobScreen)) continue;
                }
                $cards[] = [
                    'key' => $card['key'],
                    'label' => $card['label'],
                    'value' => $this->count($card, $jobCounts),
                    'description' => $card['description'],
                    'url' => '/admin-web/'.$screen,
                ];
            }
            if ($cards) {
                $sections[] = [
                    'key' => $section['key'], 'title' => $section['title'],
                    'description' => $section['description'], 'cards' => $cards,
                ];
            }
        }
        return ['sections' => $sections];
    }

    private function count(array $card, array &$jobCounts): int
    {
        if (isset($card['job_metric'])) {
            $type = $card['job_type'];
            if (!isset($jobCounts[$type])) {
                $jobCounts[$type] = \App\ScrapingJob::where('job_type', $type)
                    ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status IN ('pending', 'running', 'cancel_requested') THEN 1 ELSE 0 END) AS active")
                    ->first();
            }
            return (int) $jobCounts[$type]->{$card['job_metric']};
        }
        $model = $card['model'];
        $query = $model::query(); // Eloquent keeps the model's soft-delete scope.
        foreach ($card['where'] ?? [] as $field => $value) {
            if (is_array($value)) $query->whereIn($field, $value);
            else $query->where($field, $value);
        }
        if (isset($card['job_type'])) {
            $query->whereHas('job', function ($job) use ($card) {
                $job->where('job_type', $card['job_type']);
            });
        }
        return (int) $query->count();
    }

    private function card(string $key, string $label, string $description, string $screen, string $model, array $where = []): array
    {
        return compact('key', 'label', 'description', 'screen', 'model', 'where');
    }

    private function scrapingCards(): array
    {
        $cards = [];
        foreach ([
            ['product', 'productos', 'product_prices', 'supermarket-scraping'],
            ['recipe', 'recetas', 'recipe_scraping', 'recipe-scraping'],
        ] as [$key, $label, $type, $screen]) {
            $scope = ['job_type' => $type, 'job_screen' => $screen];
            $cards[] = $this->card($key.'_jobs_active', 'Ejecuciones de '.$label.' en curso',
                'En espera, ejecutándose o esperando cancelación. Es el estado registrado, no una prueba de conexión.', $screen, \App\ScrapingJob::class)
                + $scope + ['job_metric' => 'active'];
            $cards[] = $this->card($key.'_jobs_total', 'Ejecuciones de '.$label.' registradas',
                'Historial completo: incluye completadas, fallidas, canceladas y en curso.', $screen, \App\ScrapingJob::class)
                + $scope + ['job_metric' => 'total'];
            $cards[] = $this->card($key.'_alerts_open', 'Alertas de '.$label.' abiertas',
                'Alertas sin resolver asociadas a ejecuciones de '.$label.'.', 'scraping-alerts', \App\ScrapingAlert::class, ['status' => 'open']) + $scope;
            $cards[] = $this->card($key.'_errors_total', 'Errores de '.$label.' registrados',
                'Errores históricos asociados a estas ejecuciones; no equivalen a fallas actuales ni a cantidad de ejecuciones fallidas.', $screen, \App\ScrapingError::class) + $scope;
        }
        return $cards;
    }

    private function definitions(): array
    {
        $allStates = 'Registros no eliminados, en cualquier estado.';
        return [
            ['key' => 'catalog', 'title' => 'Catálogo y supermercados',
                'description' => 'Cantidades registradas en cada módulo. Los totales incluyen registros inactivos cuando el módulo los conserva.',
                'cards' => [
                    $this->card('products', 'Productos registrados', $allStates, 'products', \App\Product::class),
                    $this->card('ingredients', 'Ingredientes', $allStates, 'ingredients', \App\Ingredient::class),
                    $this->card('supermarkets', 'Cadenas de supermercados', $allStates, 'supermarkets', \App\SupermarketChain::class),
                    $this->card('branches', 'Sucursales', $allStates, 'branches', \App\SupermarketBranch::class),
                    $this->card('published_products', 'Productos publicados', 'Parte del total de productos: activos y habilitados para el catálogo público. No incluye pendientes de revisión.', 'products', \App\Product::class, ['status' => 'active', 'is_active' => true]),
                    $this->card('brands', 'Marcas', $allStates, 'brands', \App\Brand::class),
                    $this->card('product_categories', 'Categorías de productos', $allStates, 'product-categories', \App\ProductCategory::class),
                    $this->card('ingredient_categories', 'Categorías de ingredientes', $allStates, 'ingredient-categories', \App\IngredientCategory::class),
                    $this->card('supermarket_products', 'Publicaciones en supermercados', 'Publicaciones por cadena o sucursal; un producto puede tener varias. Incluye inactivas.', 'supermarket-products', \App\SupermarketProduct::class),
                    $this->card('prices', 'Precios en el historial', 'Registros de precios guardados; un producto puede tener varios precios históricos.', 'prices', \App\SupermarketProductPrice::class),
                    $this->card('barcodes', 'Códigos de barras', 'Códigos registrados, incluidos los inactivos; no es la cantidad de productos.', 'barcodes', \App\ProductBarcode::class),
                    $this->card('cities', 'Ciudades', $allStates, 'cities', \App\City::class),
                ]],
            ['key' => 'recipes', 'title' => 'Recetas',
                'description' => 'Recetas oficiales y registros de importación. Las recetas personales no forman parte del total de oficiales.',
                'cards' => [
                    $this->card('official_recipes', 'Recetas oficiales', 'Oficiales no eliminadas, en cualquier estado; excluye recetas personales y otras no oficiales.', 'official-recipes', \App\Recipe::class, ['is_official' => true]),
                    $this->card('imported_recipes', 'Importaciones de recetas', 'Historial de importaciones en todos sus estados; no equivale a recetas publicadas.', 'imported-recipes', \App\ImportedRecipeCandidate::class),
                    $this->card('recipe_categories', 'Categorías de recetas', $allStates, 'recipe-categories', \App\RecipeCategory::class),
                    $this->card('recipe_tags', 'Etiquetas de recetas', $allStates, 'recipe-tags', \App\RecipeTag::class),
                    $this->card('meal_types', 'Tipos de comida', 'Desayuno, almuerzo y demás tipos configurados, incluidos los inactivos.', 'meal-types', \App\MealType::class),
                ]],
            ['key' => 'pending', 'title' => 'Pendientes de atención',
                'description' => 'Registros que todavía requieren revisión o procesamiento. Cada tarjeta indica qué estados incluye.',
                'cards' => [
                    $this->card('products_pending_review', 'Productos por revisar', 'Candidatos pendientes, vinculados o creados que aún no fueron aprobados ni rechazados.', 'scraped-products', \App\ScrapedProductCandidate::class, ['review_status' => ['pending', 'matched', 'created']]),
                    $this->card('product_requests_pending', 'Solicitudes de productos pendientes', 'Solicitudes no eliminadas que todavía no fueron aprobadas ni rechazadas.', 'product-requests', \App\ProductRequest::class, ['status' => \App\ProductRequest::STATUS_PENDING]),
                    $this->card('product_reports_open', 'Reportes de productos abiertos', 'Reportes todavía no resueltos ni rechazados.', 'product-reports', \App\ProductReport::class, ['status' => \App\ProductReport::STATUS_OPEN]),
                    $this->card('price_refresh_pending', 'Actualizaciones de precios pendientes', 'Solicitudes que aún no se procesaron; excluye las ya encoladas o fallidas.', 'price-refresh-requests', \App\PriceRefreshRequest::class, ['status' => 'pending']),
                    $this->card('recipes_pending_completion', 'Importaciones de recetas por completar', 'Pendientes, analizadas o aprobadas que aún no se convirtieron en una receta. Excluye fallidas y rechazadas.', 'imported-recipes', \App\ImportedRecipeCandidate::class, ['status' => ['pending', 'parsed', 'approved']]),
                    $this->card('recipe_imports_failed', 'Importaciones de recetas con error', 'Importaciones cuyo procesamiento terminó con error.', 'imported-recipes', \App\ImportedRecipeCandidate::class, ['status' => 'failed']),
                ]],
            ['key' => 'scraping', 'title' => 'Actividad de scraping',
                'description' => 'Actividad registrada por módulo. Estos números no verifican que una tienda esté disponible ni ejecutan consultas externas.',
                'cards' => $this->scrapingCards()],
            ['key' => 'catalog-configuration', 'title' => 'Configuración del catálogo',
                'description' => 'Unidades, equivalencias, nutrición y tipos de preferencias que complementan el catálogo.',
                'cards' => [
                    $this->card('units', 'Unidades de medida', $allStates, 'units-conversions', \App\UnitMeasure::class),
                    $this->card('conversions', 'Conversiones de unidades', 'Conversiones generales y por ingrediente, incluidos registros inactivos.', 'units-conversions', \App\UnitConversion::class),
                    $this->card('equivalences', 'Equivalencias de ingredientes', 'Sustituciones registradas, incluidos registros inactivos.', 'equivalences', \App\IngredientEquivalence::class),
                    $this->card('nutrients', 'Nutrientes', $allStates, 'nutrients', \App\Nutrient::class),
                    $this->card('food_tags', 'Etiquetas alimentarias', $allStates, 'food-tags', \App\FoodTag::class),
                    $this->card('objectives', 'Objetivos del catálogo', 'Tipos de objetivos configurados; no cuenta objetivos personales de usuarios.', 'objectives', \App\Objective::class),
                    $this->card('allergies', 'Alergias del catálogo', 'Tipos de alergias configurados; no cuenta personas ni perfiles.', 'health-preferences', \App\Allergy::class),
                    $this->card('health_conditions', 'Condiciones de salud del catálogo', 'Tipos configurados; no cuenta personas ni perfiles.', 'health-preferences', \App\HealthCondition::class),
                    $this->card('dietary_restrictions', 'Restricciones alimentarias del catálogo', 'Tipos configurados; no cuenta personas ni perfiles.', 'health-preferences', \App\DietaryRestriction::class),
                ]],
            ['key' => 'administration', 'title' => 'Cuentas y roles',
                'description' => 'Resumen de administración disponible según tus permisos.',
                'cards' => [
                    $this->card('users', 'Cuentas registradas', 'Cuentas no eliminadas, activas e inactivas. No equivale a personas conectadas.', 'users', \App\User::class),
                    $this->card('roles', 'Roles vigentes', 'Roles actuales activos; excluye roles retirados.', 'roles-permissions', \App\Role::class, ['status' => 'active', 'code' => \App\Services\Auth\RolePolicy::CURRENT]),
                ]],
        ];
    }
}
