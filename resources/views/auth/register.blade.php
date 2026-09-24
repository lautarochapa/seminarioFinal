@extends('layouts.auth-page')
@section('auth_title', 'Crear cuenta')
@section('auth_intro')¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciá sesión</a>@endsection
@section('auth_form')
<form method="POST" action="{{ route('register') }}" data-api-endpoint="/api/v1/auth/register" data-api-method="POST" data-auth-session="true" data-redirect="{{ \App\Services\Auth\AuthRedirect::afterLogin() }}">
    @csrf
    <div class="alert" data-api-message role="status" style="display:none"></div>
    <div class="auth-two-columns">
        @include('partials.auth-field', ['name' => 'name', 'label' => 'Nombre', 'autocomplete' => 'given-name'])
        @include('partials.auth-field', ['name' => 'lastname', 'label' => 'Apellido', 'autocomplete' => 'family-name'])
    </div>
    @include('partials.auth-field', ['name' => 'username', 'label' => 'Nombre de usuario'])
    @include('partials.auth-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
    @include('partials.auth-field', ['name' => 'password', 'label' => 'Contraseña', 'type' => 'password', 'autocomplete' => 'new-password'])
    @include('partials.auth-field', ['name' => 'password_confirmation', 'label' => 'Confirmar contraseña', 'type' => 'password', 'autocomplete' => 'new-password'])
    <button class="cta auth-submit" type="submit">Registrarme</button>
</form>
@endsection
