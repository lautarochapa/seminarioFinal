<?php

namespace App\Http\Controllers;

use App\AuditLog;
use App\Brand;
use App\FeatureFlag;
use App\FoodTag;
use App\ImportedRecipeCandidate;
use App\Ingredient;
use App\IngredientCategory;
use App\Nutrient;
use App\Objective;
use App\Permission;
use App\PriceRefreshRequest;
use App\Product;
use App\ProductBarcode;
use App\ProductCategory;
use App\ProductRequest;
use App\Recipe;
use App\RecipeTag;
use App\Role;
use App\ScrapedProductCandidate;
use App\ScrapingAlert;
use App\ScrapingError;
use App\ScrapingJob;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\SystemSetting;
use App\ThesisDocument;
use App\DemoScenario;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;

class AdminWebScreenController extends Controller
{
    public function index($screen = 'dashboard')
    {
        $screens = $this->screens();
        unset($screens['thesis-docs'], $screens['demo-scenarios']);

        if (! isset($screens[$screen])) {
            abort(404);
        }

        if (! $this->canAccessScreen($screen)) {
            abort(403);
        }

        return view('web.admin-screen', [
            'screenKey' => $screen,
            'screen' => $screens[$screen],
            'screens' => $screens,
            'stats' => $screen === 'dashboard' ? [] : $this->stats($screens[$screen]['metrics']),
            'dashboard' => $screen === 'dashboard'
                ? app(\App\Services\Admin\AdminDashboardService::class)->build(request()->user(), $screens)
                : ['sections' => []],
        ]);
    }

    public function dashboard()
    {
        return $this->index('dashboard');
    }

    private function stats(array $keys)
    {
        $models = [
            'users' => User::class,
            'roles' => Role::class,
            'permissions' => Permission::class,
            'ingredients' => Ingredient::class,
            'ingredient_categories' => IngredientCategory::class,
            'nutrients' => Nutrient::class,
            'objectives' => Objective::class,
            'allergies' => \App\Allergy::class,
            'health_conditions' => \App\HealthCondition::class,
            'dietary_restrictions' => \App\DietaryRestriction::class,
            'units' => UnitMeasure::class,
            'conversions' => UnitConversion::class,
            'products' => Product::class,
            'product_requests' => ProductRequest::class,
            'product_categories' => ProductCategory::class,
            'brands' => Brand::class,
            'barcodes' => ProductBarcode::class,
            'supermarkets' => SupermarketChain::class,
            'branches' => SupermarketBranch::class,
            'supermarket_products' => SupermarketProduct::class,
            'prices' => SupermarketProductPrice::class,
            'price_refresh_requests' => PriceRefreshRequest::class,
            'scraping_jobs' => ScrapingJob::class,
            'scraped_products' => ScrapedProductCandidate::class,
            'scraping_alerts' => ScrapingAlert::class,
            'scraping_errors' => ScrapingError::class,
            'recipes' => Recipe::class,
            'imported_recipes' => ImportedRecipeCandidate::class,
            'recipe_tags' => RecipeTag::class,
            'food_tags' => FoodTag::class,
            'audit_logs' => AuditLog::class,
            'settings' => SystemSetting::class,
            'feature_flags' => FeatureFlag::class,
            'thesis_documents' => ThesisDocument::class,
            'demo_scenarios' => DemoScenario::class,
        ];
        $stats = [];
        foreach (array_unique($keys) as $key) {
            if (isset($models[$key])) {
                $model = $models[$key];
                $stats[$key] = $model::count();
            }
        }
        return $stats;
    }

