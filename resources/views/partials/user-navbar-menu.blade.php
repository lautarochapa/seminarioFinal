@guest
    <div class="nav-auth-actions">
        <a class="cta nav-register" href="{{ route('register') }}" data-mobile-entry="register">Registrate</a>
        <a class="cta" href="{{ route('login') }}" data-mobile-entry="login">Ingresar</a>
    </div>
@else
    <div class="dropdown">
        <a class="cta dropdown-toggle" href="#" role="button" id="userNavbarMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {{ Auth::user()->name }}
        </a>
        <div class="dropdown-menu" aria-labelledby="userNavbarMenu">
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
