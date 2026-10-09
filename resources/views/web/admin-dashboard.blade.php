<div class="admin-dashboard" data-admin-dashboard>
    <header class="hero admin-dashboard-hero">
        <div>
            <div class="module">Administración</div>
            <h1>{{ $screen['title'] }}</h1>
            <p class="lead">{{ $screen['description'] }}</p>
        </div>
    </header>

    @if(count($dashboard['sections'] ?? []) > 1)
        <nav class="admin-dashboard-navigation" aria-label="Secciones del resumen">
            <ul role="list">
                @foreach($dashboard['sections'] as $section)
                    <li><a href="#admin-dashboard-section-{{ $section['key'] }}">{{ $section['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    @forelse(($dashboard['sections'] ?? []) as $section)
        <section class="admin-dashboard-section" aria-labelledby="admin-dashboard-section-{{ $section['key'] }}" data-dashboard-section="{{ $section['key'] }}">
            <header class="admin-dashboard-section-header">
                <h2 id="admin-dashboard-section-{{ $section['key'] }}" tabindex="-1">{{ $section['title'] }}</h2>
                <p>{{ $section['description'] }}</p>
            </header>
            <ul class="admin-dashboard-cards" role="list">
                @foreach($section['cards'] as $card)
                    @php($cardId = 'admin-dashboard-'.$section['key'].'-'.$card['key'])
                    <li class="admin-dashboard-card" data-dashboard-card="{{ $card['key'] }}">
                        <dl>
                            <dt id="{{ $cardId }}-label">{{ $card['label'] }}</dt>
                            <dd class="admin-dashboard-value" data-dashboard-value="{{ $card['value'] }}">{{ number_format($card['value'], 0, ',', '.') }}</dd>
                        </dl>
                        <p class="admin-dashboard-description" id="{{ $cardId }}-description">{{ $card['description'] }}</p>
                        @if(!empty($card['url']))
                            <a class="btn-ghost admin-dashboard-link" href="{{ $card['url'] }}" aria-label="Ver listado: {{ $card['label'] }}" aria-describedby="{{ $cardId }}-description">
                                Ver listado <span aria-hidden="true">→</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="admin-dashboard-empty" aria-labelledby="admin-dashboard-empty-title">
            <h2 id="admin-dashboard-empty-title">Sin resúmenes disponibles</h2>
            <p>No hay resúmenes disponibles para tus permisos actuales.</p>
        </section>
    @endforelse
</div>
