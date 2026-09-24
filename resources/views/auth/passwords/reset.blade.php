@extends('layouts.auth-page')
@section('auth_title', 'Restablecer contraseña')
@section('auth_intro', 'Elegí una contraseña nueva para tu cuenta.')
@section('auth_form')
<form method="POST" action="{{ route('password.update') }}" data-api-endpoint="/api/v1/auth/reset-password" data-api-method="POST" data-success-message="Contraseña restablecida correctamente." data-redirect="{{ route('login') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="alert" data-api-message role="status" style="display:none"></div>
    @include('partials.auth-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email ?? old('email')])
    @include('partials.auth-field', ['name' => 'password', 'label' => 'Nueva contraseña', 'type' => 'password', 'autocomplete' => 'new-password'])
    @include('partials.auth-field', ['name' => 'password_confirmation', 'label' => 'Confirmar contraseña', 'type' => 'password', 'autocomplete' => 'new-password'])
    <button class="cta auth-submit" type="submit">Restablecer contraseña</button>
</form>
@endsection
