@extends('layouts.web-user')

@section('title', $screen['title'].' - CC Control Web')

@section('nav')
    @foreach($screens as $key => $item)
        <a class="nav-link-web {{ $screenKey === $key ? 'active' : '' }}" href="{{ url('/web/'.$key) }}">{{ $item['title'] }}</a>
    @endforeach
@endsection

@section('content')
    <section class="hero">
        <div>
            <div class="module">{{ $screen['module'] }}</div>
            <h1>{{ $screen['title'] }}</h1>
            <p class="lead">{{ $screen['description'] }}</p>
        </div>
        <div class="actions">
            <a href="#" class="btn-main">{{ $screen['primary'] }}</a>
            <a href="#" class="btn-secondary-web">{{ $screen['secondary'] }}</a>
        </div>
    </section>

    <section class="metric-row">
        @foreach($screen['metrics'] as $metric)
            <article class="metric">
                <strong>{{ $stats[$metric] ?? 0 }}</strong>
                <span>{{ str_replace('_', ' ', $metric) }}</span>
            </article>
        @endforeach
    </section>

    <section class="workspace">
        <div class="panel-grid">
            @foreach($screen['panels'] as $panel)
                <article class="panel">
                    <h2>{{ $panel }}</h2>
                    <div class="table-line"><span class="muted">Estado</span><strong>Preparado</strong></div>
                    <div class="table-line"><span class="muted">Datos conectados</span><strong>Base local</strong></div>
                    <div class="table-line"><span class="muted">Siguiente paso</span><strong>CRUD</strong></div>
                    <div style="margin-top:12px">
                        <span class="chip">{{ $screen['module'] }}</span>
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="aside-panel">
            <h2>Panel de trabajo</h2>
            <div class="table-line"><span class="muted">Pantalla</span><strong>{{ $screen['title'] }}</strong></div>
            <div class="table-line"><span class="muted">Modulo</span><strong>{{ $screen['module'] }}</strong></div>
            <div class="table-line"><span class="muted">Ruta</span><strong>/web/{{ $screenKey }}</strong></div>
            <p class="muted" style="margin-top:14px">Esta vista queda lista como pantalla web de usuario para conectar formularios, tablas, filtros, graficos y acciones reales.</p>
        </aside>
    </section>
@endsection
