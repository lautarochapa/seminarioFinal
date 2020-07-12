<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CocinaComidaControl') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    <!--<script src="{{ asset('js/navbar.js') }}" defer></script>-->

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
   <!-- <link href="{{ asset('css/treeview.css') }}" rel="stylesheet"> -->
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet"> 


   <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
                
</head>
<body>
    <div id="app">


<!--
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
           
        
        <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'CocinaComidaControl') }}
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav mr-auto">
                    </ul>
                    <ul class="navbar-nav ml-auto">
                    @auth
                    @if (auth()->user()->nivel_acceso == 1)
                            <li class="nav-item">
                            <a class="nav-link" href="{{ url('/comensal') }}" >{{ __('Panel Comensal') }}</a>
                                </li>
                        @else 
                            @if (auth()->user()->nivel_acceso == 2)
                                <li class="nav-item">
                                <a class="nav-link" href="{{ url('/admin') }}" >{{ __('Panel Admin') }}</a>
                                </li>
                            @else 
                                @if (auth()->user()->nivel_acceso == 3)
                                 <li class="nav-item">
                                    <a class="nav-link" href="{{ url('/superadmin') }}" >{{ __('Panel SuperAdmin') }}</a>
                                </li>
                                @else 
                                    @if (auth()->user()->nivel_acceso == 4)
                                  <li class="nav-item">
                                        <a class="nav-link" href="{{ url('/soemlier') }}" >{{ __('Panel Somelier') }}</a>
                                </li>
                                    @else 
                                        @if (auth()->user()->nivel_acceso == 4)
                                 <li class="nav-item">
                                            <a class="nav-link" href="{{ url('/chef') }}" >{{ __('Panel Chef') }}</a>
                                </li>
                                        @else 
                                            @if (auth()->user()->nivel_acceso == 4)
                                            <li class="nav-item">
                                                <a class="nav-link" href="{{ url('/nutricionista') }}" >{{ __('Panel Nutricionista') }}</a>
                                </li>
                                            @else  
                                            @endif
                                        @endif
                                    @endif
                                @endif
                            @endif
                        @endif
                        @else 
                                               @endif
                        @guest
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('Iniciar Sesion') }}</a>
                            </li>
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Registrarse') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }} <span class="caret"></span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

-->







        <header>
                    <a class="logo" href="{{ url('/') }}"><img :src="'images/logo/2.svg'" alt="logo" height="40em"></a>
                    
                    <form action="#" method="GET" role="search" style="left: 35px;    max-width: 600px;padding: 8px 1px; margin-right: auto; height: 56px;    position: relative;z-index: 910;">
                        <input type="text" style="padding: 7px 60px 9px 15px;background-color: #fff;z-index: 915;border: 0 rgba(0,0,0,.2);font-size: 16px; width: 100%;    margin: 0;   font-family: inherit;" aria-label="Ingresá lo que quieras encontrar" name="as_word" placeholder="Buscar productos, recetas y más…" maxlength="120" autofocus="" tabindex="2">
                        <button type="submit" style="cursor: pointer;height: 39px;width: 46px;right: 1px;z-index: 920;position: absolute; padding: 0;  background: 0 0; border: none;font-size: 22px;color: #666;line-height: 1em;" tabindex="3">
                            <div role="img" aria-label="Buscar" style="cursor: pointer;font-size: 22px;content: '\EA27';vertical-align: top; font-family: navigation;    color: #666;    line-height: 1em;"></div>
                        </button>
                    </form>
                    
                    

                <!-- <a class="cta" href="#">Contact</a> -->
                    @guest
                        <nav>
                            <ul class="nav__links">
                                <li><a href="#">Aplicacion</a></li>
                                <li><a href="#">Proyecto</a></li>
                            </ul>
                        </nav>

                            <a class="cta" href="{{ route('login') }}">{{ __('Ingresar') }}</a>
                      <!--  @if (Route::has('register'))
                            <a class="cta" href="{{ route('register') }}">{{ __('Registrarse') }}</a>
                        @endif -->
                    @else
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



                        <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="cta dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Cerrar Sesion') }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
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

        <main class="py-5">
            @yield('content')
        </main>


        <p>Footer aca </p>
    </div>
</body>
</html>
