<?php

namespace App\Http\Controllers;

use App\Budget;
use App\FamilyGroup;
use App\MealPlan;
use App\ProfessionalUserLink;
use App\Purchase;
use App\Recipe;
use App\ReportExport;
use App\ShoppingList;
use App\StockAlert;
use App\StockItem;
use App\UserObjective;
use App\Services\Onboarding\OnboardingStatusService;

class UserWebScreenController extends Controller
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

        if ($redirect = $this->maybeRedirectToOnboarding($screen)) {
            return $redirect;
        }

        return view('web.user-screen', [
            'screenKey' => $screen,
            'screen' => $screens[$screen],
            'screens' => $screens,
            'stats' => $this->stats(),
            'metricLabels' => [
                'family_groups' => 'Grupos familiares', 'stock_items' => 'Productos en stock',
                'stock_alerts' => 'Alertas de stock', 'recipes' => 'Recetas',
                'meal_plans' => 'Planes de comidas', 'shopping_lists' => 'Listas de compras',
                'purchases' => 'Compras', 'budgets' => 'Presupuestos',
                'report_exports' => 'Reportes exportados', 'objectives' => 'Objetivos',
                'professional_links' => 'Profesionales vinculados', 'payment_methods' => 'Metodos de pago',
            ],
        ]);
    }

    public function dashboard()
    {
        return $this->index('dashboard');
    }

    private function stats()
    {
        $userId = auth()->id();
        $groupIds = \Illuminate\Support\Facades\DB::table('family_group_members')
            ->select('family_group_id')->where('user_id', $userId)->where('status', 'active');
        return [
            'family_groups' => FamilyGroup::whereIn('id', $groupIds)->count(),
            'stock_items' => StockItem::whereIn('family_group_id', $groupIds)->where('status', 'active')->count(),
            'stock_alerts' => StockAlert::whereIn('family_group_id', $groupIds)->where('status', '!=', 'resolved')->count(),
            'recipes' => Recipe::where('status', 'active')->where(function ($visible) use ($userId) {
                $visible->where('is_public', true)->orWhere('owner_user_id', $userId);
            })->count(),
            'meal_plans' => MealPlan::whereIn('family_group_id', $groupIds)->count(),
            'shopping_lists' => ShoppingList::whereIn('family_group_id', $groupIds)->count(),
            'purchases' => Purchase::whereIn('family_group_id', $groupIds)->count(),
            'budgets' => Budget::whereIn('family_group_id', $groupIds)->count(),
            'report_exports' => ReportExport::where('user_id', $userId)->count(),
            'objectives' => UserObjective::where('user_id', $userId)->count(),
            'professional_links' => ProfessionalUserLink::where('user_id', $userId)->count(),
            'payment_methods' => \App\UserPaymentMethod::where('user_id', $userId)->where('status', 'active')->count(),
        ];
    }

    private function screens()
    {
        return [
            'dashboard' => [
                'title' => 'Resumen del hogar',
                'module' => 'General',
                'description' => 'Resumen amplio de stock, comidas, compras, presupuesto y actividad del hogar.',
                'primary' => 'Revisar pendientes',
                'primary_href' => '#home-actions',
                'secondary' => 'Planificar comidas',
                'secondary_href' => '/web/planning',
                'metrics' => ['stock_items', 'meal_plans', 'shopping_lists', 'budgets'],
                'panels' => ['Resumen semanal', 'Alertas del hogar', 'Planificacion activa', 'Actividad reciente'],
            ],
            'stock' => [
                'title' => 'Stock del hogar',
                'module' => 'Stock',
                'description' => 'Gestion comoda desde escritorio con filtros por ubicacion, vencimiento y estado.',
                'primary' => 'Agregar producto',
                'secondary' => 'Importar compra',
                'metrics' => ['stock_items', 'stock_alerts'],
                'panels' => ['Listado editable', 'Ubicaciones', 'Vencimientos', 'Reglas de minimo'],
            ],
            'recipes' => [
                'title' => 'Recetas',
                'module' => 'Recetas',
                'description' => 'Buscar, crear, editar y compartir recetas propias u oficiales.',
                'primary' => 'Crear receta',
                'secondary' => 'Buscar recetas',
                'metrics' => ['recipes'],
                'panels' => ['Buscador avanzado', 'Mis recetas', 'Recetas oficiales', 'Compartidas'],
            ],
            'recipe-search' => [
                'title' => 'Buscar recetas',
                'module' => 'Recetas',
                'description' => 'Buscador avanzado de recetas por nombre, categoria, tags, dificultad y tiempo de preparacion.',
                'primary' => 'Nueva busqueda',
                'primary_href' => '#recipe-search',
                'secondary' => 'Ver todas',
                'secondary_href' => '/web/recipes',
                'metrics' => ['recipes'],
                'panels' => ['Resultados', 'Filtros', 'Detalle', 'Tags'],
            ],
            'recipe-favorites' => [
                'title' => 'Favoritos y cocinadas',
                'module' => 'Recetas',
                'description' => 'Recetas guardadas como favoritas y registro de recetas cocinadas con porciones y fecha.',
                'primary' => 'Ver favoritas',
                'secondary' => 'Ver cocinadas',
                'metrics' => ['recipes'],
                'panels' => ['Favoritas', 'Cocinadas', 'Detalle', 'Historial'],
            ],
            'recipe-suggestions' => [
                'title' => 'Recomendaciones',
                'module' => 'Recetas',
                'description' => 'Sugerencias personalizadas segun stock disponible, presupuesto, vencimientos y objetivos.',
                'primary' => 'Ver sugerencias',
                'primary_href' => '#recipe-suggestions',
                'secondary' => 'Ver grupo familiar',
                'secondary_href' => '/web/family-group',
                'metrics' => ['recipes'],
                'panels' => ['Sugerencias', 'Disponibles', 'Por vencer', 'Por presupuesto'],
            ],
            'planning' => [
                'title' => 'Planificacion',
                'module' => 'Planificacion',
                'description' => 'Calendario semanal y mensual para organizar comidas por grupo familiar.',
                'primary' => 'Agregar comida',
                'secondary' => 'Generar automaticamente',
                'metrics' => ['meal_plans', 'recipes'],
                'panels' => ['Calendario semanal', 'Vista mensual', 'Porciones', 'Incompatibilidades'],
            ],
            'shopping-list' => [
                'title' => 'Lista de compras',
                'module' => 'Compras',
                'description' => 'Edicion de listas, alternativas y comparacion de precios por supermercado.',
                'primary' => 'Nueva lista',
                'secondary' => 'Ver compras realizadas',
                'secondary_href' => '/web/purchases',
                'metrics' => ['shopping_lists', 'purchases'],
                'panels' => ['Items pendientes', 'Alternativas', 'Comparacion', 'Historial'],
            ],
            'shopping-session' => [
                'title' => 'Sesion de compra',
                'module' => 'Compras',
                'description' => 'Escanea productos, marca items comprados y confirma la compra.',
                'primary' => 'Ver sesion de compra',
                'primary_href' => '#shopping-session',
                'secondary' => 'Ver listas',
                'secondary_href' => '/web/shopping-list',
                'metrics' => ['shopping_lists', 'purchases'],
                'panels' => ['Seleccion de lista', 'Escaneo', 'Items comprados', 'Confirmacion'],
            ],
            'purchases' => [
                'title' => 'Compras realizadas',
                'module' => 'Compras',
                'description' => 'Ver items de compras realizadas, agregar items manualmente y confirmar compras por grupo familiar.',
                'primary' => 'Cargar compra',
                'primary_href' => '#purchases',
                'secondary' => 'Ver sesiones',
                'secondary_href' => '/web/shopping-session',
                'metrics' => ['purchases'],
                'panels' => ['Items', 'Confirmar', 'Agregar item', 'Stock'],
            ],
            'notifications' => [
                'title' => 'Notificaciones',
                'module' => 'Notificaciones',
                'description' => 'Centro de notificaciones: vencimientos, bajo stock, presupuesto, menú del día y más.',
                'primary' => 'Marcar todo leído',
                'secondary' => 'Preferencias',
                'metrics' => [],
                'panels' => ['Sin leer', 'Todas', 'Por tipo', 'Preferencias'],
            ],
            'supplements' => [
                'title' => 'Suplementos',
                'module' => 'Suplementos',
                'description' => 'Registrá y seguí tus suplementos: proteína, creatina, vitaminas y más.',
                'primary' => 'Agregar suplemento',
                'secondary' => 'Ver historial',
                'metrics' => [],
                'panels' => ['Mis suplementos', 'Tipo', 'Dosis', 'Presupuesto'],
            ],
            'budget' => [
                'title' => 'Presupuesto',
                'module' => 'Presupuesto',
                'description' => 'Vista mensual con gastado, reservado, disponible, proyecciones y reportes.',
                'primary' => 'Configurar mes',
                'secondary' => 'Ver movimientos',
                'metrics' => ['budgets', 'purchases'],
                'panels' => ['Resumen mensual', 'Categorias', 'Movimientos', 'Proyeccion'],
            ],
            'reports' => [
                'title' => 'Reportes',
                'module' => 'Reportes',
                'description' => 'Graficos y exportacion sobre stock, compras, desperdicio y nutricion estimada.',
                'primary' => 'Exportar reporte',
                'secondary' => 'Cambiar periodo',
                'metrics' => ['report_exports', 'purchases', 'stock_items'],
                'panels' => ['Compras', 'Desperdicio', 'Nutricion', 'Exportaciones'],
            ],
            'family-group' => [
                'title' => 'Grupo familiar',
                'module' => 'Grupo',
                'description' => 'Administracion de miembros, invitaciones, roles y preferencias del hogar.',
                'primary' => 'Invitar miembro',
                'secondary' => 'Editar preferencias',
                'metrics' => ['family_groups'],
                'panels' => ['Miembros', 'Invitaciones', 'Roles del grupo', 'Preferencias'],
            ],
            'onboarding' => [
                'title' => 'Puesta en marcha',
                'module' => 'Configuracion inicial',
                'description' => 'Completa tus datos, objetivo, comidas por dia y grupo familiar para empezar a usar stock y recetas.',
                'primary' => 'Continuar',
                'primary_href' => '/web/profile-objectives',
                'secondary' => 'Ver mi perfil',
                'secondary_href' => '/web/profile-objectives',
                'metrics' => ['objectives', 'family_groups'],
                'panels' => ['Datos basicos', 'Preferencias alimentarias', 'Comidas por dia', 'Grupo familiar'],
            ],
            'profile-objectives' => [
                'title' => 'Perfil y objetivos',
                'module' => 'Perfil',
                'description' => 'Configuracion personal, objetivos, restricciones, alergias y prioridades.',
                'primary' => 'Editar perfil',
                'secondary' => 'Actualizar objetivos',
                'metrics' => ['objectives'],
                'panels' => ['Datos personales', 'Objetivos', 'Restricciones', 'Prioridades'],
            ],
            'payment-methods' => [
                'title' => 'Metodos de pago',
                'module' => 'Promociones',
                'description' => 'Elegí los metodos que usas para que el sistema pueda mostrar promociones compatibles.',
                'primary' => 'Agregar metodo',
                'secondary' => 'Ver promociones',
                'metrics' => ['payment_methods'],
                'panels' => ['Catalogo', 'Mis metodos', 'Promociones', 'Seguridad'],
                'permission' => 'web.user.profile-objectives',
            ],
            'professional-permissions' => [
                'title' => 'Permisos dietologo',
                'module' => 'Profesional',
                'description' => 'Dar o revocar acceso profesional a perfil, stock, reportes y planificacion.',
                'primary' => 'Autorizar profesional',
                'secondary' => 'Revocar acceso',
                'metrics' => ['professional_links'],
                'panels' => ['Profesionales autorizados', 'Permisos activos', 'Historial', 'Solicitudes'],
            ],
            'catalog' => [
                'title' => 'Catálogo',
                'module' => 'Catálogo',
                'description' => 'Explorá y buscá productos e ingredientes del catálogo con filtros por categoría, marca y tags.',
                'primary' => 'Buscar productos',
                'secondary' => 'Explorar ingredientes',
                'metrics' => [],
                'panels' => ['Productos', 'Ingredientes', 'Filtros', 'Detalle'],
            ],
            'barcode-scanner' => [
                'title' => 'Escáner de código',
                'module' => 'Productos',
                'description' => 'Buscá un producto ingresando su código de barras o usando la cámara del dispositivo.',
                'primary' => 'Buscar por código',
                'secondary' => 'Ir al catálogo',
                'metrics' => [],
                'panels' => ['Búsqueda', 'Resultado', 'Historial', 'Acciones'],
            ],
            'supermarkets' => [
                'title' => 'Supermercados',
                'module' => 'Supermercados',
                'description' => 'Explorá las cadenas de supermercados disponibles en la plataforma.',
                'primary' => 'Ver supermercados',
                'secondary' => 'Ir al catálogo',
                'metrics' => [],
                'panels' => ['Cadenas', 'Detalle', 'Sucursales', 'Precios'],
            ],
            'branches' => [
                'title' => 'Sucursales',
                'module' => 'Supermercados',
                'description' => 'Encontrá sucursales por ciudad, cadena o usando tu ubicación actual.',
                'primary' => 'Ver sucursales',
                'secondary' => 'Buscar cercanas',
                'metrics' => [],
                'panels' => ['Listado', 'Mapa', 'Cercanas', 'Detalle'],
            ],
        ];
    }

    private function canAccessScreen($screen)
    {
        $user = request()->user();
        $screens = $this->screens();
        $permission = $screens[$screen]['permission'] ?? 'web.user.' . $screen;

        return $user && $user->hasPermission($permission);
    }

    private function maybeRedirectToOnboarding($screen)
    {
        if ($screen !== 'dashboard') {
            return null;
        }

        $user = request()->user();
        if (! $user || ! $user->hasPermission('web.user.onboarding')) {
            return null;
        }

        if (app(OnboardingStatusService::class)->isComplete($user->id)) {
            return null;
        }

        return redirect('/web/onboarding');
    }
}
