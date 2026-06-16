@extends('layouts.app')

@section('content')
<link href="{{ asset('css/login.css') }}" rel="stylesheet"> 
<script src="https://apis.google.com/js/platform.js" async defer></script>
<meta name="google-signin-client_id" content="623128501385-5iaciaqn2e29igc5j9vrim31i1mnj3oa.apps.googleusercontent.com">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <form method="POST" action="{{ route('login') }}" data-api-endpoint="/auth/login" data-api-method="POST" data-auth-session="true" data-redirect="{{ url('/web') }}">
                @csrf
                <div class="alert" data-api-message style="display:none"></div>
                <div cass="row" style="text-align:center;">
                    <h1>Iniciar Sesion</h1>
                    <p>¿Nuevo en ComidaCocinaControl? 
                                    <a class="btn btn-link" style="color:rgba(4,172,133, 1);" href="{{ route('register') }}">
                                        {{ __('Registrate') }}
                                    </a></p>
                </div>
                <div class="row grid-divider">
                    <div class="col-md-6 align-items-center align-self-center">

                        <div class="login-form form-group row">
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            <label for="email" class="login-input-label">
                                <span class="login-input-span" >
                                    {{ __('E-Mail') }}
                                </span>
                            </label>
                        </div>


                            
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <span class="invalid-feedback" data-field-error="email" role="alert"></span>
                       

                        <div class="login-form form-group row">
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                            <label for="password" class="login-input-label">
                                <span class="login-input-span">
                                    {{ __('Contraseña') }}
                                </span>
                            </label>
                        </div>



                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <span class="invalid-feedback" data-field-error="password" role="alert"></span>




                        <div class="form-group row">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="remember">
                                        {{ __('Recordarme') }}
                                    </label>
                                </div>



                                @if (Route::has('password.request'))
                                    <a class="btn btn-link" style="color:rgba(4,172,133, 1);" href="{{ route('password.request') }}">
                                        {{ __('¿Olvidaste tu contraseña?') }}
                                    </a>
                                @endif
                        </div>

                        <div class="form-group row mb-0">
                                <button type="submit" class="cta">
                                    {{ __('Iniciar Sesion') }}
                                </button>
                        </div>
                    </div>

                    <div class="col-md-6 align-items-center align-self-center">

                    <div class="form-group row" style="margin: 5% auto 5% auto">
                            <div class="col-md-12" style="text-align:center;">
                              <div class="g-signin2" data-onsuccess="onApiGoogleSignIn"></div>
                              <a href="{{url('/redirect')}}" class="btn btn-link" style="color:rgba(4,172,133, 1); margin-top:12px;">Usar Google legacy</a>
                            </div>
                        </div>

                        <div class="form-group row" style="margin: 5% auto 5% auto">
                            <div class="col-md-12" style="text-align:center;">
                              <a href="{{url('/redirect')}}" class="cta">Continuar con Facebook</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
