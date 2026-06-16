<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CC Control Web')</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        :root { --bg:#f5f7f6; --surface:#fff; --ink:#17211b; --muted:#66746b; --line:#dde6df; --green:#2f7d4c; --green-soft:#e7f4eb; --blue:#2f80ed; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .web-shell { min-height:100vh; display:grid; grid-template-columns:260px minmax(0,1fr); }
        .sidebar { background:#102117; color:#fff; padding:22px 16px; position:sticky; top:0; height:100vh; overflow:auto; }
        .brand { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:900; font-size:20px; margin-bottom:24px; }
        .brand-mark { width:38px; height:38px; border-radius:8px; background:var(--green); display:grid; place-items:center; }
        .nav-label { color:#9ab0a2; font-size:12px; text-transform:uppercase; font-weight:800; margin:18px 10px 8px; }
        .nav-link-web { display:flex; align-items:center; gap:10px; color:#dce8df; padding:10px; border-radius:8px; text-decoration:none; font-size:14px; }
        .nav-link-web:hover, .nav-link-web.active { color:#fff; background:rgba(255,255,255,.12); text-decoration:none; }
        .content { padding:22px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:18px; }
        .search { background:#fff; border:1px solid var(--line); border-radius:8px; padding:10px 12px; min-width:320px; color:var(--muted); }
        .user-pill { background:#fff; border:1px solid var(--line); border-radius:8px; padding:9px 12px; font-weight:800; }
        .hero { background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:22px; display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:14px; }
        .module { color:var(--green); text-transform:uppercase; font-size:12px; font-weight:900; letter-spacing:0; }
        h1 { font-size:32px; line-height:1.15; margin:6px 0 8px; font-weight:900; }
        .lead { color:var(--muted); margin:0; max-width:760px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-main, .btn-secondary-web { border-radius:8px; padding:10px 14px; font-weight:900; text-decoration:none; white-space:nowrap; }
        .btn-main { background:var(--green); color:#fff; }
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
        @media (max-width: 960px) {
            .web-shell { grid-template-columns:1fr; }
            .sidebar { position:relative; height:auto; }
            .content { padding:14px; }
            .hero, .topbar { display:block; }
            .actions { justify-content:flex-start; margin-top:14px; }
            .search { min-width:0; width:100%; margin-bottom:10px; }
            .metric-row, .workspace, .panel-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="web-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ url('/web') }}"><span class="brand-mark">CC</span><span>Control Web</span></a>
        <div class="nav-label">Usuario</div>
        @yield('nav')
        <div class="nav-label">Otros</div>
        <a class="nav-link-web" href="{{ url('/app') }}">Prototipo mobile</a>
    </aside>
    <main class="content">
        <div class="topbar">
            <div class="search">Buscar productos, recetas, listas o reportes</div>
            <div class="user-pill">{{ Auth::check() ? Auth::user()->name : 'Invitado' }}</div>
        </div>
        @yield('content')
    </main>
</div>
</body>
</html>
