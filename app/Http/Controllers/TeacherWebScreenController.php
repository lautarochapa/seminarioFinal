<?php

namespace App\Http\Controllers;

use App\DemoScenario;
use App\FamilyGroup;
use App\Recipe;
use App\ReportSnapshot;
use App\ThesisComment;
use App\ThesisDocument;
use App\ThesisDocumentSection;
use App\ThesisDocumentVersion;
use App\User;

class TeacherWebScreenController extends Controller
{
    public function index($screen = 'home')
    {
        $screens = $this->screens();

        if (! isset($screens[$screen])) {
            abort(404);
        }

        if (! $this->canAccessScreen($screen)) {
            return response('Sin permiso para acceder a esta pantalla.', 403);
        }

        return view('web.teacher-screen', [
            'screenKey' => $screen,
            'screen' => $screens[$screen],
            'screens' => $screens,
            'stats' => $this->stats(),
        ]);
    }

    public function home()
    {
        return $this->index('home');
    }

    private function stats()
    {
        return [
            'documents' => ThesisDocument::count(),
            'sections' => ThesisDocumentSection::count(),
            'versions' => ThesisDocumentVersion::count(),
            'comments' => ThesisComment::count(),
            'demo_scenarios' => DemoScenario::count(),
            'demo_users' => User::count(),
            'families' => FamilyGroup::count(),
            'recipes' => Recipe::count(),
            'report_snapshots' => ReportSnapshot::count(),
        ];
    }

    private function screens()
    {
        return [
            'home' => [
                'title' => 'Inicio docente',
                'module' => 'Documentacion',
                'description' => 'Acceso a documentacion y demos.',
                'primary' => 'Abrir documentacion',
                'secondary' => 'Ver demos',
                'metrics' => ['documents', 'demo_scenarios', 'comments', 'report_snapshots'],
                'panels' => ['Documentos recientes', 'Demos preparados', 'Comentarios abiertos', 'Metricas generales'],
            ],
            'functional-docs' => [
                'title' => 'Documentacion funcional',
                'module' => 'Docs',
                'description' => 'Alcance, RF, RNF, casos de uso.',
                'primary' => 'Ver alcance',
                'secondary' => 'Comentar',
                'metrics' => ['documents', 'sections', 'comments'],
                'panels' => ['Alcance', 'Requerimientos funcionales', 'Requerimientos no funcionales', 'Casos de uso'],
            ],
            'technical-docs' => [
                'title' => 'Documentacion tecnica',
                'module' => 'Docs',
                'description' => 'Arquitectura, ERD, APIs, stack.',
                'primary' => 'Ver arquitectura',
                'secondary' => 'Ver versiones',
                'metrics' => ['documents', 'sections', 'versions'],
                'panels' => ['Arquitectura', 'Modelo ERD', 'APIs', 'Stack tecnico'],
            ],
            'comments' => [
                'title' => 'Comentarios',
                'module' => 'Docs',
                'description' => 'Comentar secciones.',
                'primary' => 'Nuevo comentario',
                'secondary' => 'Filtrar abiertos',
                'metrics' => ['comments', 'documents', 'sections'],
                'panels' => ['Comentarios abiertos', 'Por documento', 'Por seccion', 'Historial'],
            ],
            'demo-scenarios' => [
                'title' => 'Escenarios demo',
                'module' => 'Demo',
                'description' => 'Ver usuarios demo y recorridos preparados.',
                'primary' => 'Iniciar demo',
                'secondary' => 'Ver usuarios demo',
                'metrics' => ['demo_scenarios', 'demo_users', 'families'],
                'panels' => ['Recorridos', 'Usuarios demo', 'Datos preparados', 'Rutas'],
            ],
            'demo-metrics' => [
                'title' => 'Metricas demo',
                'module' => 'Reportes',
                'description' => 'Metricas generales de prueba.',
                'primary' => 'Actualizar metricas',
                'secondary' => 'Exportar',
                'metrics' => ['report_snapshots', 'demo_users', 'recipes', 'families'],
                'panels' => ['Uso general', 'Datos cargados', 'Cobertura funcional', 'Estado demo'],
            ],
        ];
    }

    private function canAccessScreen($screen)
    {
        $user = request()->user();
        $permission = 'web.teacher.' . $screen;

        return $user && $user->hasPermission($permission);
    }
}
