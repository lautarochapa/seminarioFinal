@php
    $portalMenus = [
        'Usuario' => [
            'base' => '/web',
            'items' => [
                ['Dashboard', '/web'],
                ['Stock del hogar', '/web/stock'],
                ['Recetas', '/web/recipes'],
                ['Planificacion', '/web/planning'],
                ['Lista de compras', '/web/shopping-list'],
                ['Presupuesto', '/web/budget'],
                ['Reportes', '/web/reports'],
                ['Grupo familiar', '/web/family-group'],
                ['Perfil y objetivos', '/web/profile-objectives'],
                ['Permisos dietologo', '/web/professional-permissions'],
            ],
        ],
        'Admin' => [
            'base' => '/admin-web',
            'items' => [
                ['Dashboard admin', '/admin-web'],
                ['Usuarios', '/admin-web/users'],
                ['Roles y permisos', '/admin-web/roles-permissions'],
                ['Ingredientes', '/admin-web/ingredients'],
                ['Categorias ingredientes', '/admin-web/ingredient-categories'],
                ['Nutrientes', '/admin-web/nutrients'],
                ['Unidades y conversiones', '/admin-web/units-conversions'],
                ['Productos', '/admin-web/products'],
                ['Supermercados', '/admin-web/supermarkets'],
                ['Scraping', '/admin-web/supermarket-scraping'],
                ['Recetas oficiales', '/admin-web/official-recipes'],
                ['Auditoria', '/admin-web/audit'],
                ['Configuracion', '/admin-web/settings'],
            ],
        ],
        'Docente' => [
            'base' => '/teacher-web',
            'items' => [
                ['Inicio docente', '/teacher-web'],
                ['Documentacion funcional', '/teacher-web/functional-docs'],
                ['Documentacion tecnica', '/teacher-web/technical-docs'],
                ['Comentarios', '/teacher-web/comments'],
                ['Escenarios demo', '/teacher-web/demo-scenarios'],
                ['Metricas demo', '/teacher-web/demo-metrics'],
            ],
        ],
    ];
@endphp

@foreach($portalMenus as $label => $menu)
    <li class="dropdown">
        <a href="#" class="dropdown-toggle" role="button" id="portalDropdown{{ $label }}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {{ $label }}
        </a>
        <div class="dropdown-menu" aria-labelledby="portalDropdown{{ $label }}">
            @foreach($menu['items'] as $item)
                <a class="dropdown-item {{ request()->is(ltrim($item[1], '/')) ? 'active' : '' }}" href="{{ url($item[1]) }}">{{ $item[0] }}</a>
            @endforeach
        </div>
    </li>
@endforeach
