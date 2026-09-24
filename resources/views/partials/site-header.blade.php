<header class="site-navbar {{ auth()->check() ? 'site-navbar-authenticated' : '' }}" @if(isset($screenKey) && auth()->check()) id="panel-header" data-user="{{ auth()->id() }}" data-turbo-permanent @endif>
    <div class="navbar-inner">
        <a class="logo" href="{{ auth()->check() ? url(auth()->user()->hasPermission('web.admin.dashboard') ? '/admin-web' : '/web') : url('/') }}"><img src="{{ asset('images/logo/2.svg') }}" alt="CocinaComidaControl" width="300" height="40"></a>
        @auth
        <nav class="primary-navigation" aria-label="Navegación principal"><ul class="nav__links">@include('partials.portal-navbar')</ul></nav>
        @endauth
        @include('partials.user-navbar-menu')
    </div>
</header>
