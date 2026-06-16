@extends('layouts.app')

@section('content')

<script src="https://apis.google.com/js/platform.js" async defer></script>
<meta name="google-signin-client_id" content="623128501385-5iaciaqn2e29igc5j9vrim31i1mnj3oa.apps.googleusercontent.com">



<script>
  function signOut() {
    var auth2 = gapi.auth2.getAuthInstance();
    auth2.signOut().then(function () {
      console.log('User signed out.');
    });
  }
</script>

<script>

function onSignIn(googleUser) {
  window.onApiGoogleSignIn(googleUser);
}



</script>


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Registro</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}" data-api-endpoint="/auth/register" data-api-method="POST" data-auth-session="true" data-redirect="{{ url('/web') }}">
                        @csrf
                        <div class="alert" data-api-message style="display:none"></div>

                        <div class="form-group row">
                            <label for="name" class="col-md-4 col-form-label text-md-right">Nombre</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <span class="invalid-feedback" data-field-error="name" role="alert"></span>
                            </div>
                        </div>

<div class="form-group row">
    <label for="lastname" class="col-md-4 col-form-label text-md-right">Apellido</label>

    <div class="col-md-6">
        <input id="lastname" type="text" class="form-control @error('lastname') is-invalid @enderror" name="lastname" value="{{ old('lastname') }}" required autocomplete="lastname" autofocus>

        @error('lastname')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
        <span class="invalid-feedback" data-field-error="lastname" role="alert"></span>
    </div>
</div>

<div class="form-group row">
    <label for="username" class="col-md-4 col-form-label text-md-right">Usuario</label>

    <div class="col-md-6">
        <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus>

        @error('username')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
        <span class="invalid-feedback" data-field-error="username" role="alert"></span>
    </div>
</div>

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">Email</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <span class="invalid-feedback" data-field-error="email" role="alert"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">Contraseña</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <span class="invalid-feedback" data-field-error="password" role="alert"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password-confirm" class="col-md-4 col-form-label text-md-right">Confirmar contraseña</label>

                            <div class="col-md-6">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                <span class="invalid-feedback" data-field-error="password_confirmation" role="alert"></span>
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Registrarme
                                </button>
                            </div>
                        </div>


                        <div class="g-signin2" data-onsuccess="onApiGoogleSignIn"></div>
                        <a href="#" onclick="signOut();">Cerrar sesion de Google</a>
                    </form>


                </div>
            </div>
        </div>
    </div>
</div>
@endsection
