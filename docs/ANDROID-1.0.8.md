# Android 1.0.8 (9) - Registro de release

Fecha: 23/09/2026.

## Artefacto verificado

- Version: 1.0.8; versionCode: 9; paquete: `com.cccontrol.mobile`.
- EAS Build: `20698c5b-049a-4559-b67f-61fccba52096`, finalizado correctamente.
- Fuente: `b048a009bb313d41895d53530de5b752d4ccd484`. Se cotejaron 321 archivos del envio a Expo con la fuente de la release. EAS omite gitCommitHash al compilar sin VCS.
- Archivo: `CocinaComidaControl-1.0.8-9.apk`, 128749779 bytes.
- SHA-256: `361db97d6bf2be54143aca0f44bd0cb60577044433f1ee40588347a5388d56e3`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- Firma valida e igual a 1.0.7; debuggable=false; bundle standalone incorporado.
- [Release Android](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.8-9).
- [Descarga desde el sitio](https://cocinacomidacontrol.com.ar/#descarga-app).

## Cambios

- Icono propio, variantes adaptativa y monocromatica, y pantalla de inicio.
- Correcciones de navegacion entre recetas, planificacion, stock y compras.
- Semanas, porciones con coma decimal, alta de planes y actualizacion de contadores.
- Formulario de comidas, teclado y confirmacion de descarte.
- Calculo de stock de productos especificos y faltantes, sin duplicar reservas.
- Conservacion del producto al generar listas y presentacion de costos sin precio conocido.

## Verificacion

La preparacion de la release registro 63 suites y 382 pruebas moviles, 164 pruebas
del backend y TypeScript aprobados. El detalle del lote esta en
[correcciones Android](qa/android-lote-final-2026-09-23.md).

Para la publicacion se verificaron nuevamente la fuente enviada, firma, paquete,
version, compilacion y bundle. Las pruebas de descarga, API publica de version
y seccion Demo pasaron con la nueva configuracion.

La landing y la API usan `config/mobile.php` como fuente comun. La ruta estable
`/descargas/android` redirige al archivo versionado; no se reemplazan APK anteriores
ni se cambia la firma. La API `/api/v1/mobile/android/version` anuncia build 9
sin requerir sesion, consultar la base de datos ni cachear la respuesta.

## Instalacion y comprobacion fisica

Instalar como actualizacion sobre la app existente, sin desinstalar. La validacion
del archivo y las pruebas automatizadas no sustituyen las pruebas en el Samsung.

1. Cerrar completamente la version anterior y abrirla con internet.
2. Comprobar el aviso y que Ir a descargar lleve a la version 1.0.8.
3. Instalar la actualizacion y comprobar icono, sesion y hogar.
4. Reabrir 1.0.8 y comprobar que no anuncie actualizar al mismo build.
5. Repetir los casos corregidos de recetas, planificacion, stock y compras.
