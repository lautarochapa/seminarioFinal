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
                                <li><a href="#">Aplicacion</a></li>
                                <li><a href="#">Proyecto</a></li>
                            </ul>
                        </nav>

                            <a class="cta" href="{{ route('login') }}">{{ __('Ingresar') }}</a>
                    @else

                        <form action="#" method="GET" role="search" style="left: 35px;    max-width: 600px;padding: 8px 1px; margin-right: auto; height: 56px;    position: relative;z-index: 910;">
                            <input type="text" style="padding: 7px 60px 9px 15px;background-color: #fff;z-index: 915;border: 0 rgba(0,0,0,.2);font-size: 16px; width: 100%;    margin: 0;   font-family: inherit;" aria-label="Ingres?? lo que quieras encontrar" name="as_word" placeholder="Buscar productos, recetas y m??s???" maxlength="120" autofocus="" tabindex="2">
                            <button type="submit" style="cursor: pointer;height: 39px;width: 46px;right: 1px;z-index: 920;position: absolute; padding: 0;  background: 0 0; border: none;font-size: 22px;color: #666;line-height: 1em;" tabindex="3">
                                <div role="img" aria-label="Buscar" style="cursor: pointer;font-size: 22px;content: '\EA27';vertical-align: top; font-family: navigation;    color: #666;    line-height: 1em;"></div>
                            </button>
                        </form>


                        <nav>
                            <ul class="nav__links">
                                @if (auth()->user()->nivel_acceso == 1)
                                    <li><a href="{{ url('/comensal') }}" >{{ __('Productos') }}</a></li>
                                    <li><a href="{{ url('/comensal') }}" >{{ __('Recetas') }}</a></li>
                                    <li><a href="{{ url('/comensal') }}" >{{ __('Historial') }}</a></li>
                                @endif 
                                @if (auth()->user()->nivel_acceso == 2)
                                    <li><a href="{{ url('/admin') }}" >{{ __('Panel Productos') }}</a></li>
                                    <li><a href="{{ url('/admin') }}" >{{ __('Panel Recetas') }}</a></li>
                                    <li><a href="{{ url('/admin') }}" >{{ __('Panel Administrador') }}</a></li>
                                @endif 
                                @if (auth()->user()->nivel_acceso == 3)
                                    <li><a href="{{ url('/superadmin') }}" >{{ __('Productos') }}</a></li>
                                    <li><a href="{{ url('/superadmin') }}" >{{ __('Recetas') }}</a></li>
                                    <li><a href="{{ url('/superadmin') }}" >{{ __('Historial') }}</a></li>
                                @endif 
                                @if (auth()->user()->nivel_acceso == 4)
                                    <li><a href="{{ url('/soemlier') }}" >{{ __('Productos') }}</a></li>
                                    <li><a href="{{ url('/soemlier') }}" >{{ __('Recetas') }}</a></li>
                                    <li><a href="{{ url('/soemlier') }}" >{{ __('Historial') }}</a></li>
                                @endif 
                                @if (auth()->user()->nivel_acceso == 5)
                                    <li><a href="{{ url('/chef') }}" >{{ __('Productos') }}</a></li>
                                    <li><a href="{{ url('/chef') }}" >{{ __('Recetas') }}</a></li>
                                    <li><a href="{{ url('/chef') }}" >{{ __('Historial') }}</a></li>
                                @endif 
                                @if (auth()->user()->nivel_acceso == 6)
                                    <li><a href="{{ url('/nutricionista') }}" >{{ __('Productos') }}</a></li>
                                    <li><a href="{{ url('/nutricionista') }}" >{{ __('Recetas') }}</a></li>
                                    <li><a href="{{ url('/nutricionista') }}" >{{ __('Historial') }}</a></li>
                                @endif 
                            </ul>
                        </nav>


                        <div class="dropdown">
                            <a class="cta dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </a>

                            <div class="dropdown-menu" style=""aria-labelledby="dropdownMenuLink">
                                <a class="dropdown-item" href="#">Mi Perfil</a>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
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
                        <a href="#">Services</a>
                        <a href="#">Projects</a>
                        <a href="#">About</a>
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
