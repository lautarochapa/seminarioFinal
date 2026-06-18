<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CC Control Web')</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLcE=" crossorigin="" defer></script>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/api-client.js') }}?v={{ filemtime(public_path('js/api-client.js')) }}" defer></script>
    <script src="{{ asset('js/auth-api.js') }}?v={{ filemtime(public_path('js/auth-api.js')) }}" defer></script>
    <script src="{{ asset('js/family-groups.js') }}?v={{ file_exists(public_path('js/family-groups.js')) ? filemtime(public_path('js/family-groups.js')) : time() }}" defer></script>
    <script src="{{ asset('js/user-profile.js') }}?v={{ file_exists(public_path('js/user-profile.js')) ? filemtime(public_path('js/user-profile.js')) : time() }}" defer></script>
    <script src="{{ asset('js/professional-links.js') }}?v={{ file_exists(public_path('js/professional-links.js')) ? filemtime(public_path('js/professional-links.js')) : time() }}" defer></script>
    <script src="{{ asset('js/user-catalog.js') }}?v={{ file_exists(public_path('js/user-catalog.js')) ? filemtime(public_path('js/user-catalog.js')) : time() }}" defer></script>
    <script src="{{ asset('js/user-barcode.js') }}?v={{ file_exists(public_path('js/user-barcode.js')) ? filemtime(public_path('js/user-barcode.js')) : time() }}" defer></script>
    <script src="{{ asset('js/user-supermarkets.js') }}?v={{ file_exists(public_path('js/user-supermarkets.js')) ? filemtime(public_path('js/user-supermarkets.js')) : time() }}" defer></script>
    <script src="{{ asset('js/user-branches.js') }}?v={{ file_exists(public_path('js/user-branches.js')) ? filemtime(public_path('js/user-branches.js')) : time() }}" defer></script>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#cccccc70; --surface:#fff; --ink:#24252a; --muted:#66746b; --line:#dde6df; --green:#04ac85; --green-soft:#e7f7f2; --blue:#2f80ed; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .web-shell { min-height:calc(100vh - 224px); }
        .nav__links .dropdown-menu a { color:#24252a; }
        .nav__links .dropdown-menu a.active { background:rgba(4,172,133,.9); color:#fff; }
        .content { padding:22px; }
        .topbar { display:none; }
        .hero { background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:22px; display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:14px; }
        .module { color:var(--green); text-transform:uppercase; font-size:12px; font-weight:900; letter-spacing:0; }
        h1 { font-size:32px; line-height:1.15; margin:6px 0 8px; font-weight:900; }
        .lead { color:var(--muted); margin:0; max-width:760px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-main, .btn-secondary-web { border-radius:50px; padding:10px 18px; font-family:"Montserrat", sans-serif; font-weight:500; text-decoration:none; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; }
        .btn-main { background:rgba(4,172,133,.8); color:#edf0f1; border:0; }
        .btn-main:hover { background:rgba(4,172,133,1); color:#edf0f1; text-decoration:none; }
        .btn-secondary-web { background:#fff; color:var(--ink); border:1px solid var(--line); }
        .metric-row { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
        .metric { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; min-height:96px; }
        .metric strong { display:block; font-size:28px; line-height:1; margin-bottom:8px; }
        .metric span { color:var(--muted); font-size:13px; text-transform:capitalize; }
        .workspace { display:grid; grid-template-columns:minmax(0,2fr) minmax(280px,1fr); gap:14px; }
        .panel-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .panel, .aside-panel { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; }
        .panel { min-height:190px; }
        .panel h2, .aside-panel h2 { font-size:17px; font-weight:900; margin:0 0 12px; }
        .table-line { display:grid; grid-template-columns:1fr auto; gap:10px; border-bottom:1px solid #edf2ee; padding:9px 0; font-size:14px; }
        .table-line:last-child { border-bottom:0; }
        .muted { color:var(--muted); }
        .chip { display:inline-flex; align-items:center; background:var(--green-soft); color:var(--green); border-radius:999px; padding:5px 9px; font-size:12px; font-weight:900; margin-right:6px; }
        .web-table { width:100%; border-collapse:collapse; font-size:14px; }
        .web-table th, .web-table td { border-bottom:1px solid #edf2ee; padding:10px 8px; vertical-align:top; }
        .web-table th { color:var(--muted); font-size:12px; text-transform:uppercase; }
        .web-tools { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:12px; }
        .web-tools .form-control { max-width:280px; }
        .family-layout { display:grid; grid-template-columns:minmax(0,2fr) minmax(300px,1fr); gap:14px; }
        .family-stack { display:grid; gap:12px; }
        .family-form .form-control { margin-bottom:9px; }
        .btn-sm { padding:6px 11px; font-size:12px; }
        .chip.danger { background:#f7e7e7; color:#b33a3a; }
        .profile-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .profile-form .form-control, .profile-form textarea, .profile-form select { margin-bottom:9px; }
        .checkbox-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-top:10px; }
        .checkbox-card { border:1px solid var(--line); border-radius:8px; padding:10px 12px; background:#fafdfb; display:flex; gap:8px; align-items:flex-start; }
        .objective-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-top:10px; }
        .objective-item { border:1px solid var(--line); border-radius:8px; padding:10px 12px; background:#fff; display:flex; gap:8px; align-items:flex-start; }
        .audit-tabs { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px; }
        .audit-tab { border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:999px;padding:9px 15px;font-weight:900;cursor:pointer; }
        .audit-tab.active { background:rgba(4,172,133,.9);border-color:rgba(4,172,133,.9);color:#fff; }
        .catalog-pagination { display:flex;gap:8px;align-items:center;margin-top:12px; }
        .product-thumb-img { width:36px;height:36px;object-fit:contain;border-radius:4px;background:#f0f0f0;vertical-align:middle;margin-right:6px;flex-shrink:0; }
        .product-image-main { width:100%;max-height:220px;object-fit:contain;background:#f0f0f0;border-radius:8px;display:block;margin-bottom:10px; }
        .product-image-gallery { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px; }
        .product-image-thumb { width:52px;height:52px;object-fit:contain;background:#f0f0f0;border-radius:4px;cursor:pointer;border:2px solid transparent; }
        .product-image-thumb.active { border-color:var(--green); }
        .product-image-placeholder { display:flex;align-items:center;justify-content:center;background:#f5f5f5;border-radius:8px;height:90px;color:var(--muted);font-size:13px;margin-bottom:10px; }
        .report-form-toggle { margin-top:14px;padding-top:14px;border-top:1px solid var(--line); }
        .report-form-section select,.report-form-section textarea { width:100%;box-sizing:border-box;margin-bottom:8px; }
        .report-form-section { margin-top:10px; }
        @media (max-width: 960px) {
            .content { padding:14px; }
            .hero, .topbar { display:block; }
            .actions { justify-content:flex-start; margin-top:14px; }
            .metric-row, .workspace, .panel-grid, .family-layout, .profile-grid, .checkbox-grid, .objective-list { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<header>
    <a class="logo" href="{{ url('/') }}"><img src="{{ asset('images/logo/2.svg') }}" alt="logo" height="40em"></a>
    <nav>
        <ul class="nav__links">
            @include('partials.portal-navbar')
        </ul>
    </nav>
    @include('partials.user-navbar-menu')
</header>
<div class="web-shell">
    <main class="content">
        @yield('content')
    </main>
</div>
<footer style="background-color:#24252a;color:#edf0f1;padding:28px 15%;width:100%;">
    <div class="row">
        <div class="col-md-4 align-self-center" style="text-align:center;">Imagenes de Pexels<br>Iconos de Flaticon</div>
        <div class="col-md-4 align-self-center" style="text-align:center;"><img src="{{ asset('images/logo/1.svg') }}" alt="logo" height="92em"></div>
        <div class="col-md-4 align-self-center" style="text-align:center;">Desarrollado por:<br>Lautaro Chiappero<br>comidacocinacontrol@gmail.com</div>
    </div>
</footer>
</body>
</html>
