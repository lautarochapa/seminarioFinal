# Videos de la landing

La seccion publica es `/#demos`, antes de las capturas del producto, con
acceso "Ver demo" desde el inicio y otro acceso debajo del recorrido del producto.
El listado se define en `config/demos.php`. Solo se muestran entradas cuyo
archivo exista en `public/`; una demo pendiente no genera enlaces rotos.

## Video web

- Archivo: `public/videos/demo-web-guiada-20260923.mp4`, 7.801.973 bytes.
- Poster: `public/images/landing/demo-web-guiada-20260923.jpg`.
- H.264, 1920x1080, 25 fps, 2:09 (129,16 segundos), narracion AAC.
- 27 resaltados sincronizados: borde verde, atenuacion leve del entorno y
  transiciones suaves. No se modifican los datos de las capturas.
- SHA-256: `2b6d074fb543059598895d7bf865de09364ff1212d29f1e2be28094e8030641e`.
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

- `php tests/Smoke/landing-demos.php`: integridad del MP4 aprobado, duracion,
  poster, accesos desde la landing, controles sin autoplay/precarga, descarga,
  texto y estados con 0/1/2 demos.
- `php tests/Smoke/landing-download.php`: descarga APK y capturas preservadas.
- El montaje guiado fue decodificado completo sin errores: 3.229 cuadros,
  audio identico al montaje narrado aprobado y originales conservados.

### Publicacion anterior (video sin audio)

Los controles siguientes corresponden a la version anterior, conservada con
su URL original. No describen la verificacion online del nuevo archivo guiado.

- Reproduccion local comprobada: H.264 1920x1080, duracion 159 s, avance real.
- Vista de escritorio y 390 px revisadas; texto alternativo desplegable sin
  desborde horizontal. Hubo un cierre de la pestana del navegador integrado
  durante la prueba del control nativo; la revision responsive continuo en
  otra pestana. No se atribuye ese cierre al servidor sin diagnostico.
- Publicacion verificada el 2026-09-23: Render Live en commit `494baf9c`.
  Landing y poster HTTP 200; MP4 `video/mp4`, 2.343.672 bytes, SHA-256
  identico al archivo revisado. Range `bytes=0-31` devuelve HTTP 206 y
  `Content-Range: bytes 0-31/2343672`. Reproduccion probada en Chrome.
  URL publica: https://cocinacomidacontrol.com.ar/#demos
