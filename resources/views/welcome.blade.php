@extends('layouts.app')

@section('page_styles')
<style>
    :root {
        --green: #04ac85;
        --green-dark: #087760;
        --ink: #24252a;
        --muted: #52625b;
        --line: #dce6e1;
        --soft: #f4f8f6;
        --yellow: #f2bd45;
    }

    .home-landing {
        margin: -3rem 0;
        overflow: hidden;
        color: var(--ink);
        background: #fff;
        font-family: 'Nunito', sans-serif;
    }

    .home-landing * { box-sizing: border-box; }
    .home-landing h1, .home-landing h2, .home-landing h3 { letter-spacing: 0; text-wrap: balance; }
    .home-landing a:focus-visible { outline: 3px solid #087760; outline-offset: 5px; }
    .download-band a:focus-visible { outline-color: #fff; }

    .home-container {
        width: min(1180px, calc(100% - 48px));
        margin: 0 auto;
    }

    .home-hero {
        position: relative;
        min-height: 610px;
        display: flex;
        align-items: center;
        background: #f5f7f6 url('{{ asset('images/landing-hero.png') }}') center / cover no-repeat;
    }

    .home-hero-copy {
        width: min(610px, 48%);
        padding: 100px 0 130px;
    }

    .home-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        color: var(--green-dark);
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .home-eyebrow::before {
        content: '';
        width: 32px;
        height: 3px;
        flex-shrink: 0;
        background: var(--green);
    }

    .home-hero h1 {
        margin: 0 0 24px;
        font-size: 46px;
        line-height: 1.12;
        font-weight: 900;
    }

    .home-hero h1 span { color: var(--green-dark); }

    .home-hero-copy > p {
        max-width: 550px;
        margin: 0 0 34px;
        color: #4e5b57;
        font-size: 19px;
        line-height: 1.65;
    }

    .home-actions { display: flex; flex-wrap: wrap; gap: 12px; }

    .home-button {
        display: inline-flex;
        min-height: 50px;
        align-items: center;
        justify-content: center;
        padding: 12px 22px;
        border: 1px solid transparent;
        border-radius: 4px;
        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none !important;
    }

    .home-button-primary { color: #fff !important; background: var(--green-dark); box-shadow: 0 12px 28px rgba(4,172,133,.16); }
    .home-button-primary:hover { background: #065a49; }
    .home-button-light { color: var(--ink) !important; border-color: #afbeb7; background: rgba(255,255,255,.88); }

    .home-hero-strip {
        position: absolute;
        inset: auto 0 0;
        color: #fff;
        background: rgba(36,37,42,.96);
    }

    .home-strip-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
    }

    .home-strip-item {
        min-height: 88px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 14px 26px;
        border-right: 1px solid rgba(255,255,255,.13);
    }

    .home-strip-item:first-child { padding-left: 0; }
    .home-strip-item:last-child { border: 0; }
    .home-strip-item strong { font-size: 15px; }
    .home-strip-item span { margin-top: 3px; color: #b9c4c0; font-size: 12px; }

    .home-section { padding: 80px 0; }
    .home-section-soft { background: var(--soft); }

    .home-heading {
        max-width: 860px;
        margin: 0 auto 54px;
        text-align: center;
    }

    .home-heading .home-eyebrow { margin-bottom: 17px; }
    .home-heading h2 { margin: 0 0 18px; font-size: 38px; line-height: 1.2; font-weight: 900; }
    .home-heading p { margin: 0 auto; max-width: 680px; color: var(--muted); font-size: 17px; line-height: 1.7; }

    .home-steps {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-block: 1px solid var(--line);
    }

    .home-step { padding: 30px 28px 36px; border-right: 1px solid var(--line); }
    .home-step:last-child { border: 0; }
    .home-step-number { display: block; margin-bottom: 28px; color: var(--green-dark); font-size: 13px; font-weight: 900; }
    .home-step h3 { margin: 0 0 9px; font-size: 20px; font-weight: 900; }
    .home-step p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }

    .home-demo-link { margin: 28px 0 0; text-align: center; }
    .home-demo-link a, .home-demo a { color: var(--green-dark); font-weight: 800; text-decoration: underline; }
    .home-demos { scroll-margin-top: 80px; border-top: 1px solid var(--line); }
    .home-demos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 480px), 1fr)); gap: 40px; }
    .home-demo { min-width: 0; width: 100%; max-width: 960px; margin: 0 auto; }
    .home-demo-header { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
    .home-demo h3 { margin: 0; font-size: 24px; line-height: 1.3; font-weight: 900; }
    .home-demo-duration { color: var(--muted); font-size: 14px; white-space: nowrap; }
    .home-demo video { display: block; width: 100%; aspect-ratio: 16 / 9; height: auto; background: #202326; object-fit: contain; border-radius: 6px; }
    .home-demo video:focus-visible, .home-demo summary:focus-visible { outline: 3px solid var(--green-dark); outline-offset: 4px; }
    .home-demo-description { margin: 14px 0 10px; color: var(--muted); font-size: 15px; line-height: 1.6; }
    .home-demo details { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--line); font-size: 15px; line-height: 1.65; }
    .home-demo summary { color: var(--green-dark); font-weight: 800; cursor: pointer; }
    .home-demo ol { padding-left: 24px; margin: 14px 0; }
    .home-demo li + li { margin-top: 5px; }
    .home-demo-note { color: var(--muted); }

    .screens-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, .8fr);
        gap: 44px;
        align-items: center;
    }

    .screen-copy { min-width: 0; }
    .screen-copy h2 { margin: 0 0 20px; font-size: 32px; line-height: 1.2; font-weight: 900; }
    .screen-copy > p { margin: 0; color: var(--muted); font-size: 17px; line-height: 1.7; }

    .screen-points { display: grid; gap: 18px; margin-top: 32px; }
    .screen-point { display: grid; grid-template-columns: 36px 1fr; gap: 13px; align-items: start; }
    .screen-point-mark { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 50%; color: #fff; background: var(--green-dark); font-size: 12px; font-weight: 900; }
    .screen-point strong { display: block; margin-bottom: 3px; }
    .screen-point > div > span { color: var(--muted); font-size: 14px; }

    .browser-shot {
        min-width: 0;
        overflow: hidden;
        border: 1px solid #cfdad5;
        border-radius: 7px;
        background: #fff;
        box-shadow: 0 26px 65px rgba(36,37,42,.13);
    }

    .browser-bar {
        height: 42px;
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0 15px;
        border-bottom: 1px solid var(--line);
        background: #eef3f0;
    }

    .browser-dot { width: 9px; height: 9px; border-radius: 50%; background: #b8c5bf; }
    .browser-address { width: 48%; height: 12px; margin-left: 12px; border-radius: 2px; background: #dce5e1; }

    .screen-capture { display: block; }
    .screen-capture img { display: block; width: 100%; height: auto; object-fit: contain; }
    .screen-copy .screen-note { margin-top: 16px; color: #5c6b67; font-size: 13px; line-height: 1.5; }

    .mobile-showcase {
        display: grid;
        grid-template-columns: minmax(310px, .75fr) minmax(0, 1.25fr);
        gap: 80px;
        align-items: center;
    }

    .mobile-stage {
        padding: 32px 16px;
        position: relative;
        display: grid;
        place-items: center;
        overflow: hidden;
        border-radius: 6px;
        background: url('{{ asset('images/background/1.jpg') }}') center / cover no-repeat;
    }

    .mobile-stage::before { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,.34); }

    .phone-frame {
        position: relative;
        z-index: 1;
        width: 286px;
        max-width: 100%;
        border: 8px solid var(--ink);
        border-radius: 28px;
        background: var(--ink);
        box-shadow: 0 30px 60px rgba(36,37,42,.3);
    }

    .phone-frame img { border-radius: 20px; }

    .filler-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr .9fr;
        grid-template-rows: 360px;
        gap: 18px;
        margin-top: 70px;
    }

    .filler-image {
        position: relative;
        overflow: hidden;
        border-radius: 5px;
        background-position: center;
        background-size: cover;
    }

    .filler-image:nth-child(1) { background-image: url('{{ asset('images/landing-hero.png') }}'); background-position: 72% center; }
    .filler-image:nth-child(2) { background-image: url('{{ asset('images/background/1.jpg') }}'); }
    .filler-image:nth-child(3) { background-image: url('{{ asset('images/background/3.jpg') }}'); }
    .filler-image::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 52%, rgba(36,37,42,.75)); }
    .filler-image span { position: absolute; z-index: 1; left: 22px; bottom: 18px; color: #fff; font-size: 14px; font-weight: 800; }

    .download-band { color: #fff; background: var(--ink); }
    .download-inner { min-height: 300px; padding-block: 56px; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 48px; align-items: center; }
    .download-copy h2 { margin: 0 0 14px; font-size: 36px; font-weight: 900; }
    .download-copy p { max-width: 680px; margin: 0; color: #d8e1dc; font-size: 16px; line-height: 1.65; }
    .download-copy .download-version { margin-top: 16px; color: #fff; font-size: 14px; font-weight: 800; }
    .download-actions { display: flex; flex-direction: column; gap: 12px; }
    .home-button-outline { color: #fff !important; border-color: #66736e; background: transparent; }

    @media (max-width: 980px) {
        .home-hero { min-height: 680px; background-position: 60% center; }
        .home-hero-copy { width: 60%; }
        .home-strip-grid { grid-template-columns: repeat(2, 1fr); }
        .home-strip-item:nth-child(2) { border-right: 0; }
        .home-steps { grid-template-columns: repeat(2, 1fr); }
        .home-step:nth-child(2) { border-right: 0; }
        .home-step:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        .screens-layout, .mobile-showcase { grid-template-columns: 1fr; gap: 42px; }
        .screen-copy { max-width: 720px; padding: 0; }
        .mobile-stage { width: min(620px, 100%); margin: 0 auto; }
        .filler-grid { grid-template-columns: 1fr 1fr; grid-template-rows: 340px 300px; }
        .filler-image:first-child { grid-column: 1 / -1; }
        .download-inner { grid-template-columns: 1fr; gap: 28px; padding: 65px 0; }
    }

    @media (max-width: 680px) {
        .home-container { width: calc(100% - 32px); }
        .home-hero { min-height: 680px; align-items: flex-start; background-position: 68% bottom; }
        .home-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(255,255,255,.92) 52%, rgba(255,255,255,.18) 78%); }
        .home-hero-copy { position: relative; z-index: 1; width: 100%; padding: 48px 0 245px; text-align: center; }
        .home-eyebrow { justify-content: center; }
        .home-hero h1 { font-size: 34px; }
        .home-hero-copy > p { font-size: 16px; }
        .home-actions { flex-direction: column; }
        .home-hero-strip { z-index: 2; }
        .home-strip-item { padding: 12px 10px; }
        .home-strip-item:first-child { padding-left: 10px; }
        .home-section { padding: 56px 0; }
        .home-heading { margin-bottom: 38px; }
        .home-heading h2, .screen-copy h2, .download-copy h2 { font-size: 28px; }
        .home-steps { grid-template-columns: 1fr; }
        .home-step { border-right: 0; border-bottom: 1px solid var(--line); }
        .home-step:nth-child(3) { border-bottom: 1px solid var(--line); }
        .mobile-showcase { gap: 34px; }
        .phone-frame { width: 268px; }
        .filler-grid { grid-template-columns: 1fr; grid-template-rows: repeat(3, 260px); margin-top: 48px; }
        .filler-image:first-child { grid-column: auto; }
        .download-actions { flex-direction: column; }
    }
</style>
@endsection

@section('content')
@php
    $demoVideos = array_filter(config('demos.videos', []), function ($demo) {
        return !empty($demo['file']) && is_file(public_path($demo['file']));
    });
@endphp
<div class="home-landing">
    <section class="home-hero">
        <div class="home-container">
            <div class="home-hero-copy">
                <div class="home-eyebrow">Tu cocina, tu presupuesto, tu hogar</div>
                <h1>Cocina<wbr>Comida<wbr><span>Control</span></h1>
                <p>Organizá el stock, planificá las comidas y cuidá el presupuesto familiar desde una sola plataforma.</p>
                <div class="home-actions">
                    <a class="home-button home-button-primary" href="{{ route('login') }}">Ingresar</a>
                    <a class="home-button home-button-light" href="#producto">Descubrir cómo funciona</a>
                    <a class="home-button home-button-light" href="#descarga-app">App para Android</a>
                </div>
            </div>
        </div>

        <div class="home-hero-strip">
            <div class="home-container home-strip-grid">
                <div class="home-strip-item"><strong>Stock inteligente</strong><span>Cantidades y vencimientos</span></div>
                <div class="home-strip-item"><strong>Recetas posibles</strong><span>Con lo que ya tenés</span></div>
                <div class="home-strip-item"><strong>Plan semanal</strong><span>Para todo el grupo familiar</span></div>
                <div class="home-strip-item"><strong>Compras y presupuesto</strong><span>Todo bajo control</span></div>
            </div>
        </div>
    </section>

    <section id="producto" class="home-section">
        <div class="home-container">
            <header class="home-heading">
                <div class="home-eyebrow">Una decisión conecta con la siguiente</div>
                <h2>Del stock de tu casa a la mesa.</h2>
                <p>CocinaComidaControl reúne información que normalmente está dispersa y la convierte en un recorrido simple.</p>
            </header>

            <div class="home-steps">
                <article class="home-step"><span class="home-step-number">01</span><h3>Registrá</h3><p>Productos, cantidades, ubicaciones y vencimientos.</p></article>
                <article class="home-step"><span class="home-step-number">02</span><h3>Elegí</h3><p>Recetas compatibles con tu stock y tus preferencias.</p></article>
                <article class="home-step"><span class="home-step-number">03</span><h3>Planificá</h3><p>Comidas y porciones para cada integrante del hogar.</p></article>
                <article class="home-step"><span class="home-step-number">04</span><h3>Comprá</h3><p>Sólo lo necesario, comparando precios y presupuesto.</p></article>
            </div>
            @if($demoVideos)
                <p class="home-demo-link"><a href="#demos">Ver el recorrido en video</a></p>
            @endif
        </div>
    </section>

    @if($demoVideos)
        <section id="demos" class="home-section home-demos" aria-labelledby="demos-title">
            <div class="home-container">
                <header class="home-heading">
                    <div class="home-eyebrow">CocinaComidaControl en acción</div>
                    <h2 id="demos-title">Mirá cómo funciona.</h2>
                    <p>De organizar tu hogar a registrar lo que comprás y cocinás: un recorrido con datos de ejemplo.</p>
                </header>
                <div class="home-demos-grid">
                    @foreach($demoVideos as $platform => $demo)
                        <article class="home-demo" aria-labelledby="demo-{{ $platform }}-title">
                            <header class="home-demo-header">
                                <h3 id="demo-{{ $platform }}-title">{{ $demo['title'] }}</h3>
                                @if(!empty($demo['duration']))
                                    <span class="home-demo-duration">{{ $demo['duration'] }} min</span>
                                @endif
                            </header>
                            <video controls playsinline preload="none" aria-labelledby="demo-{{ $platform }}-title" aria-describedby="demo-{{ $platform }}-description"
                                @if(!empty($demo['poster']) && is_file(public_path($demo['poster']))) poster="{{ asset($demo['poster']) }}" @endif>
                                <source src="{{ asset($demo['file']) }}" type="video/mp4">
                                <a href="{{ asset($demo['file']) }}">Abrir {{ $demo['title'] }}</a>
                            </video>
                            <p id="demo-{{ $platform }}-description" class="home-demo-description">{{ $demo['description'] }}</p>
                            <a href="{{ asset($demo['file']) }}" download>Descargar {{ $demo['title'] }} (MP4)</a>
                            @if(!empty($demo['steps']))
                                <details>
                                    <summary>Leer el recorrido</summary>
                                    <ol>
                                        @foreach($demo['steps'] as $step)
                                            <li>{{ $step }}</li>
                                        @endforeach
                                    </ol>
                                    @if(!empty($demo['note']))
                                        <p class="home-demo-note">{{ $demo['note'] }}</p>
                                    @endif
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="home-section home-section-soft">
        <div class="home-container screens-layout">
            <div class="browser-shot">
                <div class="browser-bar"><i class="browser-dot"></i><i class="browser-dot"></i><i class="browser-dot"></i><span class="browser-address"></span></div>
                <a class="screen-capture" href="{{ asset('images/landing/web-dashboard-martin.jpg') }}" target="_blank" rel="noopener" aria-label="Ampliar captura del resumen del hogar en la web">
                    <img src="{{ asset('images/landing/web-dashboard-martin.jpg') }}" alt="Resumen del hogar en la web con Martín López y datos de ejemplo." width="1396" height="612" loading="lazy" decoding="async">
                </a>
            </div>

            <div class="screen-copy">
                <div class="home-eyebrow">Versión web</div>
                <h2>La vista completa de tu hogar.</h2>
                <p>Un escritorio cómodo para administrar productos, recetas, miembros, compras y reportes con toda la información visible.</p>
                <p class="screen-note">Captura web con datos de ejemplo.</p>
                <div class="screen-points">
                    <div class="screen-point"><span class="screen-point-mark">1</span><div><strong>Gestión clara</strong><span>Más espacio para revisar y editar.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">2</span><div><strong>Información conectada</strong><span>Salud, economía, tiempo y stock.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">3</span><div><strong>Permisos por rol</strong><span>Accesos para usuarios, profesionales y administradores.</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="home-container mobile-showcase">
            <div class="mobile-stage">
                <div class="phone-frame">
                    <a class="screen-capture" href="{{ asset('images/landing/mobile-home-preview-martin.jpg') }}" target="_blank" rel="noopener" aria-label="Ampliar vista previa móvil simulada en navegador">
                        <img src="{{ asset('images/landing/mobile-home-preview-martin.jpg') }}" alt="Vista previa móvil simulada en navegador con Martín López y Hogar López; datos de ejemplo." width="391" height="845" loading="lazy" decoding="async">
                    </a>
                </div>
            </div>

            <div class="screen-copy">
                <div class="home-eyebrow">Aplicación móvil</div>
                <h2>Tu hogar, también en el celular.</h2>
                <p>Consultá qué falta, revisá tu lista y registrá productos desde el teléfono mientras comprás o cocinás.</p>
                <p class="screen-note">Vista previa móvil simulada en navegador, con datos de ejemplo.</p>
                <div class="screen-points">
                    <div class="screen-point"><span class="screen-point-mark">1</span><div><strong>En el supermercado</strong><span>Lista, escaneo y comparación.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">2</span><div><strong>En la cocina</strong><span>Recetas, pasos y porciones.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">3</span><div><strong>Tu información compartida</strong><span>La misma cuenta en la web y en la app.</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-section-soft">
        <div class="home-container">
            <header class="home-heading">
                <div class="home-eyebrow">Pensada para la vida real</div>
                <h2>Menos desperdicio. Más organización.</h2>
                <p>Una experiencia que acompaña las decisiones cotidianas sin perder de vista la salud ni el bolsillo.</p>
            </header>

            <div class="filler-grid">
                <div class="filler-image"><span>Planificar con lo que ya tenés</span></div>
                <div class="filler-image"><span>Elegir alimentos variados</span></div>
                <div class="filler-image"><span>Ordenar la cocina y el hogar</span></div>
            </div>
        </div>
    </section>

    <section id="descarga-app" class="download-band">
        <div class="home-container download-inner">
            <div class="download-copy">
                <h2>Llevá tu cocina con vos.</h2>
                @if(config('mobile.android.download_url'))
                    <p>Descargá la versión de prueba para Android y entrá con tu misma cuenta. Tu hogar y tus listas, también en el celular.</p>
                    <p class="download-version">Android · Versión {{ config('mobile.android.version') }} ({{ config('mobile.android.build') }}) · {{ number_format(config('mobile.android.size_bytes') / 1000000, 0) }} MB · En pruebas</p>
                @else
                    <p>Estamos preparando la descarga para Android. Mientras tanto, podés usar tu cuenta desde la web.</p>
                @endif
            </div>
            <div class="download-actions">
                @if(config('mobile.android.download_url'))
                    <a class="home-button home-button-primary" href="{{ route('downloads.android') }}">Descargar APK {{ config('mobile.android.version') }}</a>
                @endif
                <a class="home-button home-button-outline" href="{{ route('login') }}">Ingresar a la web</a>
            </div>
        </div>
    </section>
</div>
@endsection
