<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin CC Control')</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        :root { --bg:#f4f6f8; --surface:#fff; --ink:#1d2329; --muted:#697681; --line:#dde3e8; --accent:#315f8f; --soft:#eaf2fb; --danger:#b33a3a; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Nunito, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .admin-shell { min-height:100vh; display:grid; grid-template-columns:280px minmax(0,1fr); }
        .sidebar { background:#16202a; color:#fff; padding:20px 14px; height:100vh; position:sticky; top:0; overflow:auto; }
        .brand { display:flex; gap:10px; align-items:center; color:#fff; text-decoration:none; font-size:20px; font-weight:900; margin-bottom:18px; }
        .brand-mark { width:38px; height:38px; border-radius:8px; display:grid; place-items:center; background:var(--accent); }
        .nav-link-admin { display:block; padding:9px 10px; border-radius:8px; color:#dce5ed; text-decoration:none; font-size:14px; }
        .nav-link-admin:hover, .nav-link-admin.active { background:rgba(255,255,255,.12); color:#fff; text-decoration:none; }
        .content { padding:22px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; gap:14px; }
        .search { background:#fff; border:1px solid var(--line); border-radius:8px; padding:10px 12px; min-width:360px; color:var(--muted); }
        .pill { background:#fff; border:1px solid var(--line); border-radius:8px; padding:9px 12px; font-weight:900; }
        .hero { background:#fff; border:1px solid var(--line); border-radius:8px; padding:20px; display:flex; justify-content:space-between; gap:18px; margin-bottom:14px; }
        .module { color:var(--accent); font-weight:900; text-transform:uppercase; font-size:12px; }
        h1 { margin:5px 0 8px; font-size:31px; font-weight:900; }
        .lead { color:var(--muted); margin:0; max-width:780px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-main, .btn-ghost { border-radius:8px; padding:10px 14px; text-decoration:none; font-weight:900; white-space:nowrap; }
        .btn-main { background:var(--accent); color:#fff; }
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
        @media (max-width: 860px) { .admin-shell { grid-template-columns:1fr; } .sidebar { height:auto; position:relative; } .content { padding:14px; } .hero,.topbar { display:block; } .search { min-width:0; width:100%; margin-bottom:10px; } .actions { justify-content:flex-start; margin-top:14px; } .grid,.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ url('/admin-web') }}"><span class="brand-mark">AD</span><span>Admin Web</span></a>
        @yield('nav')
    </aside>
    <main class="content">
        <div class="topbar">
            <div class="search">Buscar usuarios, productos, jobs, recetas o configuracion</div>
            <div class="pill">{{ Auth::check() ? Auth::user()->name : 'Admin' }}</div>
        </div>
        @yield('content')
    </main>
</div>
</body>
</html>
