<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CC Control')</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        :root { --ink:#17211b; --muted:#66746b; --line:#dfe7e1; --green:#2f7d4c; --mint:#e8f5ec; --amber:#f7b731; --blue:#2f80ed; --bg:#f6f8f5; }
        body { background: var(--bg); color: var(--ink); font-family: Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .app-shell { max-width: 1180px; margin: 0 auto; padding: 18px 16px 92px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; }
        .brand { display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:800; font-size:20px; text-decoration:none; }
        .brand-mark { width:36px; height:36px; border-radius:8px; background:var(--green); color:#fff; display:grid; place-items:center; font-weight:900; }
        .top-actions { display:flex; align-items:center; gap:8px; }
        .icon-btn { width:38px; height:38px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--ink); display:grid; place-items:center; text-decoration:none; font-weight:800; }
        .screen-grid { display:grid; grid-template-columns: 280px minmax(0,1fr); gap:18px; }
        .side-nav { background:#fff; border:1px solid var(--line); border-radius:8px; padding:10px; align-self:start; position:sticky; top:14px; max-height:calc(100vh - 28px); overflow:auto; }
        .nav-item { display:block; padding:9px 10px; border-radius:7px; color:var(--muted); text-decoration:none; font-size:14px; }
        .nav-item:hover, .nav-item.active { background:var(--mint); color:var(--green); text-decoration:none; }
        .mobile-nav { position:fixed; left:0; right:0; bottom:0; background:#fff; border-top:1px solid var(--line); display:none; grid-template-columns:repeat(5,1fr); z-index:20; }
        .mobile-nav a { padding:9px 4px; text-align:center; color:var(--muted); font-size:11px; text-decoration:none; }
        .mobile-nav strong { display:block; font-size:18px; line-height:1; color:var(--ink); }
        .hero { background:#fff; border:1px solid var(--line); border-radius:8px; padding:20px; margin-bottom:14px; }
        .module { color:var(--green); font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:0; }
        h1 { font-size:30px; line-height:1.15; margin:6px 0 8px; font-weight:900; }
        .lead { color:var(--muted); font-size:16px; margin:0; }
        .actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; }
        .btn-main { background:var(--green); color:#fff; border:0; border-radius:8px; padding:10px 14px; font-weight:800; text-decoration:none; }
        .btn-ghost { background:#fff; color:var(--ink); border:1px solid var(--line); border-radius:8px; padding:10px 14px; font-weight:800; text-decoration:none; }
        .metric-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .metric { background:#fff; border:1px solid var(--line); border-radius:8px; padding:12px; min-height:86px; }
        .metric-value { font-size:24px; font-weight:900; }
        .metric-label { color:var(--muted); font-size:12px; margin-top:4px; }
        .panel-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px; min-height:154px; }
        .panel h2 { font-size:16px; font-weight:900; margin:0 0 10px; }
        .empty { border:1px dashed #cbd8ce; border-radius:8px; padding:12px; color:var(--muted); background:#fbfcfb; font-size:14px; }
        .rowline { display:flex; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px solid #edf2ee; font-size:14px; }
        .rowline:last-child { border-bottom:0; }
        .chip { display:inline-flex; align-items:center; border-radius:999px; background:var(--mint); color:var(--green); padding:4px 8px; font-size:12px; font-weight:800; }
        @media (max-width: 860px) {
            .app-shell { padding:14px 12px 78px; }
            .screen-grid { display:block; }
            .side-nav { display:none; }
            .mobile-nav { display:grid; }
            .metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .panel-grid { grid-template-columns:1fr; }
            h1 { font-size:25px; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <div class="topbar">
        <a class="brand" href="{{ url('/app') }}"><span class="brand-mark">CC</span><span>Control</span></a>
        <div class="top-actions">
            <a class="icon-btn" href="{{ url('/app/notifications') }}" title="Notificaciones">!</a>
            @guest
                <a class="btn-ghost" href="{{ route('login') }}">Ingresar</a>
            @else
                <a class="icon-btn" href="{{ url('/app/profile') }}" title="Perfil">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</a>
            @endguest
        </div>
    </div>
    @yield('content')
</div>
<nav class="mobile-nav">
    <a href="{{ url('/app') }}"><strong>⌂</strong>Inicio</a>
    <a href="{{ url('/app/stock') }}"><strong>□</strong>Stock</a>
    <a href="{{ url('/app/planner') }}"><strong>▦</strong>Plan</a>
    <a href="{{ url('/app/shopping-list') }}"><strong>✓</strong>Compras</a>
    <a href="{{ url('/app/profile') }}"><strong>○</strong>Perfil</a>
</nav>
</body>
</html>
