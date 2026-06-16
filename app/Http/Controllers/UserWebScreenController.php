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
            'stock_items' => StockItem::count(),
            'stock_alerts' => StockAlert::where('status', '!=', 'resolved')->count(),
            'recipes' => Recipe::count(),
            'meal_plans' => MealPlan::count(),
            'shopping_lists' => ShoppingList::count(),
            'purchases' => Purchase::count(),
            'budgets' => Budget::count(),
            'report_exports' => ReportExport::count(),
            'objectives' => UserObjective::count(),
            'professional_links' => ProfessionalUserLink::count(),
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
            'profile-objectives' => [
                'title' => 'Perfil y objetivos',
                'module' => 'Perfil',
                'description' => 'Configuracion personal, objetivos, restricciones, alergias y prioridades.',
                'primary' => 'Editar perfil',
                'secondary' => 'Actualizar objetivos',
                'metrics' => ['objectives'],
                'panels' => ['Datos personales', 'Objetivos', 'Restricciones', 'Prioridades'],
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
        ];
    }

    private function canAccessScreen($screen)
    {
        $user = request()->user();
        $permission = 'web.user.' . $screen;

        return $user && $user->hasPermission($permission);
    }
}