    private function screens()
    {
        return [
            'dashboard' => ['title' => 'Resumen de administración', 'module' => 'Administración', 'description' => 'Cantidades del catálogo, pendientes y actividad de los módulos a los que tenés acceso.', 'primary' => '', 'secondary' => '', 'metrics' => [], 'panels' => []],
            'users' => ['title' => 'Usuarios', 'module' => 'Usuarios, roles', 'description' => 'ABM usuarios.', 'primary' => 'Nuevo usuario', 'secondary' => 'Exportar', 'metrics' => ['users', 'roles'], 'panels' => ['Listado', 'Roles asignados', 'Estado', 'Actividad']],
            'roles-permissions' => ['title' => 'Roles y permisos', 'module' => 'Seguridad', 'description' => 'Gestion de permisos.', 'primary' => 'Nuevo rol', 'secondary' => 'Editar permisos', 'metrics' => ['roles', 'permissions'], 'panels' => ['Roles', 'Permisos', 'Matriz', 'Auditoria']],
            'objectives' => ['title' => 'Objetivos', 'module' => 'Perfil y salud', 'description' => 'ABM de objetivos configurables para usuarios.', 'primary' => 'Nuevo objetivo', 'secondary' => 'Ver catalogo', 'metrics' => ['objectives'], 'panels' => ['Catalogo', 'Estado', 'Descripciones', 'Uso']],
            'health-preferences' => ['title' => 'Restricciones, alergias y condiciones', 'module' => 'Perfil y salud', 'description' => 'ABM del catalogo de restricciones alimentarias, condiciones de salud y alergias.', 'primary' => 'Nuevo item', 'secondary' => 'Ver catalogos', 'metrics' => ['dietary_restrictions', 'health_conditions', 'allergies'], 'panels' => ['Restricciones', 'Condiciones', 'Alergias', 'Catalogos']],
            'ingredients' => ['title' => 'Ingredientes', 'module' => 'Catalogo', 'description' => 'ABM ingredientes.', 'primary' => 'Nuevo ingrediente', 'secondary' => 'Importar', 'metrics' => ['ingredients', 'ingredient_categories'], 'panels' => ['Listado', 'Categoria', 'Nutricion', 'Tags']],
            'ingredient-categories' => ['title' => 'Categorias ingredientes', 'module' => 'Catalogo', 'description' => 'ABM jerarquico.', 'primary' => 'Nueva categoria', 'secondary' => 'Reordenar', 'metrics' => ['ingredient_categories'], 'panels' => ['Arbol', 'Raices', 'Subcategorias', 'Estado']],
            'nutrients' => ['title' => 'Nutrientes', 'module' => 'Nutricion', 'description' => 'ABM nutrientes y valores.', 'primary' => 'Nuevo nutriente', 'secondary' => 'Valores', 'metrics' => ['nutrients'], 'panels' => ['Nutrientes', 'Unidades', 'Valores por ingrediente', 'Valores por producto']],
            'units-conversions' => ['title' => 'Unidades y conversiones', 'module' => 'Catalogo', 'description' => 'ABM unidades.', 'primary' => 'Nueva unidad', 'secondary' => 'Nueva conversion', 'metrics' => ['units', 'conversions'], 'panels' => ['Unidades', 'Conversiones generales', 'Conversiones por ingrediente', 'Validaciones']],
            'equivalences' => ['title' => 'Equivalencias', 'module' => 'Catalogo', 'description' => 'Sustituciones.', 'primary' => 'Nueva equivalencia', 'secondary' => 'Revisar', 'metrics' => ['ingredients'], 'panels' => ['Sustituciones', 'Motivos', 'Factores', 'Estado']],
            'food-tags' => ['title' => 'Tags alimentarios', 'module' => 'Catalogo', 'description' => 'Etiquetas de salud y dieta.', 'primary' => 'Nuevo tag', 'secondary' => 'Ver catalogo', 'metrics' => ['food_tags'], 'panels' => ['Tags', 'Tipos', 'Catalogo', 'Estado'], 'permission' => 'catalog.manage'],
            'product-categories' => ['title' => 'Categorias productos', 'module' => 'Productos', 'description' => 'Categorias comerciales jerarquicas.', 'primary' => 'Nueva categoria', 'secondary' => 'Ver arbol', 'metrics' => ['product_categories', 'products'], 'panels' => ['Categorias', 'Jerarquia', 'Catalogo', 'Estado'], 'permission' => 'catalog.manage'],
            'products' => ['title' => 'Productos', 'module' => 'Productos', 'description' => 'ABM productos.', 'primary' => 'Nuevo producto', 'secondary' => 'Importar', 'metrics' => ['products', 'brands'], 'panels' => ['Listado', 'Marca', 'Categoria', 'Nutricion']],
            'product-requests' => ['title' => 'Solicitudes de productos', 'module' => 'Productos', 'description' => 'Revision de productos solicitados por usuarios para el catalogo interno.', 'primary' => 'Ver pendientes', 'secondary' => 'Ver rechazadas', 'metrics' => ['product_requests', 'products'], 'panels' => ['Pendientes', 'Aprobadas', 'Rechazadas', 'Creacion'], 'permission' => 'web.admin.product-requests'],
            'brands' => ['title' => 'Marcas', 'module' => 'Productos', 'description' => 'ABM marcas.', 'primary' => 'Nueva marca', 'secondary' => 'Normalizar', 'metrics' => ['brands'], 'panels' => ['Listado', 'Normalizados', 'Productos', 'Estado']],
            'barcodes' => ['title' => 'Codigos de barra', 'module' => 'Productos', 'description' => 'Gestion barcodes.', 'primary' => 'Nuevo codigo', 'secondary' => 'Buscar duplicados', 'metrics' => ['barcodes', 'products'], 'panels' => ['Codigos', 'Productos', 'Duplicados', 'Estado']],
            'supermarkets' => ['title' => 'Supermercados', 'module' => 'Supermercados', 'description' => 'ABM cadenas.', 'primary' => 'Nueva cadena', 'secondary' => 'Editar', 'metrics' => ['supermarkets'], 'panels' => ['Cadenas', 'Sitios', 'Estado', 'Scraping']],
            'branches' => ['title' => 'Sucursales', 'module' => 'Supermercados, mapa', 'description' => 'ABM sucursales Bariloche.', 'primary' => 'Nueva sucursal', 'secondary' => 'Ver mapa', 'metrics' => ['branches'], 'panels' => ['Sucursales', 'Direccion', 'Mapa', 'Delivery/Pickup']],
            'supermarket-products' => ['title' => 'Productos por supermercado', 'module' => 'Supermercados', 'description' => 'Mapeo entre productos internos y publicaciones por sucursal.', 'primary' => 'Nuevo mapeo', 'secondary' => 'Comparar precios', 'metrics' => ['supermarket_products', 'prices'], 'panels' => ['Mapeos', 'Sucursales', 'Precios', 'Scraping'], 'permission' => 'catalog.manage'],
            'prices' => ['title' => 'Precios', 'module' => 'Supermercados', 'description' => 'Precios por producto/super.', 'primary' => 'Cargar precio', 'secondary' => 'Historial', 'metrics' => ['prices', 'products'], 'panels' => ['Actuales', 'Historial', 'Validacion']],
            'supermarket-scraping' => ['title' => 'Scraping supermercados', 'module' => 'Scraping', 'description' => 'Ejecutar scraping, ver jobs y logs.', 'primary' => 'Ejecutar scraping', 'secondary' => 'Ver logs', 'metrics' => ['scraping_jobs', 'scraping_errors'], 'panels' => ['Jobs', 'Logs', 'Parametros', 'Resultados']],
            'scraped-products' => ['title' => 'Productos scrapeados pendientes', 'module' => 'Scraping', 'description' => 'Validar, mapear, crear productos.', 'primary' => 'Validar seleccion', 'secondary' => 'Crear producto', 'metrics' => ['scraped_products'], 'panels' => ['Pendientes', 'Matches', 'Crear producto', 'Descartar'], 'permission' => 'catalog.manage'],
            'price-refresh-requests' => ['title' => 'Solicitudes de refresh de precio', 'module' => 'Scraping', 'description' => 'Usuarios reportan precios desactualizados para revisar o disparar scraping puntual.', 'primary' => 'Procesar pendientes', 'secondary' => 'Ver historial', 'metrics' => ['price_refresh_requests', 'scraping_jobs'], 'panels' => ['Pendientes', 'Procesadas', 'Productos', 'Scraping'], 'permission' => 'scraping.manage'],
            'official-recipes' => ['title' => 'Recetas oficiales', 'module' => 'Recetas', 'description' => 'ABM recetas oficiales.', 'primary' => 'Nueva receta oficial', 'secondary' => 'Publicar', 'metrics' => ['recipes'], 'panels' => ['Oficiales', 'Ingredientes', 'Pasos', 'Costo/nutricion']],
            'imported-recipes' => ['title' => 'Recetas importadas pendientes', 'module' => 'Importacion recetas', 'description' => 'Validar recetas scrapeadas.', 'primary' => 'Validar receta', 'secondary' => 'Crear oficial', 'metrics' => ['imported_recipes'], 'panels' => ['Pendientes', 'Parseo', 'Revision', 'Creacion'], 'permission' => 'recipes.manage'],
            'recipe-import' => ['title' => 'Importar recetas por URL', 'module' => 'Importacion recetas', 'description' => 'Importar recetas a partir de una URL usando scraping puntual o manual.', 'primary' => 'Importar URL', 'secondary' => 'Ver importadas', 'metrics' => ['imported_recipes'], 'panels' => ['URL', 'Preview', 'Mapeo', 'Crear']],
            'recipe-import-text' => ['title' => 'Importar recetas por texto', 'module' => 'Importacion recetas', 'description' => 'Pegar texto libre o JSON de una receta para parsear e importar al catalogo.', 'primary' => 'Parsear texto', 'secondary' => 'Ver importadas', 'metrics' => ['imported_recipes'], 'panels' => ['Texto', 'Preview', 'Mapeo', 'Crear']],
            'recipe-scraping' => ['title' => 'Scraping recetas', 'module' => 'Importacion recetas', 'description' => 'Ejecutar scraping Cookpad.', 'primary' => 'Ejecutar Cookpad', 'secondary' => 'Ver jobs', 'metrics' => ['scraping_jobs', 'imported_recipes'], 'panels' => ['Fuentes', 'Jobs', 'Logs', 'Errores']],
            'meal-types' => ['title' => 'Tipos de comida', 'module' => 'Planificacion', 'description' => 'ABM desayuno, almuerzo, cena y colaciones.', 'primary' => 'Nuevo tipo', 'secondary' => 'Ver catalogo', 'metrics' => [], 'panels' => ['Tipos', 'Orden', 'Catalogo', 'Estado'], 'permission' => 'recipes.manage'],
            'recipe-tags' => ['title' => 'Tags recetas', 'module' => 'Recetas', 'description' => 'ABM tags.', 'primary' => 'Nuevo tag', 'secondary' => 'Agrupar', 'metrics' => ['recipe_tags', 'food_tags'], 'panels' => ['Tags receta', 'Tipos', 'Uso', 'Estado']],
            'scraping-alerts' => ['title' => 'Alertas scraping', 'module' => 'Scraping', 'description' => 'Resolver errores.', 'primary' => 'Resolver', 'secondary' => 'Asignar', 'metrics' => ['scraping_alerts', 'scraping_errors'], 'panels' => ['Alertas', 'Severidad', 'Resolucion', 'Historial']],
            'admin-reports' => ['title' => 'Reportes admin', 'module' => 'Reportes', 'description' => 'Metricas de uso y estado.', 'primary' => 'Exportar', 'secondary' => 'Actualizar', 'metrics' => ['users', 'products', 'recipes', 'scraping_jobs'], 'panels' => ['Uso', 'Catalogo', 'Scraping', 'Salud sistema']],
            'audit' => ['title' => 'Auditoria', 'module' => 'Auditoria', 'description' => 'Historial de acciones.', 'primary' => 'Filtrar', 'secondary' => 'Exportar', 'metrics' => ['audit_logs'], 'panels' => ['Acciones', 'Usuarios', 'Entidades', 'Cambios']],
            'settings' => ['title' => 'Configuracion', 'module' => 'Settings', 'description' => 'Feature flags y configuracion general.', 'primary' => 'Nuevo setting', 'secondary' => 'Feature flags', 'metrics' => ['settings', 'feature_flags'], 'panels' => ['Settings', 'Feature flags', 'Publicos', 'Sistema']],
            'feature-flags' => ['title' => 'Feature flags', 'module' => 'Settings', 'description' => 'Activar o desactivar modulos del sistema.', 'primary' => 'Actualizar', 'secondary' => 'Ver estado', 'metrics' => ['feature_flags'], 'panels' => ['Flags', 'Modulos', 'Auth', 'Estado']],
            'ai-foundation' => ['title' => 'Base IA', 'module' => 'IA', 'description' => 'Arquitectura preparada para integracion de IA. MVP usa provider simulado.', 'primary' => 'Test provider', 'secondary' => 'Ver flag', 'metrics' => ['feature_flags'], 'panels' => ['Estado', 'Provider', 'Test', 'Arquitectura']],
            'thesis-docs' => ['title' => 'Documentacion tesis', 'module' => 'Docs', 'description' => 'Editor documentacion.', 'primary' => 'Nuevo documento', 'secondary' => 'Versionar', 'metrics' => ['thesis_documents'], 'panels' => ['Documentos', 'Secciones', 'Versiones', 'Comentarios']],
            'demo-scenarios' => ['title' => 'Escenarios demo', 'module' => 'Docs/demo', 'description' => 'ABM demos para docente.', 'primary' => 'Nuevo escenario', 'secondary' => 'Probar ruta', 'metrics' => ['demo_scenarios'], 'panels' => ['Escenarios', 'Usuario demo', 'Rutas', 'Estado']],
            'product-reports' => ['title' => 'Reportes de productos', 'module' => 'Productos', 'description' => 'Revisión y resolución de reportes enviados por usuarios sobre errores en productos y precios.', 'primary' => 'Ver pendientes', 'secondary' => 'Ver resueltos', 'metrics' => ['products'], 'panels' => ['Pendientes', 'Resueltos', 'Filtros', 'Detalle']],
            'cities' => ['title' => 'Ciudades', 'module' => 'Geodatos', 'description' => 'ABM de ciudades habilitadas en la plataforma.', 'primary' => 'Nueva ciudad', 'secondary' => 'Ver activas', 'metrics' => [], 'panels' => ['Listado', 'Crear', 'Editar', 'Estado']],
            'recipe-categories' => ['title' => 'Categorias de recetas', 'module' => 'Recetas', 'description' => 'ABM categorias jerarquicas de recetas (desayuno, almuerzo, cena, saludable, etc.).', 'primary' => 'Nueva categoria', 'secondary' => 'Ver arbol', 'metrics' => [], 'panels' => ['Categorias', 'Jerarquia', 'Estado', 'Arbol']],
        ];
    }

    private function canAccessScreen($screen)
    {
        $user = request()->user();
        $screens = $this->screens();
        $permission = $screens[$screen]['permission'] ?? 'web.admin.' . $screen;

        return $user && $user->hasPermission($permission);
    }
}
