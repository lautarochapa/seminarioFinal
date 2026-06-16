<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Docente CC Control')</title>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/api-client.js') }}?v={{ filemtime(public_path('js/api-client.js')) }}" defer></script>
    <script src="{{ asset('js/auth-api.js') }}?v={{ filemtime(public_path('js/auth-api.js')) }}" defer></script>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#cccccc70; --surface:#fff; --ink:#24252a; --muted:#716d64; --line:#e3ded2; --accent:#04ac85; --soft:#e7f7f2; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .teacher-shell { min-height:calc(100vh - 224px); }
        .nav__links .dropdown-menu a { color:#24252a; }
        .nav__links .dropdown-menu a.active { background:rgba(4,172,133,.9); color:#fff; }
        .content { padding:24px; }
        .topbar { display:none; }
        .hero { background:#fff; border:1px solid var(--line); border-radius:8px; padding:22px; display:flex; justify-content:space-between; gap:18px; margin-bottom:14px; }
        .module { color:var(--accent); font-weight:900; text-transform:uppercase; font-size:12px; }
        h1 { font-size:31px; font-weight:900; margin:5px 0 8px; }
        .lead { color:var(--muted); margin:0; max-width:760px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-main, .btn-ghost { border-radius:50px; padding:10px 18px; text-decoration:none; font-family:"Montserrat", sans-serif; font-weight:500; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; }
        .btn-main { background:rgba(4,172,133,.8); color:#edf0f1; }
        .btn-main:hover { background:rgba(4,172,133,1); color:#edf0f1; text-decoration:none; }
        .btn-ghost { background:#fff; color:var(--ink); border:1px solid var(--line); }
        .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
        .metric { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; min-height:88px; }
        .metric strong { display:block; font-size:27px; }
        .metric span { color:var(--muted); font-size:13px; text-transform:capitalize; }
        .panels { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; min-height:180px; }
        .panel h2 { font-size:17px; font-weight:900; margin:0 0 12px; }
        .line { display:flex; justify-content:space-between; gap:10px; border-bottom:1px solid #eee9dc; padding:8px 0; font-size:14px; }
        .line:last-child { border-bottom:0; }
        .muted { color:var(--muted); }
        .chip { display:inline-flex; background:var(--soft); color:var(--accent); border-radius:999px; padding:5px 9px; font-size:12px; font-weight:900; margin-top:10px; }
        @media (max-width: 880px) { .content { padding:14px; } .hero { display:block; } .actions { justify-content:flex-start; margin-top:14px; } .metrics,.panels { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <a class="logo" href="{{ url('/') }}"><img src="{{ asset('images/logo/2.svg') }}" alt="logo" height="40em"></a>
    <nav><ul class="nav__links">@include('partials.portal-navbar')</ul></nav>
    @include('partials.user-navbar-menu')
</header>
<div class="teacher-shell">
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
