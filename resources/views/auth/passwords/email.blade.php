@extends('layouts.auth-page')
@section('auth_title', 'Recuperar contraseña')
@section('auth_intro', 'Te enviaremos un enlace para elegir una nueva contraseña.')
@section('auth_form')
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" data-api-endpoint="/api/v1/auth/forgot-password" data-api-method="POST" data-success-message="Si el email está registrado, recibirás un enlace de recuperación.">
    @csrf
    <div class="alert" data-api-message role="status" style="display:none"></div>
    @include('partials.auth-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
    <button class="cta auth-submit" type="submit">Enviar enlace</button>
</form>
@endsection
@section('auth_after')<a href="{{ route('login') }}">Volver a iniciar sesión</a>@endsection
