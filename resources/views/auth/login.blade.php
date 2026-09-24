@extends('layouts.auth-page')
@section('auth_title', 'Iniciar sesión')
@section('auth_intro')¿Todavía no tenés cuenta? <a href="{{ route('register') }}">Registrate</a>@endsection
@section('auth_form')
<form method="POST" action="{{ route('login') }}" data-api-endpoint="/api/v1/auth/login" data-api-method="POST" data-auth-session="true" data-redirect="{{ \App\Services\Auth\AuthRedirect::afterLogin() }}">
    @csrf
    <div class="alert" data-api-message role="status" style="display:none"></div>
    @include('partials.auth-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
    @include('partials.auth-field', ['name' => 'password', 'label' => 'Contraseña', 'type' => 'password', 'autocomplete' => 'current-password'])
    <div class="auth-options">
        <label><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> Recordarme</label>
        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
    </div>
    <button class="cta auth-submit" type="submit">Iniciar sesión</button>
</form>
@endsection
