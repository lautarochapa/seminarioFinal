<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CocinaComidaControl') }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/navbar.css') }}?v={{ filemtime(public_path('css/navbar.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/public-pages.css') }}?v={{ filemtime(public_path('css/public-pages.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/loader.css') }}?v={{ filemtime(public_path('css/loader.css')) }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700,800&display=swap" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/api-client.js') }}?v={{ filemtime(public_path('js/api-client.js')) }}" defer></script>
    <script src="{{ asset('js/auth-api.js') }}?v={{ filemtime(public_path('js/auth-api.js')) }}" defer></script>
    <script src="{{ asset('js/mobile-entry.js') }}?v={{ filemtime(public_path('js/mobile-entry.js')) }}" defer></script>
    <script src="{{ asset('js/loader.js') }}?v={{ filemtime(public_path('js/loader.js')) }}" defer></script>
    @yield('page_styles')
</head>
<body data-mobile-invitation="{{ session('mobile_invitation_until', 0) > time() ? '1' : '0' }}">
<div id="app" class="public-app">
    @include('partials.site-header')
    <main class="py-5">@yield('content')</main>
    @include('partials.site-footer')
</div>
@include('partials/navigation-loader')
</body>
</html>
