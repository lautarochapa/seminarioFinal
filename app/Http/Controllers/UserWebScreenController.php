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
        ]);
    }

    public function dashboard()
    {
        return $this->index('dashboard');
    }

    private function stats()
    {
        return [
            'family_groups' => FamilyGroup::count(),
            'stock_items' => StockItem::whereIn('family_group_id', function ($q) {
                $q->select('family_group_id')->from('family_group_members')
                    ->where('user_id', auth()->id())->where('status', 'active');
            })->where('status', 'active')->count(),
            'stock_alerts' => StockAlert::where('status', '!=', 'resolved')->count(),
            'recipes' => Recipe::count(),
            'meal_plans' => MealPlan::count(),
            'shopping_lists' => ShoppingList::count(),
            'purchases' => Purchase::count(),
            'budgets' => Budget::count(),
            'report_exports' => ReportExport::count(),
            'objectives' => UserObjective::count(),
            'professional_links' => ProfessionalUserLink::count(),
            'payment_methods' => \App\UserPaymentMethod::where('status', 'active')->count(),
        ];
    }

    private function screens()
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard web',
                'module' => 'General',
                'description' => 'Resumen amplio de stock, comidas, compras, presupuesto y actividad del hogar.',
                'primary' => 'Revisar pendientes',
                'secondary' => 'Generar menu semanal',
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
                'secondary' => 'Ver todas',
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
                'secondary' => 'Cambiar grupo',
                'metrics' => ['recipes'],
                'panels' => ['Sugerencias', 'Disponibles', 'Por vencer', 'Por presupuesto'],
            ],
            'planning' => [
                'title' => 'Planificacion',
                'module' => 'Meal plan',
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
                'secondary' => 'Comparar supermercados',
                'metrics' => ['shopping_lists', 'purchases'],
                'panels' => ['Items pendientes', 'Alternativas', 'Comparacion', 'Historial'],
            ],
            'shopping-session' => [
                'title' => 'Sesion de compra',
                'module' => 'Compras',
                'description' => 'Recorrido mobile para escanear productos, marcar items comprados y confirmar la compra.',
                'primary' => 'Iniciar compra',
                'secondary' => 'Ver listas',
                'metrics' => ['shopping_lists', 'purchases'],
                'panels' => ['Seleccion de lista', 'Escaneo', 'Items comprados', 'Confirmacion'],
            ],
            'purchases' => [
                'title' => 'Compras realizadas',
                'module' => 'Compras',
                'description' => 'Ver items de compras realizadas, agregar items manualmente y confirmar compras por grupo familiar.',
                'primary' => 'Cargar compra',
                'secondary' => 'Ver sesiones',
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
                'module' => 'Onboarding',
                'description' => 'Completa tus datos, objetivo, comidas por dia y grupo familiar para empezar a usar stock y recetas.',
                'primary' => 'Continuar',
                'secondary' => 'Ver mi perfil',
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
