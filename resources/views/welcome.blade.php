@extends('layouts.app')

@section('page_styles')
<style>
    :root {
        --green: #04ac85;
        --green-dark: #087760;
        --ink: #24252a;
        --muted: #66736e;
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

    .home-container {
        width: min(1180px, calc(100% - 48px));
        margin: 0 auto;
    }

    .home-hero {
        position: relative;
        min-height: 720px;
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
        background: var(--green);
    }

    .home-hero h1 {
        margin: 0 0 24px;
        font-size: clamp(48px, 5.3vw, 74px);
        line-height: 1.01;
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

    .home-button-primary { color: #fff !important; background: var(--green); box-shadow: 0 12px 28px rgba(4,172,133,.24); }
    .home-button-primary:hover { background: var(--green-dark); }
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

    .home-section { padding: 100px 0; }
    .home-section-soft { background: var(--soft); }

    .home-heading {
        max-width: 780px;
        margin: 0 auto 54px;
        text-align: center;
    }

    .home-heading .home-eyebrow { margin-bottom: 17px; }
    .home-heading h2 { margin: 0 0 18px; font-size: clamp(36px, 4vw, 54px); line-height: 1.08; font-weight: 900; }
    .home-heading p { margin: 0 auto; max-width: 680px; color: var(--muted); font-size: 17px; line-height: 1.7; }

    .home-steps {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-block: 1px solid var(--line);
    }

    .home-step { padding: 30px 28px 36px; border-right: 1px solid var(--line); }
    .home-step:last-child { border: 0; }
    .home-step-number { display: block; margin-bottom: 28px; color: var(--green); font-size: 13px; font-weight: 900; }
    .home-step h3 { margin: 0 0 9px; font-size: 20px; font-weight: 900; }
    .home-step p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }

    .screens-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(300px, .65fr);
        gap: 32px;
        align-items: center;
    }

    .screen-copy { padding-right: 30px; }
    .screen-copy h2 { margin: 0 0 20px; font-size: clamp(36px, 4vw, 52px); line-height: 1.08; font-weight: 900; }
    .screen-copy > p { margin: 0; color: var(--muted); font-size: 17px; line-height: 1.7; }

    .screen-points { display: grid; gap: 18px; margin-top: 32px; }
    .screen-point { display: grid; grid-template-columns: 36px 1fr; gap: 13px; align-items: start; }
    .screen-point-mark { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 50%; color: #fff; background: var(--green); font-size: 12px; font-weight: 900; }
    .screen-point strong { display: block; margin-bottom: 3px; }
    .screen-point span { color: var(--muted); font-size: 13px; }

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

    .screen-slot {
        position: relative;
        min-height: 520px;
        display: grid;
        place-items: center;
        overflow: hidden;
        background:
            linear-gradient(rgba(255,255,255,.78), rgba(255,255,255,.78)),
            url('{{ asset('images/background/2.jpg') }}') center / cover no-repeat;
    }

    .slot-message {
        max-width: 330px;
        padding: 30px;
        text-align: center;
        border: 1px dashed #9db0a7;
        border-radius: 5px;
        color: var(--muted);
        background: rgba(255,255,255,.88);
    }

    .slot-message strong { display: block; margin-bottom: 8px; color: var(--ink); font-size: 18px; }
    .slot-message span { font-size: 13px; line-height: 1.5; }

    .mobile-showcase {
        display: grid;
        grid-template-columns: minmax(310px, .75fr) minmax(0, 1.25fr);
        gap: 80px;
        align-items: center;
    }

    .mobile-stage {
        min-height: 560px;
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
        width: 270px;
        height: 500px;
        padding: 10px;
        border: 8px solid var(--ink);
        border-radius: 36px;
        background: var(--ink);
        box-shadow: 0 30px 60px rgba(36,37,42,.3);
    }

    .phone-slot {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        padding: 24px;
        border-radius: 22px;
        text-align: center;
        color: var(--muted);
        background: var(--soft);
    }

    .phone-slot strong { display: block; margin-bottom: 8px; color: var(--ink); font-size: 18px; }
    .phone-slot span { font-size: 13px; line-height: 1.5; }

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
    .download-inner { min-height: 300px; display: grid; grid-template-columns: 1fr auto; gap: 60px; align-items: center; }
    .download-copy h2 { margin: 0 0 14px; font-size: clamp(34px, 4vw, 50px); font-weight: 900; }
    .download-copy p { max-width: 680px; margin: 0; color: #bdc8c3; font-size: 16px; line-height: 1.65; }
    .download-actions { display: flex; gap: 12px; }
    .home-button-outline { color: #fff !important; border-color: #66736e; background: transparent; }

    @media (max-width: 980px) {
        .home-hero { min-height: 780px; background-position: 60% center; }
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
        .home-hero { min-height: 850px; align-items: flex-start; background-position: 68% bottom; }
        .home-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(255,255,255,.92) 52%, rgba(255,255,255,.18) 78%); }
        .home-hero-copy { position: relative; z-index: 1; width: 100%; padding: 68px 0 245px; text-align: center; }
        .home-eyebrow { justify-content: center; }
        .home-hero h1 { font-size: 43px; }
        .home-hero-copy > p { font-size: 16px; }
        .home-actions { flex-direction: column; }
        .home-hero-strip { z-index: 2; }
        .home-strip-item { padding: 12px 10px; }
        .home-strip-item:first-child { padding-left: 10px; }
        .home-section { padding: 70px 0; }
        .home-heading { margin-bottom: 38px; }
        .home-steps { grid-template-columns: 1fr; }
        .home-step { border-right: 0; border-bottom: 1px solid var(--line); }
        .home-step:nth-child(3) { border-bottom: 1px solid var(--line); }
        .screen-slot { min-height: 350px; }
        .mobile-showcase { gap: 34px; }
        .mobile-stage { min-height: 500px; }
        .phone-frame { width: 240px; height: 440px; }
        .filler-grid { grid-template-columns: 1fr; grid-template-rows: repeat(3, 260px); margin-top: 48px; }
        .filler-image:first-child { grid-column: auto; }
        .download-actions { flex-direction: column; }
    }
</style>
@endsection

@section('content')
<main class="home-landing">
    <section class="home-hero">
        <div class="home-container">
            <div class="home-hero-copy">
                <div class="home-eyebrow">CocinaComidaControl</div>
                <h1>Tu casa sabe qué hay. <span>Ahora también sabe qué cocinar.</span></h1>
                <p>Organizá el stock, planificá las comidas y cuidá el presupuesto familiar desde una sola plataforma.</p>
                <div class="home-actions">
                    <a class="home-button home-button-primary" href="{{ route('login') }}">Ingresar</a>
                    <a class="home-button home-button-light" href="#producto">Descubrir cómo funciona</a>
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
        </div>
    </section>

    <section class="home-section home-section-soft">
        <div class="home-container screens-layout">
            <div class="browser-shot">
                <div class="browser-bar"><i class="browser-dot"></i><i class="browser-dot"></i><i class="browser-dot"></i><span class="browser-address"></span></div>
                <div class="screen-slot">
                    <div class="slot-message"><strong>Captura de la web</strong><span>Espacio reservado para una imagen del dashboard o de la planificación en escritorio.</span></div>
                </div>
            </div>

            <div class="screen-copy">
                <div class="home-eyebrow">Versión web</div>
                <h2>La vista completa de tu hogar.</h2>
                <p>Un escritorio cómodo para administrar productos, recetas, miembros, compras y reportes con toda la información visible.</p>
                <div class="screen-points">
                    <div class="screen-point"><span class="screen-point-mark">1</span><div><strong>Gestión clara</strong><span>Más espacio para revisar y editar.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">2</span><div><strong>Información conectada</strong><span>Salud, economía, tiempo y stock.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">3</span><div><strong>Permisos por rol</strong><span>Usuario, profesional, admin y docente.</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="home-container mobile-showcase">
            <div class="mobile-stage">
                <div class="phone-frame">
                    <div class="phone-slot"><div><strong>Captura de la app</strong><span>Espacio reservado para una pantalla real de Android.</span></div></div>
                </div>
            </div>

            <div class="screen-copy">
                <div class="home-eyebrow">Aplicación móvil</div>
                <h2>La información justa, cuando estás en movimiento.</h2>
                <p>Consultá qué falta, revisá tu lista y registrá productos desde el teléfono mientras comprás o cocinás.</p>
                <div class="screen-points">
                    <div class="screen-point"><span class="screen-point-mark">1</span><div><strong>En el supermercado</strong><span>Lista, escaneo y comparación.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">2</span><div><strong>En la cocina</strong><span>Recetas, pasos y porciones.</span></div></div>
                    <div class="screen-point"><span class="screen-point-mark">3</span><div><strong>Siempre sincronizada</strong><span>La misma cuenta y el mismo backend.</span></div></div>
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
                <h2>Llevá CocinaComidaControl con vos.</h2>
                <p>La descarga de Android aparecerá acá cuando esté lista la primera compilación de prueba. Mientras tanto, la versión web ya está disponible.</p>
            </div>
            <div class="download-actions">
                <span class="home-button home-button-primary" aria-disabled="true">APK próximamente</span>
                <a class="home-button home-button-outline" href="{{ route('login') }}">Ingresar a la web</a>
            </div>
        </div>
    </section>
</main>
@endsection
