@guest
    <a class="cta" href="{{ route('login') }}">Ingresar</a>
@else
    <div class="dropdown">
        <a class="cta dropdown-toggle" href="#" role="button" id="userNavbarMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {{ Auth::user()->name }}
        </a>
        <div class="dropdown-menu" aria-labelledby="userNavbarMenu">
            <a class="dropdown-item" href="{{ url('/web/profile-objectives') }}">Mi Perfil</a>
            <a class="dropdown-item" href="{{ route('logout') }}" data-api-logout data-fallback-form="#logout-form-navbar">
                Cerrar Sesion
            </a>
            <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </div>
@endguest
