<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CocinaComidaControl') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/loader.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/api-client.js') }}?v={{ filemtime(public_path('js/api-client.js')) }}" defer></script>
    <script src="{{ asset('js/auth-api.js') }}?v={{ filemtime(public_path('js/auth-api.js')) }}" defer></script>
    <script src="{{ asset('js/professional-panel.js') }}?v={{ file_exists(public_path('js/professional-panel.js')) ? filemtime(public_path('js/professional-panel.js')) : time() }}" defer></script>
    <!--<script src="{{ asset('js/navbar.js') }}" defer></script>-->

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
   <!-- <link href="{{ asset('css/treeview.css') }}" rel="stylesheet"> -->
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet"> 
    
    <link href="{{ asset('css/loader.css') }}" rel="stylesheet"> 


   <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
                
</head>
<style>
body {
   /* background: linear-gradient(rgba(255,255,255,.5), rgba(255,255,255,.5)), url('{{asset('images/background/1.jpg')}}'); */

   /* background: url('{{asset('images/background/1.jpg')}}') no-repeat 0 50%;*/
    background-color: #cccccc70;
   }
   .legacy-panel { background:#fff; border:1px solid #dde6df; border-radius:8px; padding:16px; margin-bottom:14px; }
   .legacy-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
   .legacy-table { width:100%; border-collapse:collapse; font-size:14px; }
   .legacy-table th, .legacy-table td { border-bottom:1px solid #edf2ee; padding:10px 8px; vertical-align:top; }
   .legacy-table th { color:#66746b; font-size:12px; text-transform:uppercase; }
   .legacy-actions { display:flex; gap:8px; flex-wrap:wrap; }
   @media (max-width:960px) { .legacy-grid { grid-template-columns:1fr; } }
</style>


<body>



<div class="loader">
    <!--<img src="loading.gif" alt="Loading..." />-->
	<p id="texto"> Cargando... </p>
	<video autoplay muted loop id="myVideo">
		<source id="src" src="" type="video/mp4">
	</video>
	
</div>




    <div id="app" style="display: flex;
    flex-direction: column;
    min-height: 100vh;">


        <header>
                    <a class="logo" href="{{ url('/') }}"><img :src="'images/logo/2.svg'" alt="logo" height="40em"></a>
                    
                    @guest
                        <nav>
                            <ul class="nav__links">
                                @include('partials.portal-navbar')
                            </ul>
                        </nav>

                            <a class="cta" href="{{ route('login') }}">{{ __('Ingresar') }}</a>
                    @else

                        <nav>
                            <ul class="nav__links">
                                @include('partials.portal-navbar')
</ul>
                        </nav>


                        <div class="dropdown">
                            <a class="cta dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </a>

                            <div class="dropdown-menu" style=""aria-labelledby="dropdownMenuLink">
                                <a class="dropdown-item" href="{{ url('/web/profile-objectives') }}">Mi Perfil</a>
                                <a class="dropdown-item" href="{{ route('logout') }}" data-api-logout data-fallback-form="#logout-form">
                                    {{ __('Cerrar Sesion') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>

                            </div>
                        </div>
                    @endguest


                    <p class="menu cta">Menu</p>
                </header>
                <div id="mobile__menu" class="overlay">
                    <a class="close">&times;</a>
                    <div class="overlay__content">
                        @auth
                            @if(Auth::user()->hasPermission('web.user.dashboard'))
                                <a href="{{ url('/web') }}">Usuario</a>
                            @endif
                            @if(Auth::user()->hasPermission('web.admin.dashboard'))
                                <a href="{{ url('/admin-web') }}">Admin</a>
                            @endif
                            @if(Auth::user()->hasPermission('web.teacher.home'))
                                <a href="{{ url('/teacher-web') }}">Docente</a>
                            @endif
                        @endauth
                    </div>
                </div>

        <main class="py-5" style="flex: 1;">
            @yield('content')
        </main>


 
        <footer style="background-color: #24252a; color: #edf0f1; padding: 5px 15%;width: 100%; bottom: 0;">
            <div class="row" style="margin: 5% auto 5% auto">
                <div class="col-md-4 align-self-center" style="text-align:center;">
                    Imagenes de https://www.pexels.com/
                    <br>
                    Iconos de https://www.flaticon.es/
                    ver atribuciones
                </div>
                <div class="col-md-4 align-self-center" style="text-align:center;">
                    <img :src="'images/logo/1.svg'" alt="logo" height="120em">
                </div>
                <div class="col-md-4 align-self-center" style="text-align:center;">
                Desarrollado por:
                    Lautaro Chiappero
                    Contacto:
                    mail: comidacocinacontrol@gmail.com
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
