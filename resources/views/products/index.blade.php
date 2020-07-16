@extends('layouts.app')

@section('content')
<div class="row mt-4">

    <div class="col-lg-4 col-md-6 mb-4" style="padding: 1%;">
        @foreach($products as $prod)
        <productCard :prod="{{ $prod}}"></productCard>
        @endforeach
    </div>
</div>

@endsection
