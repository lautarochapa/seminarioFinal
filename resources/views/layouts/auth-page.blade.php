@extends('layouts.app')
@section('content')
<section class="auth-page public-container">
    <header class="auth-heading"><h1>@yield('auth_title')</h1><p>@yield('auth_intro')</p></header>
    <div class="auth-surface">@yield('auth_form')</div>
    <div class="auth-after">@yield('auth_after')</div>
</section>
@endsection
