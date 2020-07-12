@extends('layouts.app')

@section('content')
<div style="padding: 5px 15%;">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

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

                    {{ __('Bienvenido!') }}



                    <p> desde aca podras ver tu agenda y tu stock de productos</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
