@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Perfil Administrador') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p> Bienvenido {{ Auth::user()->name }} ,  {{ Auth::user()->lastname }} </p>


                    <p> Desde aca podras administrar los productos, las recetas, las dietas y los usuarios</p>

                    
                    <a href="{{ url('/categories') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Categorias') }}</button></a>

                    <main class="py-4">
                        @yield('content2')
                    </main>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
