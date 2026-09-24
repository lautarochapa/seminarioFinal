<header class="site-navbar {{ auth()->check() ? 'site-navbar-authenticated' : '' }}">
    <div class="navbar-inner">
        <a class="logo" href="{{ url('/') }}"><img src="{{ asset('images/logo/2.svg') }}" alt="CocinaComidaControl" width="300" height="40"></a>
        @auth
        <nav class="primary-navigation" aria-label="Navegación principal"><ul class="nav__links">@include('partials.portal-navbar')</ul></nav>
        @endauth
        @include('partials.user-navbar-menu')
    </div>
</header>
