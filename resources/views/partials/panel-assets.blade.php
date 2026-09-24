@php
    $coreScreens = ['dashboard', 'stock', 'recipes', 'planning', 'shopping-list', 'budget', 'family-group', 'profile-objectives'];
    $partialNavigation = in_array($screenKey ?? '', $coreScreens, true);
    $recipeAssets = ['recipe-ingredients', 'recipe-steps', 'recipe-nutrition', 'recipe-cost', 'recipe-availability', 'recipe-substitutions', 'recipe-favorites-actions', 'recipe-sharing-branch'];
    $screenAssets = [
        'onboarding' => ['user-onboarding'], 'catalog' => ['user-catalog'],
        'barcode-scanner' => ['user-barcode'], 'supermarkets' => ['user-supermarkets'],
        'branches' => ['user-branches'], 'payment-methods' => ['user-payment-methods'],
        'recipe-favorites' => ['user-recipe-favorites'], 'recipe-search' => ['user-recipe-search'],
        'recipe-suggestions' => ['user-recipe-suggestions'], 'notifications' => ['user-notifications'],
        'reports' => ['user-reports'], 'supplements' => ['user-supplements'],
        'purchases' => ['user-purchases'], 'shopping-session' => ['user-shopping-session'],
    ];
    // Core factories share one stable head and mount only on their own screen.
    $scripts = $partialNavigation
        ? array_merge(['user-home', 'family-groups', 'user-profile', 'user-stock-locations'], $recipeAssets,
            ['user-recipes', 'user-meal-plans', 'user-budget', 'shopping-alternatives', 'shopping-compare', 'user-shopping-lists'])
        : array_merge(in_array($screenKey ?? '', ['recipe-search', 'recipe-favorites', 'recipe-suggestions'], true) ? $recipeAssets : [], $screenAssets[$screenKey ?? ''] ?? []);
@endphp
@if($partialNavigation)
    <meta name="turbo-cache-control" content="no-cache">
    <meta name="turbo-prefetch" content="false">
    <script src="{{ asset('js/vendor/turbo-8.0.23/dist/turbo.es2017-umd.js') }}" data-turbo-track="reload" defer></script>
    <script src="{{ asset('js/panel-navigation.js') }}?v={{ filemtime(public_path('js/panel-navigation.js')) }}" data-turbo-track="reload" defer></script>
@else
    <meta name="turbo-visit-control" content="reload">
@endif
@if(($screenKey ?? '') === 'branches')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLcE=" crossorigin="" defer></script>
@endif
@foreach(array_merge(['panel-menu', 'api-client', 'auth-api', 'panel-ui', 'loader'], $scripts) as $script)
    <script src="{{ asset('js/'.$script.'.js') }}?v={{ filemtime(public_path('js/'.$script.'.js')) }}" @if($partialNavigation) data-turbo-track="reload" @endif defer></script>
@endforeach
