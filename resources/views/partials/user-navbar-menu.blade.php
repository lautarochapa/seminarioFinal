@guest
    <div class="nav-auth-actions">
        <a class="cta nav-register" href="{{ route('register') }}" data-mobile-entry="register">Registrate</a>
        <a class="cta" href="{{ route('login') }}" data-mobile-entry="login">Ingresar</a>
    </div>
@else
    <div class="dropdown navbar-dropdown navbar-account">
        <button type="button" class="cta navbar-menu-toggle dropdown-toggle" id="userNavbarMenu" data-toggle="dropdown" aria-controls="userNavbarDropdown" aria-expanded="false" aria-label="Cuenta de {{ Auth::user()->name }}">
            <span class="navbar-user-name" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</span>
        </button>
        <div class="dropdown-menu navbar-menu" id="userNavbarDropdown" aria-labelledby="userNavbarMenu">
            @if(Auth::user()->hasPermission('web.user.profile-objectives'))
                <a class="dropdown-item" href="{{ url('/web/profile-objectives') }}">Mi Perfil</a>
            @endif
            <a class="dropdown-item" href="{{ route('logout') }}" data-api-logout data-fallback-form="#logout-form-navbar">
                Cerrar Sesion
            </a>
            <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </div>
@endguest
