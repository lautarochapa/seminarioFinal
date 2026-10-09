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
            'metricLabels' => $this->metricLabels(),
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

    private function metricLabels(): array
    {
        return [
            'users' => 'Usuarios',
            'roles' => 'Roles',
            'permissions' => 'Permisos',
            'ingredients' => 'Ingredientes',
            'ingredient_categories' => 'Categorías de ingredientes',
            'nutrients' => 'Nutrientes',
            'objectives' => 'Objetivos',
            'allergies' => 'Alergias',
            'health_conditions' => 'Condiciones de salud',
            'dietary_restrictions' => 'Restricciones alimentarias',
            'units' => 'Unidades de medida',
            'conversions' => 'Conversiones de unidades',
            'products' => 'Productos registrados',
            'product_requests' => 'Solicitudes de productos',
            'product_categories' => 'Categorías de productos',
            'brands' => 'Marcas',
            'barcodes' => 'Códigos de barras',
            'supermarkets' => 'Cadenas de supermercados',
            'branches' => 'Sucursales',
            'supermarket_products' => 'Publicaciones en supermercados',
            'prices' => 'Registros de precios',
            'price_refresh_requests' => 'Solicitudes de actualización de precios',
            'scraping_jobs' => 'Ejecuciones de importación web',
            'scraped_products' => 'Candidatos de productos',
            'scraping_alerts' => 'Alertas de importación web',
            'scraping_errors' => 'Errores registrados',
            'recipes' => 'Recetas registradas',
            'imported_recipes' => 'Importaciones de recetas',
            'recipe_tags' => 'Etiquetas de recetas',
            'food_tags' => 'Etiquetas alimentarias',
            'audit_logs' => 'Registros de auditoría',
            'settings' => 'Configuraciones',
            'feature_flags' => 'Funciones configurables',
        ];
    }

    private function screens()
    {
        return [
            'dashboard' => ['title' => 'Resumen de administración', 'module' => 'Administración', 'description' => 'Cantidades del catálogo, pendientes y actividad de los módulos a los que tenés acceso.', 'primary' => '', 'secondary' => '', 'metrics' => [], 'panels' => []],
            'users' => ['title' => 'Usuarios', 'module' => 'Usuarios, roles', 'description' => 'Administración de cuentas de usuario.', 'primary' => 'Nuevo usuario', 'secondary' => 'Exportar', 'metrics' => ['users', 'roles'], 'panels' => ['Listado', 'Roles asignados', 'Estado', 'Actividad']],
            'roles-permissions' => ['title' => 'Roles y permisos', 'module' => 'Seguridad', 'description' => 'Gestión de permisos.', 'primary' => 'Nuevo rol', 'secondary' => 'Editar permisos', 'metrics' => ['roles', 'permissions'], 'panels' => ['Roles', 'Permisos', 'Matriz', 'Auditoría']],
            'objectives' => ['title' => 'Objetivos', 'module' => 'Perfil y salud', 'description' => 'Administración de objetivos configurables para usuarios.', 'primary' => 'Nuevo objetivo', 'secondary' => 'Ver catálogo', 'metrics' => ['objectives'], 'panels' => ['Catálogo', 'Estado', 'Descripciones', 'Uso']],
            'health-preferences' => ['title' => 'Restricciones, alergias y condiciones', 'module' => 'Perfil y salud', 'description' => 'Administración del catálogo de restricciones alimentarias, condiciones de salud y alergias.', 'primary' => 'Nuevo elemento', 'secondary' => 'Ver catálogos', 'metrics' => ['dietary_restrictions', 'health_conditions', 'allergies'], 'panels' => ['Restricciones', 'Condiciones', 'Alergias', 'Catálogos']],
            'ingredients' => ['title' => 'Ingredientes', 'module' => 'Catálogo', 'description' => 'Administración de ingredientes.', 'primary' => 'Nuevo ingrediente', 'secondary' => 'Importar', 'metrics' => ['ingredients', 'ingredient_categories'], 'panels' => ['Listado', 'Categoría', 'Nutrición', 'Etiquetas']],
            'ingredient-categories' => ['title' => 'Categorías de ingredientes', 'module' => 'Catálogo', 'description' => 'Administración de categorías y subcategorías.', 'primary' => 'Nueva categoría', 'secondary' => 'Reordenar', 'metrics' => ['ingredient_categories'], 'panels' => ['Árbol', 'Raíces', 'Subcategorías', 'Estado']],
            'nutrients' => ['title' => 'Nutrientes', 'module' => 'Nutrición', 'description' => 'Administración de nutrientes y valores nutricionales.', 'primary' => 'Nuevo nutriente', 'secondary' => 'Valores', 'metrics' => ['nutrients'], 'panels' => ['Nutrientes', 'Unidades', 'Valores por ingrediente', 'Valores por producto']],
            'units-conversions' => ['title' => 'Unidades y conversiones', 'module' => 'Catálogo', 'description' => 'Administración de unidades de medida.', 'primary' => 'Nueva unidad', 'secondary' => 'Nueva conversión', 'metrics' => ['units', 'conversions'], 'panels' => ['Unidades', 'Conversiones generales', 'Conversiones por ingrediente', 'Validaciones']],
            'equivalences' => ['title' => 'Equivalencias', 'module' => 'Catálogo', 'description' => 'Sustituciones.', 'primary' => 'Nueva equivalencia', 'secondary' => 'Revisar', 'metrics' => ['ingredients'], 'panels' => ['Sustituciones', 'Motivos', 'Factores', 'Estado']],
            'food-tags' => ['title' => 'Etiquetas alimentarias', 'module' => 'Catálogo', 'description' => 'Etiquetas de salud y dieta.', 'primary' => 'Nueva etiqueta', 'secondary' => 'Ver catálogo', 'metrics' => ['food_tags'], 'panels' => ['Etiquetas', 'Tipos', 'Catálogo', 'Estado'], 'permission' => 'catalog.manage'],
            'product-categories' => ['title' => 'Categorías de productos', 'module' => 'Productos', 'description' => 'Categorías comerciales jerárquicas.', 'primary' => 'Nueva categoría', 'secondary' => 'Ver árbol', 'metrics' => ['product_categories', 'products'], 'panels' => ['Categorías', 'Jerarquía', 'Catálogo', 'Estado'], 'permission' => 'catalog.manage'],
            'products' => ['title' => 'Productos', 'module' => 'Productos', 'description' => 'Administración de productos.', 'primary' => 'Nuevo producto', 'secondary' => 'Importar', 'metrics' => ['products', 'brands'], 'panels' => ['Listado', 'Marca', 'Categoría', 'Nutrición']],
            'product-requests' => ['title' => 'Solicitudes de productos', 'module' => 'Productos', 'description' => 'Revisión de productos solicitados por usuarios para el catálogo interno.', 'primary' => 'Ver pendientes', 'secondary' => 'Ver rechazadas', 'metrics' => ['product_requests', 'products'], 'panels' => ['Pendientes', 'Aprobadas', 'Rechazadas', 'Creación'], 'permission' => 'web.admin.product-requests'],
            'brands' => ['title' => 'Marcas', 'module' => 'Productos', 'description' => 'Administración de marcas.', 'primary' => 'Nueva marca', 'secondary' => 'Normalizar', 'metrics' => ['brands'], 'panels' => ['Listado', 'Normalizados', 'Productos', 'Estado']],
            'barcodes' => ['title' => 'Códigos de barras', 'module' => 'Productos', 'description' => 'Administración de códigos de barras.', 'primary' => 'Nuevo código', 'secondary' => 'Buscar duplicados', 'metrics' => ['barcodes', 'products'], 'panels' => ['Códigos', 'Productos', 'Duplicados', 'Estado']],
            'supermarkets' => ['title' => 'Supermercados', 'module' => 'Supermercados', 'description' => 'Administración de cadenas de supermercados.', 'primary' => 'Nueva cadena', 'secondary' => 'Editar', 'metrics' => ['supermarkets'], 'panels' => ['Cadenas', 'Sitios', 'Estado', 'Importación web']],
            'branches' => ['title' => 'Sucursales', 'module' => 'Supermercados, mapa', 'description' => 'Administración de sucursales y sus ubicaciones.', 'primary' => 'Nueva sucursal', 'secondary' => 'Ver mapa', 'metrics' => ['branches'], 'panels' => ['Sucursales', 'Dirección', 'Mapa', 'Entrega y retiro']],
            'supermarket-products' => ['title' => 'Productos por supermercado', 'module' => 'Supermercados', 'description' => 'Vinculación de productos internos con publicaciones por cadena o sucursal.', 'primary' => 'Nueva vinculación', 'secondary' => 'Comparar precios', 'metrics' => ['supermarket_products', 'prices'], 'panels' => ['Vinculaciones', 'Sucursales', 'Precios', 'Importación web'], 'permission' => 'catalog.manage'],
            'prices' => ['title' => 'Precios', 'module' => 'Supermercados', 'description' => 'Precios de productos en supermercados.', 'primary' => 'Cargar precio', 'secondary' => 'Historial', 'metrics' => ['prices', 'products'], 'panels' => ['Actuales', 'Historial', 'Validación']],
            'supermarket-scraping' => ['title' => 'Importación web de productos', 'module' => 'Importación web', 'description' => 'Importación web de productos y consulta de ejecuciones y registros.', 'primary' => 'Iniciar importación web', 'secondary' => 'Ver registros', 'metrics' => ['scraping_jobs', 'scraping_errors'], 'panels' => ['Ejecuciones', 'Registros', 'Parámetros', 'Resultados']],
            'scraped-products' => ['title' => 'Productos importados por revisar', 'module' => 'Importación web', 'description' => 'Revisión de productos importados y vinculación con el catálogo interno.', 'primary' => 'Validar selección', 'secondary' => 'Crear producto', 'metrics' => ['scraped_products'], 'panels' => ['Pendientes', 'Coincidencias', 'Crear producto', 'Descartar'], 'permission' => 'catalog.manage'],
            'price-refresh-requests' => ['title' => 'Solicitudes de actualización de precios', 'module' => 'Importación web', 'description' => 'Solicitudes de usuarios para revisar precios desactualizados o iniciar una importación web puntual.', 'primary' => 'Procesar pendientes', 'secondary' => 'Ver historial', 'metrics' => ['price_refresh_requests', 'scraping_jobs'], 'panels' => ['Pendientes', 'Procesadas', 'Productos', 'Importación web'], 'permission' => 'scraping.manage'],
            'official-recipes' => ['title' => 'Recetas oficiales', 'module' => 'Recetas', 'description' => 'Administración de recetas oficiales.', 'primary' => 'Nueva receta oficial', 'secondary' => 'Publicar', 'metrics' => ['recipes'], 'panels' => ['Oficiales', 'Ingredientes', 'Pasos', 'Costo/nutrición']],
            'imported-recipes' => ['title' => 'Recetas importadas pendientes', 'module' => 'Importación de recetas', 'description' => 'Revisión de recetas obtenidas mediante importación web.', 'primary' => 'Validar receta', 'secondary' => 'Crear oficial', 'metrics' => ['imported_recipes'], 'panels' => ['Pendientes', 'Análisis', 'Revisión', 'Creación'], 'permission' => 'recipes.manage'],
            'recipe-import' => ['title' => 'Importar recetas por URL', 'module' => 'Importación de recetas', 'description' => 'Importación de recetas desde una dirección web compatible.', 'primary' => 'Importar URL', 'secondary' => 'Ver importadas', 'metrics' => ['imported_recipes'], 'panels' => ['URL', 'Vista previa', 'Vinculación', 'Crear']],
            'recipe-import-text' => ['title' => 'Importar recetas por texto', 'module' => 'Importación de recetas', 'description' => 'Análisis de texto libre o datos JSON de una receta para importarla al catálogo.', 'primary' => 'Analizar texto', 'secondary' => 'Ver importadas', 'metrics' => ['imported_recipes'], 'panels' => ['Texto', 'Vista previa', 'Vinculación', 'Crear']],
            'recipe-scraping' => ['title' => 'Importación web de recetas', 'module' => 'Importación de recetas', 'description' => 'Importación web de recetas desde Cookpad.', 'primary' => 'Ejecutar Cookpad', 'secondary' => 'Ver ejecuciones', 'metrics' => ['scraping_jobs', 'imported_recipes'], 'panels' => ['Fuentes', 'Ejecuciones', 'Registros', 'Errores']],
            'meal-types' => ['title' => 'Tipos de comida', 'module' => 'Planificación', 'description' => 'Administración de desayuno, almuerzo, cena y colaciones.', 'primary' => 'Nuevo tipo', 'secondary' => 'Ver catálogo', 'metrics' => [], 'panels' => ['Tipos', 'Orden', 'Catálogo', 'Estado'], 'permission' => 'recipes.manage'],
            'recipe-tags' => ['title' => 'Etiquetas de recetas', 'module' => 'Recetas', 'description' => 'Administración de etiquetas de recetas.', 'primary' => 'Nueva etiqueta', 'secondary' => 'Agrupar', 'metrics' => ['recipe_tags', 'food_tags'], 'panels' => ['Etiquetas de recetas', 'Tipos', 'Uso', 'Estado']],
            'scraping-alerts' => ['title' => 'Alertas de importación web', 'module' => 'Importación web', 'description' => 'Resolver errores.', 'primary' => 'Resolver', 'secondary' => 'Asignar', 'metrics' => ['scraping_alerts', 'scraping_errors'], 'panels' => ['Alertas', 'Severidad', 'Resolución', 'Historial']],
            'admin-reports' => ['title' => 'Reportes de administración', 'module' => 'Reportes', 'description' => 'Métricas de uso y estado.', 'primary' => 'Exportar', 'secondary' => 'Actualizar', 'metrics' => ['users', 'products', 'recipes', 'scraping_jobs'], 'panels' => ['Uso', 'Catálogo', 'Importación web', 'Estado del sistema']],
            'audit' => ['title' => 'Auditoría', 'module' => 'Auditoría', 'description' => 'Historial de acciones.', 'primary' => 'Filtrar', 'secondary' => 'Exportar', 'metrics' => ['audit_logs'], 'panels' => ['Acciones', 'Usuarios', 'Entidades', 'Cambios']],
            'settings' => ['title' => 'Configuración', 'module' => 'Configuración', 'description' => 'Funciones y configuración general del sistema.', 'primary' => 'Nueva configuración', 'secondary' => 'Funciones', 'metrics' => ['settings', 'feature_flags'], 'panels' => ['Configuración', 'Funciones', 'Públicos', 'Sistema']],
            'feature-flags' => ['title' => 'Funciones', 'module' => 'Configuración', 'description' => 'Configuración de las funciones del sistema.', 'primary' => 'Actualizar', 'secondary' => 'Ver estado', 'metrics' => ['feature_flags'], 'panels' => ['Funciones', 'Módulos', 'Autenticación', 'Estado']],
            'ai-foundation' => ['title' => 'Base de inteligencia artificial', 'module' => 'IA', 'description' => 'Base para integrar inteligencia artificial. La versión actual usa un proveedor simulado.', 'primary' => 'Probar proveedor', 'secondary' => 'Ver función', 'metrics' => ['feature_flags'], 'panels' => ['Estado', 'Proveedor', 'Prueba', 'Arquitectura']],
            'thesis-docs' => ['title' => 'Documentacion tesis', 'module' => 'Docs', 'description' => 'Editor documentacion.', 'primary' => 'Nuevo documento', 'secondary' => 'Versionar', 'metrics' => ['thesis_documents'], 'panels' => ['Documentos', 'Secciones', 'Versiones', 'Comentarios']],
            'demo-scenarios' => ['title' => 'Escenarios demo', 'module' => 'Docs/demo', 'description' => 'ABM demos para docente.', 'primary' => 'Nuevo escenario', 'secondary' => 'Probar ruta', 'metrics' => ['demo_scenarios'], 'panels' => ['Escenarios', 'Usuario demo', 'Rutas', 'Estado']],
            'product-reports' => ['title' => 'Reportes de productos', 'module' => 'Productos', 'description' => 'Revisión y resolución de reportes enviados por usuarios sobre errores en productos y precios.', 'primary' => 'Ver pendientes', 'secondary' => 'Ver resueltos', 'metrics' => ['products'], 'panels' => ['Pendientes', 'Resueltos', 'Filtros', 'Detalle']],
            'cities' => ['title' => 'Ciudades', 'module' => 'Geodatos', 'description' => 'Administración de ciudades habilitadas en la plataforma.', 'primary' => 'Nueva ciudad', 'secondary' => 'Ver activas', 'metrics' => [], 'panels' => ['Listado', 'Crear', 'Editar', 'Estado']],
            'recipe-categories' => ['title' => 'Categorías de recetas', 'module' => 'Recetas', 'description' => 'Administración de categorías y subcategorías de recetas.', 'primary' => 'Nueva categoría', 'secondary' => 'Ver árbol', 'metrics' => [], 'panels' => ['Categorías', 'Jerarquía', 'Estado', 'Árbol']],
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
