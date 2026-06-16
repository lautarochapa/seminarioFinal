@php
    $actionType = $error['action_type'] ?? 'home';
    $href = url('/');
    $onclick = null;

    if ($actionType === 'login') {
        $href = route('login');
    } elseif ($actionType === 'back') {
        $href = url('/');
        $onclick = 'event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = \''.url('/').'\'; }';
    } elseif ($actionType === 'reload') {
        $href = request()->fullUrl();
        $onclick = 'event.preventDefault(); window.location.reload();';
    }

    $errorImage = 'images/errors/kitchen-error.png';
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
        $candidate = 'images/errors/'.$error['code'].'.'.$extension;
        if (file_exists(public_path($candidate))) {
            $errorImage = $candidate;
            break;
        }
    }
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $error['code'] }} - {{ $error['title'] }} | {{ config('app.name', 'CocinaComidaControl') }}</title>
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,700,900|Montserrat:500,800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/errors.css') }}?v={{ filemtime(public_path('css/errors.css')) }}" rel="stylesheet">
</head>
<body class="error-page-body">
    <main class="error-shell">
        <a class="error-brand" href="{{ url('/') }}">CocinaComidaControl</a>

        <section class="error-content">
            <div class="error-copy">
                <h1 class="error-code">{{ $error['code'] }}</h1>
                <h2 class="error-title">{{ $error['title'] }}</h2>
                <p class="error-message">{{ $error['message'] }}</p>
                <p class="error-remate">{{ $error['remate'] }}</p>
                <a class="error-action" href="{{ $href }}" @if($onclick) onclick="{{ $onclick }}" @endif>
                    <svg class="error-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 10.5 12 4l8 6.5v8a1.5 1.5 0 0 1-1.5 1.5H15v-5H9v5H5.5A1.5 1.5 0 0 1 4 18.5v-8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                    {{ $error['action'] }}
                </a>
            </div>

            <div class="error-art" aria-hidden="true">
                <img src="{{ asset($errorImage) }}" alt="">
            </div>
        </section>
    </main>
</body>
</html>
