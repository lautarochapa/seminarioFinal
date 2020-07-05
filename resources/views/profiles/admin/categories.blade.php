@extends('profiles.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ __('Bienvenido Admin!') }}


                    <p> desde aca podras administrar los productos, las recetas, las dietas y los usuarios</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
