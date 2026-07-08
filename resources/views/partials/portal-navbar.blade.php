@php
    $portalMenus = [
        'Usuario' => [
            'base' => '/web',
            'permission' => 'web.user.dashboard',
            'items' => [
                ['Dashboard', '/web', 'web.user.dashboard'],
                ['Stock del hogar', '/web/stock', 'web.user.stock'],
                ['Recetas', '/web/recipes', 'web.user.recipes'],
                ['Buscar recetas', '/web/recipe-search', 'web.user.recipe-search'],
                ['Recomendaciones', '/web/recipe-suggestions', 'web.user.recipe-suggestions'],
                ['Favoritos y cocinadas', '/web/recipe-favorites', 'web.user.recipe-favorites'],
                ['Planificacion', '/web/planning', 'web.user.planning'],
                ['Lista de compras', '/web/shopping-list', 'web.user.shopping-list'],
                ['Sesión de compra', '/web/shopping-session', 'web.user.shopping-session'],
                ['Compras realizadas', '/web/purchases', 'web.user.purchases'],
                ['Notificaciones', '/web/notifications', 'web.user.notifications'],
                ['Suplementos', '/web/supplements', 'web.user.supplements'],
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
                ['Solicitudes productos', '/admin-web/product-requests', 'web.admin.product-requests'],
                ['Codigos de barra', '/admin-web/barcodes', 'web.admin.barcodes'],
                ['Marcas', '/admin-web/brands', 'web.admin.brands'],
                ['Reportes de productos', '/admin-web/product-reports', 'web.admin.product-reports'],
                ['Ciudades', '/admin-web/cities', 'web.admin.cities'],
                ['Supermercados', '/admin-web/supermarkets', 'web.admin.supermarkets'],
                ['Productos supermercado', '/admin-web/supermarket-products', 'catalog.manage'],
                ['Promociones', '/admin-web/promotions', 'catalog.manage'],
                ['Metodos de pago', '/admin-web/payment-methods', 'catalog.manage'],
                ['Scraping', '/admin-web/supermarket-scraping', 'web.admin.supermarket-scraping'],
                ['Scraping recetas', '/admin-web/recipe-scraping', 'recipes.manage'],
                ['Validacion scrapeados', '/admin-web/scraped-products', 'catalog.manage'],
                ['Refresh precios', '/admin-web/price-refresh-requests', 'scraping.manage'],
                ['Categorias recetas', '/admin-web/recipe-categories', 'web.admin.recipe-categories'],
                ['Tags recetas', '/admin-web/recipe-tags', 'web.admin.recipe-tags'],
                ['Recetas oficiales', '/admin-web/official-recipes', 'web.admin.official-recipes'],
                ['Tipos de comida', '/admin-web/meal-types', 'catalog.manage'],
                ['Importar recetas', '/admin-web/recipe-import', 'web.admin.recipe-import'],
                ['Importar por texto', '/admin-web/recipe-import-text', 'web.admin.recipe-import-text'],
                ['Validacion recetas', '/admin-web/imported-recipes', 'recipes.manage'],
                ['Reportes admin', '/admin-web/admin-reports', 'web.admin.admin-reports'],
                ['Documentacion tesis', '/admin-web/thesis-docs', 'web.admin.thesis-docs'],
                ['Escenarios demo', '/admin-web/demo-scenarios', 'web.admin.demo-scenarios'],
                ['Auditoria', '/admin-web/audit', 'web.admin.audit'],
                ['Feature flags', '/admin-web/feature-flags', 'web.admin.feature-flags'],
                ['Base IA', '/admin-web/ai-foundation', 'web.admin.ai-foundation'],
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
