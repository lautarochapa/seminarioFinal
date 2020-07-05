@extends('layouts.app')

@section('content')
<div class="container">
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


                    <p> desde aca podras ver todas las vistas de la aplicacion y sus funcionalidades y ver la documentacion de las API</p>

                    
                    <a href="{{ url('/admin') }}" ><button type="button" class="btn btn-primary">{{ __('Vista Admin') }}</button></a>
                    <a href="{{ url('/chef') }}" ><button type="button" class="btn btn-primary">{{ __('Vista Chef') }}</button></a>
                    <a href="{{ url('/nutritionist') }}" ><button type="button" class="btn btn-primary">{{ __('Vista Nutricionista') }}</button></a>
                    <a href="{{ url('/somelier') }}" ><button type="button" class="btn btn-primary">{{ __('Vista Somelier') }}</button></a>
                    <a href="{{ url('/users') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Usuarios') }}</button></a>

                    <main class="py-4">
                        @yield('content2')
                    </main>



                </div>
            </div>
        </div>
    </div>
</div>
@endsection
