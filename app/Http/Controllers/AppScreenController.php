<?php

namespace App\Http\Controllers;

use App\Allergy;
use App\Budget;
use App\FamilyGroup;
use App\FoodTag;
use App\HealthCondition;
use App\IngredientCategory;
use App\MealPlan;
use App\Notification;
use App\Product;
use App\Purchase;
use App\Recipe;
use App\ShoppingList;
use App\StockAlert;
use App\StockItem;
use App\UserSupplement;
use Illuminate\Support\Facades\Auth;

class AppScreenController extends Controller
{
    public function index($screen = 'dashboard')
    {
        $screens = $this->screens();

        if (! isset($screens[$screen])) {
            abort(404);
        }

        return view('app.screen', [
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
        $userId = Auth::id();

        return [
            'family_groups' => FamilyGroup::count(),
            'stock_items' => StockItem::count(),
            'expiring_items' => StockItem::whereNotNull('expiration_date')->where('expiration_date', '<=', now()->addDays(7)->toDateString())->count(),
            'stock_alerts' => StockAlert::where('status', '!=', 'resolved')->count(),
            'recipes' => Recipe::count(),
            'meal_plans' => MealPlan::count(),
            'shopping_lists' => ShoppingList::count(),
            'purchases' => Purchase::count(),
            'budgets' => Budget::count(),
            'notifications' => Notification::when($userId, function ($query) use ($userId) {
                return $query->where('user_id', $userId);
            })->count(),
            'supplements' => UserSupplement::when($userId, function ($query) use ($userId) {
                return $query->where('user_id', $userId);
            })->count(),
            'products' => Product::count(),
            'ingredient_categories' => IngredientCategory::count(),
            'food_tags' => FoodTag::count(),
            'allergies' => Allergy::count(),
            'health_conditions' => HealthCondition::count(),
        ];
    }

    private function screens()
    {
        return [
            'auth' => [
                'title' => 'Login / registro',
                'module' => 'Auth',
                'description' => 'Acceso por email o Google.',
                'primary' => 'Entrar con email',
                'secondary' => 'Crear cuenta',
                'metrics' => ['products', 'recipes'],
                'sections' => ['Email y password', 'Acceso con Google', 'Recuperacion de cuenta'],
            ],
            'onboarding' => [
                'title' => 'Onboarding',
                'module' => 'Perfil, objetivos, restricciones',
                'description' => 'Carga inicial de datos, objetivos, alergias, condiciones y preferencias.',
                'primary' => 'Continuar carga',
                'secondary' => 'Saltar por ahora',
                'metrics' => ['allergies', 'health_conditions', 'food_tags'],
                'sections' => ['Datos personales', 'Objetivos', 'Alergias', 'Condiciones', 'Prioridades'],
            ],
            'dashboard' => [
                'title' => 'Dashboard mobile',
                'module' => 'Stock, recetas, planificacion, presupuesto',
                'description' => 'Resumen diario: comida de hoy, productos por vencer, presupuesto, sugerencias.',
                'primary' => 'Generar sugerencia',
                'secondary' => 'Ver planificador',
                'metrics' => ['expiring_items', 'stock_alerts', 'shopping_lists', 'budgets'],
                'sections' => ['Comida de hoy', 'Productos por vencer', 'Presupuesto', 'Sugerencias'],
            ],
            'family-group' => [
                'title' => 'Grupo familiar',
                'module' => 'Grupo familiar',
                'description' => 'Ver grupo, miembros y permisos.',
                'primary' => 'Invitar miembro',
                'secondary' => 'Editar permisos',
                'metrics' => ['family_groups'],
                'sections' => ['Miembros', 'Invitaciones', 'Preferencias del grupo'],
            ],
            'profile' => [
                'title' => 'Mi perfil',
                'module' => 'Perfil usuario',
                'description' => 'Datos personales, objetivos, prioridades.',
                'primary' => 'Editar perfil',
                'secondary' => 'Actualizar prioridades',
                'metrics' => ['allergies', 'health_conditions'],
                'sections' => ['Datos personales', 'Objetivos activos', 'Restricciones', 'Prioridades'],
            ],
            'stock' => [
                'title' => 'Mi stock',
                'module' => 'Stock',
                'description' => 'Listado de productos disponibles.',
                'primary' => 'Agregar producto',
                'secondary' => 'Escanear',
                'metrics' => ['stock_items', 'expiring_items'],
                'sections' => ['Alacena', 'Heladera', 'Freezer', 'Filtros'],
            ],
            'scan-product' => [
                'title' => 'Escanear producto',
                'module' => 'Codigo de barras, stock, compras',
                'description' => 'Escaneo para agregar stock o comprar.',
                'primary' => 'Iniciar escaneo',
                'secondary' => 'Ingresar codigo',
                'metrics' => ['products', 'shopping_lists'],
                'sections' => ['Camara', 'Resultado', 'Acciones rapidas'],
            ],
            'product-detail' => [
                'title' => 'Detalle producto',
                'module' => 'Productos, precios',
                'description' => 'Info del producto, ingrediente asociado, precios por supermercado.',
                'primary' => 'Agregar a stock',
                'secondary' => 'Comparar precios',
                'metrics' => ['products'],
                'sections' => ['Datos comerciales', 'Ingrediente asociado', 'Nutricion', 'Precios'],
            ],
            'expiring-products' => [
                'title' => 'Productos por vencer',
                'module' => 'Stock, alertas',
                'description' => 'Vista especifica de vencimientos.',
                'primary' => 'Cocinar primero',
                'secondary' => 'Marcar revisado',
                'metrics' => ['expiring_items'],
                'sections' => ['Vence hoy', 'Esta semana', 'Sugerencias'],
            ],
            'low-stock' => [
                'title' => 'Bajo stock',
                'module' => 'Stock minimo',
                'description' => 'Productos faltantes o debajo del minimo.',
                'primary' => 'Crear lista',
                'secondary' => 'Editar minimos',
                'metrics' => ['stock_alerts'],
                'sections' => ['Faltantes', 'Debajo del minimo', 'Reglas'],
            ],
            'cook-now' => [
                'title' => 'Que puedo cocinar',
                'module' => 'Recetas, stock',
                'description' => 'Recetas posibles con stock actual.',
                'primary' => 'Ver receta',
                'secondary' => 'Filtrar',
                'metrics' => ['recipes', 'stock_items'],
                'sections' => ['Listas para cocinar', 'Rapidas', 'Usan productos por vencer'],
            ],
            'almost-recipes' => [
                'title' => 'Recetas casi posibles',
                'module' => 'Recetas, compras',
                'description' => 'Recetas donde falta poco y se sugiere compra minima.',
                'primary' => 'Comprar faltantes',
                'secondary' => 'Ver alternativas',
                'metrics' => ['recipes', 'shopping_lists'],
                'sections' => ['Falta 1 ingrediente', 'Compra minima', 'Sustituciones'],
            ],
            'recipe-search' => [
                'title' => 'Buscador de recetas',
                'module' => 'Recetas',
                'description' => 'Filtros por ingrediente, dieta, precio, tiempo, gusto.',
                'primary' => 'Buscar',
                'secondary' => 'Limpiar filtros',
                'metrics' => ['recipes', 'food_tags'],
                'sections' => ['Ingredientes', 'Dieta', 'Precio', 'Tiempo', 'Gustos'],
            ],
            'recipe-detail' => [
                'title' => 'Detalle receta',
                'module' => 'Recetas, nutricion, costo',
                'description' => 'Ingredientes, pasos, costo, nutricion, advertencias.',
                'primary' => 'Cocinar',
                'secondary' => 'Agregar al plan',
                'metrics' => ['recipes'],
                'sections' => ['Ingredientes', 'Pasos', 'Costo', 'Nutricion', 'Advertencias'],
            ],
            'recipe-create' => [
                'title' => 'Crear receta',
                'module' => 'Recetas usuario',
                'description' => 'Alta de receta propia.',
                'primary' => 'Guardar receta',
                'secondary' => 'Vista previa',
                'metrics' => ['ingredient_categories'],
                'sections' => ['Datos basicos', 'Ingredientes', 'Pasos', 'Imagenes'],
            ],
            'planner' => [
                'title' => 'Planificador',
                'module' => 'Meal plan',
                'description' => 'Calendario diario/semanal/mensual.',
                'primary' => 'Agregar comida',
                'secondary' => 'Generar menu',
                'metrics' => ['meal_plans'],
                'sections' => ['Dia', 'Semana', 'Mes', 'Aprobaciones'],
            ],
            'generate-menu' => [
                'title' => 'Generar menu',
                'module' => 'Meal plan automatico',
                'description' => 'Configuracion de preferencias y aprobacion.',
                'primary' => 'Generar',
                'secondary' => 'Guardar preferencias',
                'metrics' => ['recipes', 'stock_items', 'budgets'],
                'sections' => ['Objetivo', 'Presupuesto', 'Stock', 'Restricciones'],
            ],
            'portions' => [
                'title' => 'Porciones por persona',
                'module' => 'Grupo familiar, planificacion',
                'description' => 'Ajuste de porciones de cada miembro.',
                'primary' => 'Guardar porciones',
                'secondary' => 'Restablecer',
                'metrics' => ['family_groups', 'meal_plans'],
                'sections' => ['Miembros', 'Factores', 'Notas'],
            ],
            'shopping-list' => [
                'title' => 'Lista de compras',
                'module' => 'Compras',
                'description' => 'Lista generada/manual.',
                'primary' => 'Agregar item',
                'secondary' => 'Optimizar',
                'metrics' => ['shopping_lists'],
                'sections' => ['Pendientes', 'Alternativas', 'Estimado'],
            ],
            'compare-supermarkets' => [
                'title' => 'Comparar supermercados',
                'module' => 'Supermercados, precios',
                'description' => 'Comparacion de costo total por supermercado.',
                'primary' => 'Comparar',
                'secondary' => 'Elegir sucursal',
                'metrics' => ['products', 'shopping_lists'],
                'sections' => ['Carrefour', 'ChangoMas', 'La Anonima', 'Mejor combinacion'],
            ],
            'shopping-mode' => [
                'title' => 'Modo compra',
                'module' => 'Shopping session',
                'description' => 'Pantalla para usar en supermercado escaneando productos.',
                'primary' => 'Iniciar compra',
                'secondary' => 'Pausar',
                'metrics' => ['shopping_lists'],
                'sections' => ['Escaneo', 'Checklist', 'Total parcial'],
            ],
            'confirm-purchase' => [
                'title' => 'Confirmar compra',
                'module' => 'Compras, stock, presupuesto',
                'description' => 'Confirmar compra, actualizar stock y presupuesto.',
                'primary' => 'Confirmar',
                'secondary' => 'Revisar items',
                'metrics' => ['purchases', 'stock_items', 'budgets'],
                'sections' => ['Totales', 'Stock creado', 'Impacto presupuesto'],
            ],
            'budget' => [
                'title' => 'Presupuesto',
                'module' => 'Presupuesto',
                'description' => 'Mensual, gastado, reservado, disponible.',
                'primary' => 'Ajustar presupuesto',
                'secondary' => 'Ver movimientos',
                'metrics' => ['budgets', 'purchases'],
                'sections' => ['Gastado', 'Reservado', 'Disponible', 'Proyeccion'],
            ],
            'reports' => [
                'title' => 'Reportes',
                'module' => 'Reportes',
                'description' => 'Stock, compras, desperdicio, nutricion estimada.',
                'primary' => 'Exportar',
                'secondary' => 'Cambiar periodo',
                'metrics' => ['stock_items', 'purchases', 'recipes'],
                'sections' => ['Stock', 'Compras', 'Desperdicio', 'Nutricion'],
            ],
            'notifications' => [
                'title' => 'Notificaciones',
                'module' => 'Notificaciones',
                'description' => 'Alertas y recordatorios.',
                'primary' => 'Marcar leidas',
                'secondary' => 'Preferencias',
                'metrics' => ['notifications', 'stock_alerts'],
                'sections' => ['Alertas', 'Recordatorios', 'Preferencias'],
            ],
            'supplements' => [
                'title' => 'Suplementos',
                'module' => 'Suplementos',
                'description' => 'Registro y recordatorios.',
                'primary' => 'Registrar toma',
                'secondary' => 'Nuevo suplemento',
                'metrics' => ['supplements'],
                'sections' => ['Activos', 'Horarios', 'Historial'],
            ],
        ];
    }
}
