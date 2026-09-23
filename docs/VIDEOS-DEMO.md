# Videos de la landing

La seccion publica es `/#demos`, antes de las capturas del producto.
El listado se define en `config/demos.php`. Solo se muestran entradas cuyo
archivo exista en `public/`; una demo pendiente no genera enlaces rotos.

## Video web

- Archivo: `public/videos/demo-web-20260923.mp4`, 2.343.672 bytes.
- Poster: `public/images/landing/demo-web-20260923.jpg`.
- H.264, 1920x1080, 25 fps, 2:39, sin audio.
- Capturas reales editadas por etapas; NO grabacion continua.
- Cuenta y hogar ficticios. Contrasena y tokens excluidos.
- El video y su resumen aclaran el limite de vinculacion del stock manual.

## Android

Todavia no grabado. Su entrada tiene `file`, `poster` y `duration` en null.
Para incorporarlo: grabar y revisar el MP4 real del telefono; comprobar que
no muestre notificaciones privadas ni credenciales; agregar video y poster
versionados a `public/` y completar esa entrada, incluido el resumen textual.
No reutilizar el video web ni la simulacion de la landing como prueba Android.

## Publicacion y rendimiento

Los MP4 son archivos estaticos servidos por Apache, sin pasar por un
controlador PHP. No se cargan completos al abrir la landing (`preload=none`),
no tienen autoplay y no se incorpora un reproductor de terceros.
Se conservan controles nativos, pantalla completa, descarga y lectura alternativa.
Para reemplazar una version, usar un nombre de archivo nuevo y actualizar
la configuracion; no sobrescribir la misma URL con otro contenido.

## Verificacion

- `php tests/Smoke/landing-demos.php`: existencia de archivos, poster,
  controles sin autoplay/precarga, descarga, texto y estados con 0/1/2 demos.
- `php tests/Smoke/landing-download.php`: descarga APK y capturas preservadas.
- Reproduccion local comprobada: H.264 1920x1080, duracion 159 s, avance real.
- Vista de escritorio y 390 px revisadas; texto alternativo desplegable sin
  desborde horizontal. Hubo un cierre de la pestana del navegador integrado
  durante la prueba del control nativo; la revision responsive continuo en
  otra pestana. No se atribuye ese cierre al servidor sin diagnostico.
- Antes de cerrar publicacion: verificar HTTP 200 en landing y poster,
  `video/mp4` y respuesta 206 a una peticion Range del MP4 en produccion.
