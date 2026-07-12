@extends('layouts.mobile-app')

@section('title', $screen['title'].' - CC Control')

@section('content')
<div class="screen-grid">
    <aside class="side-nav">
        @foreach($screens as $key => $item)
            <a class="nav-item {{ $screenKey === $key ? 'active' : '' }}" href="{{ url('/app/'.$key) }}">{{ $item['title'] }}</a>
        @endforeach
    </aside>

    <main>
        <section class="hero">
            <div class="module">{{ $screen['module'] }}</div>
            <h1>{{ $screen['title'] }}</h1>
            <p class="lead">{{ $screen['description'] }}</p>
            <div class="actions">
                <a class="btn-main" href="#">{{ $screen['primary'] }}</a>
                <a class="btn-ghost" href="#">{{ $screen['secondary'] }}</a>
            </div>
        </section>

        <section class="metric-grid">
            @foreach($screen['metrics'] as $metric)
                <div class="metric">
                    <div class="metric-value">{{ $stats[$metric] ?? 0 }}</div>
                    <div class="metric-label">{{ str_replace('_', ' ', $metric) }}</div>
                </div>
            @endforeach
        </section>

        <section class="panel-grid">
            @foreach($screen['sections'] as $section)
                <article class="panel">
                    <h2>{{ $section }}</h2>
                    @if($screenKey === 'dashboard' && $loop->first)
                        <div class="rowline"><span>Plan activo</span><strong>{{ $stats['meal_plans'] }}</strong></div>
                        <div class="rowline"><span>Recetas disponibles</span><strong>{{ $stats['recipes'] }}</strong></div>
                        <div class="rowline"><span>Items en stock</span><strong>{{ $stats['stock_items'] }}</strong></div>
                    @elseif($screenKey === 'stock')
                        <div class="rowline"><span>Productos cargados</span><strong>{{ $stats['stock_items'] }}</strong></div>
                        <div class="rowline"><span>Por vencer</span><strong>{{ $stats['expiring_items'] }}</strong></div>
                        <span class="chip">Stock familiar</span>
                    @elseif($screenKey === 'budget')
                        <div class="rowline"><span>Presupuestos</span><strong>{{ $stats['budgets'] }}</strong></div>
                        <div class="rowline"><span>Compras confirmadas</span><strong>{{ $stats['purchases'] }}</strong></div>
                        <span class="chip">Mensual</span>
                    @elseif($screenKey === 'recipe-search' || $screenKey === 'cook-now' || $screenKey === 'almost-recipes')
                        <div class="rowline"><span>Recetas</span><strong>{{ $stats['recipes'] }}</strong></div>
                        <div class="rowline"><span>Tags</span><strong>{{ $stats['food_tags'] }}</strong></div>
                        <span class="chip">Filtros activos</span>
                    @else
                        <div class="empty">Vista preparada para conectar formularios, tablas y acciones del modulo.</div>
                    @endif
                </article>
            @endforeach
        </section>
    </main>
</div>
@endsection
