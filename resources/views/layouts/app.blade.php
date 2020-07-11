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

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
   <!-- <link href="{{ asset('css/treeview.css') }}" rel="stylesheet"> -->
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
        <nav>
            @yield('nav')
        </nav>

        <main class="py-5">
            @yield('content')
        </main>


        <p>Footer aca </p>
    </div>
</body>
</html>
