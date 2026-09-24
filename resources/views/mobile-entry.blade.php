@extends('layouts.app')
@section('content')
<section class="mobile-entry public-container">
    <img class="mobile-entry-icon" src="{{ asset('images/landing/app-icon.png') }}" alt="" width="88" height="88">
    <h1>Tu cocina, con vos.</h1>
    <div data-android-entry>
        <p>Abrí CocinaComidaControl en tu celular o descargá la aplicación para Android.</p>
        <div class="entry-actions">
            <a class="cta" href="{{ route('downloads.android') }}" data-open-app>Abrir app</a>
            <a class="entry-secondary" href="{{ route('downloads.android') }}">Descargar APK {{ config('mobile.android.version') }}</a>
        </div>
        <p class="entry-note">Si todavía no la instalaste, descargá el archivo y confirmá la instalación en Android.</p>
    </div>
    <div data-ios-entry hidden>
        <p>La aplicación está disponible para Android. Para ingresar desde un iPhone o iPad, usá la versión web desde una computadora.</p>
    </div>
    <p class="entry-note">El panel web y la administración se utilizan desde una computadora.</p>
    <a class="entry-secondary" href="{{ url('/') }}">Volver al inicio</a>
</section>
@endsection
