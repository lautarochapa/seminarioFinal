@php
    $portalMenus = [
        'Usuario' => [
            'base' => '/web',
            'permission' => 'web.user.dashboard',
            'items' => [
                ['Dashboard', '/web', 'web.user.dashboard'],
                ['Stock del hogar', '/web/stock', 'web.user.stock'],
                ['Recetas', '/web/recipes', 'web.user.recipes'],
                ['Planificacion', '/web/planning', 'web.user.planning'],
                ['Lista de compras', '/web/shopping-list', 'web.user.shopping-list'],
                ['Presupuesto', '/web/budget', 'web.user.budget'],
                ['Reportes', '/web/reports', 'web.user.reports'],
                ['Grupo familiar', '/web/family-group', 'web.user.family-group'],
                ['Perfil y objetivos', '/web/profile-objectives', 'web.user.profile-objectives'],
                ['Metodos de pago', '/web/payment-methods', 'web.user.profile-objectives'],
                ['Permisos dietologo', '/web/professional-permissions', 'web.user.professional-permissions'],
                ['Catálogo', '/web/catalog', 'web.user.catalog'],
                ['Escáner', '/web/barcode-scanner', 'web.user.barcode-scanner'],
                ['Supermercados', '/web/supermarkets', 'web.user.supermarkets'],
                ['Sucursales', '/web/branches', 'web.user.branches'],
            ],
        ],
        'Admin' => [
            'base' => '/admin-web',
            'permission' => 'web.admin.dashboard',
            'items' => [
                ['Dashboard admin', '/admin-web', 'web.admin.dashboard'],
                ['Usuarios', '/admin-web/users', 'web.admin.users'],
                ['Roles y permisos', '/admin-web/roles-permissions', 'web.admin.roles-permissions'],
                ['Objetivos', '/admin-web/objectives', 'web.admin.objectives'],
                ['Restricciones y alergias', '/admin-web/health-preferences', 'web.admin.health-preferences'],
                ['Ingredientes', '/admin-web/ingredients', 'web.admin.ingredients'],
                ['Categorias ingredientes', '/admin-web/ingredient-categories', 'web.admin.ingredient-categories'],
                ['Nutrientes', '/admin-web/nutrients', 'web.admin.nutrients'],
                ['Unidades y conversiones', '/admin-web/units-conversions', 'web.admin.units-conversions'],
                ['Equivalencias ingredientes', '/admin-web/equivalences', 'web.admin.equivalences'],
                ['Tags alimentarios', '/admin-web/food-tags', 'catalog.manage'],
                ['Categorias productos', '/admin-web/product-categories', 'catalog.manage'],
                ['Productos', '/admin-web/products', 'web.admin.products'],
                ['Codigos de barra', '/admin-web/barcodes', 'web.admin.barcodes'],
                ['Marcas', '/admin-web/brands', 'web.admin.brands'],
                ['Reportes de productos', '/admin-web/product-reports', 'web.admin.product-reports'],
                ['Ciudades', '/admin-web/cities', 'web.admin.cities'],
                ['Supermercados', '/admin-web/supermarkets', 'web.admin.supermarkets'],
                ['Productos supermercado', '/admin-web/supermarket-products', 'catalog.manage'],
                ['Promociones', '/admin-web/promotions', 'catalog.manage'],
                ['Metodos de pago', '/admin-web/payment-methods', 'catalog.manage'],
                ['Scraping', '/admin-web/supermarket-scraping', 'web.admin.supermarket-scraping'],
                ['Validacion scrapeados', '/admin-web/scraped-products', 'catalog.manage'],
                ['Refresh precios', '/admin-web/price-refresh-requests', 'scraping.manage'],
                ['Recetas oficiales', '/admin-web/official-recipes', 'web.admin.official-recipes'],
                ['Auditoria', '/admin-web/audit', 'web.admin.audit'],
                ['Configuracion', '/admin-web/settings', 'web.admin.settings'],
            ],
        ],
        'Docente' => [
            'base' => '/teacher-web',
            'permission' => 'web.teacher.home',
            'items' => [
                ['Inicio docente', '/teacher-web', 'web.teacher.home'],
                ['Documentacion funcional', '/teacher-web/functional-docs', 'web.teacher.functional-docs'],
                ['Documentacion tecnica', '/teacher-web/technical-docs', 'web.teacher.technical-docs'],
                ['Comentarios', '/teacher-web/comments', 'web.teacher.comments'],
                ['Escenarios demo', '/teacher-web/demo-scenarios', 'web.teacher.demo-scenarios'],
                ['Metricas demo', '/teacher-web/demo-metrics', 'web.teacher.demo-metrics'],
            ],
        ],
    ];
    $currentUser = Auth::user();
@endphp

@auth
    @foreach($portalMenus as $label => $menu)
        @php
            $visibleItems = array_values(array_filter($menu['items'], function ($item) use ($currentUser) {
                return $currentUser && $currentUser->hasPermission($item[2]);
            }));
        @endphp
        @if(count($visibleItems) > 0)
            <li class="dropdown" data-permission="{{ $menu['permission'] }}">
                <a href="#" class="dropdown-toggle" role="button" id="portalDropdown{{ $label }}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {{ $label }}
                </a>
                <div class="dropdown-menu" aria-labelledby="portalDropdown{{ $label }}">
                    @foreach($visibleItems as $item)
                        <a class="dropdown-item {{ request()->is(ltrim($item[1], '/')) ? 'active' : '' }}" data-permission="{{ $item[2] }}" href="{{ url($item[1]) }}">{{ $item[0] }}</a>
                    @endforeach
                </div>
            </li>
        @endif
    @endforeach
@endauth
