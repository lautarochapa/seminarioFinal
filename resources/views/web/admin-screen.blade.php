@extends('layouts.admin-web')

@section('title', $screen['title'].' - Admin CC Control')

@section('nav')
    @foreach($screens as $key => $item)
        <a class="nav-link-admin {{ $screenKey === $key ? 'active' : '' }}" href="{{ url('/admin-web/'.$key) }}">{{ $item['title'] }}</a>
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
            <a href="#" class="btn-ghost">{{ $screen['secondary'] }}</a>
        </div>
    </section>

    <section class="metrics">
        @foreach($screen['metrics'] as $metric)
            <article class="metric">
                <strong>{{ $stats[$metric] ?? 0 }}</strong>
                <span>{{ str_replace('_', ' ', $metric) }}</span>
            </article>
        @endforeach
    </section>

    <section class="grid">
        @foreach($screen['panels'] as $panel)
            <article class="panel">
                <h2>{{ $panel }}</h2>
                <div class="line"><span class="muted">Estado</span><strong>Listo</strong></div>
                <div class="line"><span class="muted">Origen</span><strong>PostgreSQL</strong></div>
                <div class="line"><span class="muted">Accion</span><strong>ABM</strong></div>
                <span class="chip">{{ $screen['module'] }}</span>
            </article>
        @endforeach
    </section>
@endsection
