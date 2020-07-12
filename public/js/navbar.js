const doc = document;
const menuOpen = document.getElementsByClassName("menu");
const menuClose = document.getElementsByClassName("close");
const overlay = document.getElementsByClassName("overlay");

for(i=0;i<menuOpen.length;i++){
  menuOpen[i].addEventListener("click", () => {
    overlay.classList.add("overlay--active");
  });
}

for(i=0;i<menuClose.length;i++){
  menuClose[i].addEventListener("click", () => {
    overlay.classList.remove("overlay--active");
  });
}