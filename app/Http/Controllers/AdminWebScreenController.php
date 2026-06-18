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
use App\PaymentMethod;
use App\Permission;
use App\Product;
use App\ProductBarcode;
use App\ProductCategory;
use App\Promotion;
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
            'stats' => $this->stats(),
        ]);
    }

    public function dashboard()
    {
        return $this->index('dashboard');
    }

    private function stats()
    {
        return [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'ingredients' => Ingredient::count(),
            'ingredient_categories' => IngredientCategory::count(),
            'nutrients' => Nutrient::count(),
            'objectives' => Objective::count(),
            'allergies' => \App\Allergy::count(),
            'health_conditions' => \App\HealthCondition::count(),
            'dietary_restrictions' => \App\DietaryRestriction::count(),
            'units' => UnitMeasure::count(),
            'conversions' => UnitConversion::count(),
            'products' => Product::count(),
            'product_categories' => ProductCategory::count(),
            'brands' => Brand::count(),
            'barcodes' => ProductBarcode::count(),
            'supermarkets' => SupermarketChain::count(),
            'branches' => SupermarketBranch::count(),
            'supermarket_products' => SupermarketProduct::count(),
            'prices' => SupermarketProductPrice::count(),
            'promotions' => Promotion::count(),
            'payment_methods' => PaymentMethod::count(),
            'scraping_jobs' => ScrapingJob::count(),
            'scraped_products' => ScrapedProductCandidate::count(),
            'scraping_alerts' => ScrapingAlert::count(),
            'scraping_errors' => ScrapingError::count(),
            'recipes' => Recipe::count(),
            'imported_recipes' => ImportedRecipeCandidate::count(),
            'recipe_tags' => RecipeTag::count(),
            'food_tags' => FoodTag::count(),
            'audit_logs' => AuditLog::count(),
            'settings' => SystemSetting::count(),
            'feature_flags' => FeatureFlag::count(),
            'thesis_documents' => ThesisDocument::count(),
            'demo_scenarios' => DemoScenario::count(),
        ];
    }

    private function screens()
    {
        return [
            'dashboard' => ['title' => 'Dashboard admin', 'module' => 'Reportes admin', 'description' => 'Pendientes, scraping, validaciones, errores.', 'primary' => 'Ver pendientes', 'secondary' => 'Revisar errores', 'metrics' => ['scraping_jobs', 'scraped_products', 'scraping_alerts', 'scraping_errors'], 'panels' => ['Pendientes', 'Scraping', 'Validaciones', 'Errores']],
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
            'brands' => ['title' => 'Marcas', 'module' => 'Productos', 'description' => 'ABM marcas.', 'primary' => 'Nueva marca', 'secondary' => 'Normalizar', 'metrics' => ['brands'], 'panels' => ['Listado', 'Normalizados', 'Productos', 'Estado']],
            'barcodes' => ['title' => 'Codigos de barra', 'module' => 'Productos', 'description' => 'Gestion barcodes.', 'primary' => 'Nuevo codigo', 'secondary' => 'Buscar duplicados', 'metrics' => ['barcodes', 'products'], 'panels' => ['Codigos', 'Productos', 'Duplicados', 'Estado']],
            'supermarkets' => ['title' => 'Supermercados', 'module' => 'Supermercados', 'description' => 'ABM cadenas.', 'primary' => 'Nueva cadena', 'secondary' => 'Editar', 'metrics' => ['supermarkets'], 'panels' => ['Cadenas', 'Sitios', 'Estado', 'Scraping']],
            'branches' => ['title' => 'Sucursales', 'module' => 'Supermercados, mapa', 'description' => 'ABM sucursales Bariloche.', 'primary' => 'Nueva sucursal', 'secondary' => 'Ver mapa', 'metrics' => ['branches'], 'panels' => ['Sucursales', 'Direccion', 'Mapa', 'Delivery/Pickup']],
            'supermarket-products' => ['title' => 'Productos por supermercado', 'module' => 'Supermercados', 'description' => 'Mapeo entre productos internos y publicaciones por sucursal.', 'primary' => 'Nuevo mapeo', 'secondary' => 'Comparar precios', 'metrics' => ['supermarket_products', 'prices'], 'panels' => ['Mapeos', 'Sucursales', 'Precios', 'Scraping'], 'permission' => 'catalog.manage'],
            'prices' => ['title' => 'Precios', 'module' => 'Supermercados', 'description' => 'Precios por producto/super.', 'primary' => 'Cargar precio', 'secondary' => 'Historial', 'metrics' => ['prices', 'products'], 'panels' => ['Actuales', 'Historial', 'Promociones', 'Validacion']],
            'promotions' => ['title' => 'Promociones', 'module' => 'Supermercados', 'description' => 'Descuentos y metodos de pago.', 'primary' => 'Nueva promocion', 'secondary' => 'Metodos de pago', 'metrics' => ['promotions'], 'panels' => ['Promociones', 'Vigencia', 'Pago', 'Sucursales']],
            'payment-methods' => ['title' => 'Metodos de pago', 'module' => 'Supermercados', 'description' => 'Catalogo de tarjetas, billeteras, efectivo y otros medios usados por promociones.', 'primary' => 'Nuevo metodo', 'secondary' => 'Ver usuarios', 'metrics' => ['payment_methods'], 'panels' => ['Catalogo', 'Tipos', 'Emisores', 'Usuarios'], 'permission' => 'catalog.manage'],
            'supermarket-scraping' => ['title' => 'Scraping supermercados', 'module' => 'Scraping', 'description' => 'Ejecutar scraping, ver jobs y logs.', 'primary' => 'Ejecutar scraping', 'secondary' => 'Ver logs', 'metrics' => ['scraping_jobs', 'scraping_errors'], 'panels' => ['Jobs', 'Logs', 'Parametros', 'Resultados']],
            'scraped-products' => ['title' => 'Productos scrapeados pendientes', 'module' => 'Scraping', 'description' => 'Validar, mapear, crear productos.', 'primary' => 'Validar seleccion', 'secondary' => 'Crear producto', 'metrics' => ['scraped_products'], 'panels' => ['Pendientes', 'Matches', 'Crear producto', 'Descartar']],
            'official-recipes' => ['title' => 'Recetas oficiales', 'module' => 'Recetas', 'description' => 'ABM recetas oficiales.', 'primary' => 'Nueva receta oficial', 'secondary' => 'Publicar', 'metrics' => ['recipes'], 'panels' => ['Oficiales', 'Ingredientes', 'Pasos', 'Costo/nutricion']],
            'imported-recipes' => ['title' => 'Recetas importadas pendientes', 'module' => 'Importacion recetas', 'description' => 'Validar recetas scrapeadas.', 'primary' => 'Validar receta', 'secondary' => 'Crear oficial', 'metrics' => ['imported_recipes'], 'panels' => ['Pendientes', 'Parseo', 'Revision', 'Creacion']],
            'recipe-scraping' => ['title' => 'Scraping recetas', 'module' => 'Importacion recetas', 'description' => 'Ejecutar scraping Cookpad.', 'primary' => 'Ejecutar Cookpad', 'secondary' => 'Ver jobs', 'metrics' => ['scraping_jobs', 'imported_recipes'], 'panels' => ['Fuentes', 'Jobs', 'Logs', 'Errores']],
            'recipe-tags' => ['title' => 'Tags recetas', 'module' => 'Recetas', 'description' => 'ABM tags.', 'primary' => 'Nuevo tag', 'secondary' => 'Agrupar', 'metrics' => ['recipe_tags', 'food_tags'], 'panels' => ['Tags receta', 'Tipos', 'Uso', 'Estado']],
            'scraping-alerts' => ['title' => 'Alertas scraping', 'module' => 'Scraping', 'description' => 'Resolver errores.', 'primary' => 'Resolver', 'secondary' => 'Asignar', 'metrics' => ['scraping_alerts', 'scraping_errors'], 'panels' => ['Alertas', 'Severidad', 'Resolucion', 'Historial']],
            'admin-reports' => ['title' => 'Reportes admin', 'module' => 'Reportes', 'description' => 'Metricas de uso y estado.', 'primary' => 'Exportar', 'secondary' => 'Actualizar', 'metrics' => ['users', 'products', 'recipes', 'scraping_jobs'], 'panels' => ['Uso', 'Catalogo', 'Scraping', 'Salud sistema']],
            'audit' => ['title' => 'Auditoria', 'module' => 'Auditoria', 'description' => 'Historial de acciones.', 'primary' => 'Filtrar', 'secondary' => 'Exportar', 'metrics' => ['audit_logs'], 'panels' => ['Acciones', 'Usuarios', 'Entidades', 'Cambios']],
            'settings' => ['title' => 'Configuracion', 'module' => 'Settings', 'description' => 'Feature flags y configuracion general.', 'primary' => 'Nuevo setting', 'secondary' => 'Feature flags', 'metrics' => ['settings', 'feature_flags'], 'panels' => ['Settings', 'Feature flags', 'Publicos', 'Sistema']],
            'thesis-docs' => ['title' => 'Documentacion tesis', 'module' => 'Docs', 'description' => 'Editor documentacion.', 'primary' => 'Nuevo documento', 'secondary' => 'Versionar', 'metrics' => ['thesis_documents'], 'panels' => ['Documentos', 'Secciones', 'Versiones', 'Comentarios']],
            'demo-scenarios' => ['title' => 'Escenarios demo', 'module' => 'Docs/demo', 'description' => 'ABM demos para docente.', 'primary' => 'Nuevo escenario', 'secondary' => 'Probar ruta', 'metrics' => ['demo_scenarios'], 'panels' => ['Escenarios', 'Usuario demo', 'Rutas', 'Estado']],
            'product-reports' => ['title' => 'Reportes de productos', 'module' => 'Productos', 'description' => 'Revisión y resolución de reportes enviados por usuarios sobre errores en productos y precios.', 'primary' => 'Ver pendientes', 'secondary' => 'Ver resueltos', 'metrics' => ['products'], 'panels' => ['Pendientes', 'Resueltos', 'Filtros', 'Detalle']],
            'cities' => ['title' => 'Ciudades', 'module' => 'Geodatos', 'description' => 'ABM de ciudades habilitadas en la plataforma.', 'primary' => 'Nueva ciudad', 'secondary' => 'Ver activas', 'metrics' => [], 'panels' => ['Listado', 'Crear', 'Editar', 'Estado']],
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
