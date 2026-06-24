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
            <a href="{{ url('/teacher-web/functional-docs') }}" class="{{ request()->is('teacher-web/functional-docs') ? 'btn-main' : 'btn-ghost' }}">Documentación funcional</a>
            <a href="{{ url('/teacher-web/technical-docs') }}" class="{{ request()->is('teacher-web/technical-docs') ? 'btn-main' : 'btn-ghost' }}">Documentación técnica</a>
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

    @if($screenKey === 'functional-docs' || $screenKey === 'technical-docs')

        @php $docRoot = $screenKey === 'functional-docs' ? 'data-teacher-functional-docs' : 'data-teacher-technical-docs'; @endphp

        <div {{ $docRoot }} style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:14px;margin-top:4px">

            {{-- Columna izquierda: lista de documentos --}}
            <div>
                <div class="panel" style="padding:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px">
                        <h2 style="margin:0;font-size:15px">Documentos</h2>
                        <span style="font-size:11px;color:#716d64" data-docs-count></span>
                    </div>
                    <input type="search" data-docs-search placeholder="Buscar documento..."
                           style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #e3ded2;border-radius:6px;font-size:13px;margin-bottom:10px;outline:none">
                    <div class="alert" data-docs-message style="display:none"></div>
                    <div data-docs-list>
                        <p style="font-size:13px;color:#716d64;text-align:center;padding:16px 0">Cargando documentos...</p>
                    </div>
                </div>
            </div>

            {{-- Columna derecha: detalle + secciones --}}
            <div>
                <div class="panel" style="padding:14px">
                    {{-- Header documento seleccionado --}}
                    <div data-doc-detail>
                        <p style="font-size:13px;color:#716d64;text-align:center;padding:24px 0">Seleccioná un documento de la lista.</p>
                    </div>

                    {{-- Navegación de secciones --}}
                    <div data-section-nav-list style="border:1px solid #f0ede6;border-radius:6px;padding:6px;max-height:260px;overflow-y:auto;margin-bottom:4px"></div>

                    {{-- Contenido de sección seleccionada --}}
                    <div data-section-content style="display:none"></div>
                </div>
            </div>
        </div>

    @else

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

    @endif
@endsection
