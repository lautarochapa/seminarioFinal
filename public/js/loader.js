nro = Math.floor(Math.random() * 19) + 1
document.getElementById("src").src = "images/video/" + nro + ".mp4"
window.addEventListener("load", function () {
    const loader = document.querySelector(".loader");
    loader.className += " hidden"; // class "loader hidden"
});

