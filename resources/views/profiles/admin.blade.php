@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Perfil Administrador') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif


                    @auth
                    @if (auth()->user()->nivel_acceso == 3)
                    <a href="{{ url('/superadmin') }}" ><button type="button" class="btn btn-primary">{{ __('SuperAdmin') }}</button></a>
                        @else 
                        @endif
                    @else 
                      
                         @endif


                    <p> Bienvenido {{ Auth::user()->name }} ,  {{ Auth::user()->lastname }} </p>


                    <p> Desde aca podras administrar las categorias y los productos</p>

                    
                    <a href="{{ url('/categories') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Categorias') }}</button></a>
                    <a href="{{ url('/products') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Productos') }}</button></a>
                    <a href="{{ url('/utensils') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Utensillos') }}</button></a>

                    <main class="py-4">
                        @yield('content2')
                    </main>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
