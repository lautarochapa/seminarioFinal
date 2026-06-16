<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin CC Control')</title>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/api-client.js') }}" defer></script>
    <script src="{{ asset('js/auth-api.js') }}" defer></script>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#cccccc70; --surface:#fff; --ink:#24252a; --muted:#697681; --line:#dde3e8; --accent:#04ac85; --soft:#e7f7f2; --danger:#b33a3a; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .admin-shell { min-height:calc(100vh - 224px); }
        .nav__links .dropdown-menu a { color:#24252a; }
        .nav__links .dropdown-menu a.active { background:rgba(4,172,133,.9); color:#fff; }
        .content { padding:22px; }
        .topbar { display:none; }
        .hero { background:#fff; border:1px solid var(--line); border-radius:8px; padding:20px; display:flex; justify-content:space-between; gap:18px; margin-bottom:14px; }
        .module { color:var(--accent); font-weight:900; text-transform:uppercase; font-size:12px; }
        h1 { margin:5px 0 8px; font-size:31px; font-weight:900; }
        .lead { color:var(--muted); margin:0; max-width:780px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-main, .btn-ghost { border-radius:50px; padding:10px 18px; text-decoration:none; font-family:"Montserrat", sans-serif; font-weight:500; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; }
        .btn-main { background:rgba(4,172,133,.8); color:#edf0f1; }
        .btn-main:hover { background:rgba(4,172,133,1); color:#edf0f1; text-decoration:none; }
        .btn-ghost { background:#fff; border:1px solid var(--line); color:var(--ink); }
        .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
        .metric { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; min-height:88px; }
        .metric strong { display:block; font-size:27px; }
        .metric span { color:var(--muted); text-transform:capitalize; font-size:13px; }
        .grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:8px; padding:15px; min-height:170px; }
        .panel h2 { font-size:16px; font-weight:900; margin:0 0 10px; }
        .line { display:flex; justify-content:space-between; gap:10px; border-bottom:1px solid #edf1f4; padding:8px 0; font-size:14px; }
        .line:last-child { border-bottom:0; }
        .muted { color:var(--muted); }
        .chip { display:inline-flex; background:var(--soft); color:var(--accent); border-radius:999px; padding:5px 9px; font-size:12px; font-weight:900; margin-top:10px; }
        @media (max-width: 1100px) { .grid, .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 860px) { .content { padding:14px; } .hero { display:block; } .actions { justify-content:flex-start; margin-top:14px; } .grid,.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <a class="logo" href="{{ url('/') }}"><img src="{{ asset('images/logo/2.svg') }}" alt="logo" height="40em"></a>
    <nav><ul class="nav__links">@include('partials.portal-navbar')</ul></nav>
    @include('partials.user-navbar-menu')
</header>
<div class="admin-shell">
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
