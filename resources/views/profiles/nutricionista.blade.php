@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Perfil Nutricionista') }}</div>

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


                    <p> Desde aca podras administrar las dietas y tus pacientes</p>

                    
                    <a href="{{ url('/diets') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Dietas') }}</button></a>
                    <a href="{{ url('/patietns') }}" ><button type="button" class="btn btn-primary">{{ __('Panel Pacientes') }}</button></a>

                    <main class="py-4">
                        @yield('content2')
                    </main>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
