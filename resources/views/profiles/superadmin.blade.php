@extends('layouts.app')

@section('content')
<div style="padding: 5px 15%;">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Perfil SuperAdmin') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif






                    <p> Bienvenido {{ Auth::user()->name }} ,  {{ Auth::user()->lastname }} </p>


                    <p>Desde aca podras ver todas las vistas de la aplicacion, sus funcionalidades y la documentacion de las API.</p>

                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-6" style="margin-bottom: 15px;">
                            <div class="card">
                                <div class="card-header">{{ __('Vistas legacy por rol') }}</div>
                                <div class="card-body">
                                    <a href="{{ url('/admin') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Vista Admin') }}</a>
                                    <a href="{{ url('/chef') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Vista Chef') }}</a>
                                    <a href="{{ url('/nutritionist') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Vista Nutricionista') }}</a>
                                    <a href="{{ url('/somelier') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Vista Somelier') }}</a>
                                    <a href="{{ url('/comensal') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Vista Usuario') }}</a>
                                    <a href="{{ url('/users') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Panel Usuarios') }}</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6" style="margin-bottom: 15px;">
                            <div class="card">
                                <div class="card-header">{{ __('Portales web nuevos') }}</div>
                                <div class="card-body">
                                    <a href="{{ url('/web') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Web Usuario') }}</a>
                                    <a href="{{ url('/admin-web') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Web Admin') }}</a>
                                    <a href="{{ url('/teacher-web') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Web Docente') }}</a>
                                    <a href="{{ url('/app') }}" class="cta" style="display:inline-block;margin:5px;">{{ __('Prototipo Mobile') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <main class="py-4">
                        @yield('content2')
                    </main>



                </div>
            </div>
        </div>
    </div>
</div>
@endsection
