@extends('layouts.teacher-web')

@section('title', $screen['title'].' - Docente CC Control')

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

    <section class="panels">
        @foreach($screen['panels'] as $panel)
            <article class="panel">
                <h2>{{ $panel }}</h2>
                <div class="line"><span class="muted">Estado</span><strong>Disponible</strong></div>
                <div class="line"><span class="muted">Origen</span><strong>Docs/demo</strong></div>
                <div class="line"><span class="muted">Uso</span><strong>Evaluacion</strong></div>
                <span class="chip">{{ $screen['module'] }}</span>
            </article>
        @endforeach
    </section>
@endsection
