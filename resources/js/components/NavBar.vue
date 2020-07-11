<template>
<div>
        <link href="https://fonts.googleapis.com/css?family=Montserrat:500&display=swap" rel="stylesheet">
         <header>
            <a class="logo" :href="url('/')"><img :src="'images/logo/2.svg'" alt="logo" height="2em"></a>
            
            <form action="#" method="GET" role="search" style="left: 35px;    max-width: 600px;padding: 8px 1px;    overflow: auto;    height: 56px;    position: relative;z-index: 910;">
                <input type="text" style="padding: 7px 60px 9px 15px;background-color: #fff;z-index: 915;border: 0 rgba(0,0,0,.2);font-size: 16px; width: 100%;    margin: 0;   font-family: inherit;" aria-label="Ingresá lo que quieras encontrar" name="as_word" placeholder="Buscar productos, recetas y más…" maxlength="120" autofocus="" tabindex="2">
                <button type="submit" style="cursor: pointer;height: 39px;width: 46px;right: 1px;z-index: 920;position: absolute; padding: 0;  background: 0 0; border: none;font-size: 22px;color: #666;line-height: 1em;" tabindex="3">
                    <div role="img" aria-label="Buscar" style="cursor: pointer;font-size: 22px;content: '\EA27';vertical-align: top; font-family: navigation;    color: #666;    line-height: 1em;"></div>
                </button>
            </form>
            
            
            <nav>
                <ul class="nav__links">
                    <li><a href="#">Services</a></li>
                    <li><a href="#">Projects</a></li>
                    <li><a href="#">About</a></li>
                </ul>
            </nav>
           <!-- <a class="cta" href="#">Contact</a> -->


                        @guest
                                <a class="cta" :href="route('login')">{{ __('Iniciar Sesion') }}</a>
                            @if (Route::has('register'))
                                    <a class="cta" :href="route('register')">{{ __('Registrarse') }}</a>
                            @endif
                        @else

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="cta dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" :href="route('logout')"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Cerrar Sesion') }}
                                    </a>

                                    <form id="logout-form" :action="route('logout')" method="POST" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest





            <p class="menu cta">Menu</p>
        </header>
        <div id="mobile__menu" class="overlay">
            <a class="close">&times;</a>
            <div class="overlay__content">
                <a href="#">Services</a>
                <a href="#">Projects</a>
                <a href="#">About</a>
            </div>
        </div>

</div>
</template>

<script>
const doc = document;
const menuOpen = document.getElementsByClassName("menu");
const menuClose = document.getElementsByClassName("close");
const overlay = document.getElementsByClassName("overlay");

menuOpen.addEventListener("click", () => {
  overlay.classList.add("overlay--active");
});

menuClose.addEventListener("click", () => {
  overlay.classList.remove("overlay--active");
});

</script>

<style>

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

header {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding: 5px 15%;
  background-color: #24252a;
}

.logo {
  margin-right: auto;
}

.nav__links {
  list-style: none;
  display: flex;
}

.nav__links a,
.cta,
.overlay__content a {
  font-family: "Montserrat", sans-serif;
  font-weight: 500;
  color: #edf0f1;
  text-decoration: none;
}

.nav__links li {
  padding: 0px 20px;
}

.nav__links li a {
  transition: all 0.3s ease 0s;
}

.nav__links li a:hover {
  color: #0088a9;
}

.cta {
  margin-left: 20px;
  padding: 9px 25px;
  background-color: rgba(0, 136, 169, 1);
  border: none;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.3s ease 0s;
}

.cta:hover {
  background-color: rgba(0, 136, 169, 0.8);
}

/* Mobile Nav */

.menu {
  display: none;
}

.overlay {
  height: 100%;
  width: 0;
  position: fixed;
  z-index: 1;
  left: 0;
  top: 0;
  background-color: #24252a;
  overflow-x: hidden;
  transition: all 0.5s ease 0s;
}

.overlay--active {
  width: 100%;
}

.overlay__content {
  display: flex;
  height: 100%;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.overlay a {
  padding: 15px;
  font-size: 36px;
  display: block;
  transition: all 0.3s ease 0s;
}

.overlay a:hover,
.overlay a:focus {
  color: #0088a9;
}
.overlay .close {
  position: absolute;
  top: 20px;
  right: 45px;
  font-size: 60px;
  color: #edf0f1;
  cursor: pointer;
}

@media screen and (max-height: 450px) {
  .overlay a {
    font-size: 20px;
  }
  .overlay .close {
    font-size: 40px;
    top: 15px;
    right: 35px;
  }
}

@media only screen and (max-width: 800px) {
  .nav__links,
  .cta {
    display: none;
  }
  .menu {
    display: initial;
  }
}


</style>
