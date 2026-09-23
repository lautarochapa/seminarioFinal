# Android 1.0.7 (8) - Registro de release

Fecha de publicacion: 23/09/2026.

## Artefacto

- Version: 1.0.7; versionCode: 8; paquete: `com.cccontrol.mobile`.
- [Release publica](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.7-8).
- Archivo: `CocinaComidaControl-1.0.7-8.apk`, 129114783 bytes.
- SHA-256: `5d29dbba7d6756bafac4fdca8fd7d5d71903ba1de140ce0dadbed0f27edda585`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- EAS Build: `4b67bba9-6f74-47e8-8ea1-6e01d05634a8`, finalizado correctamente.
- Fuente movil: `7cb8559d23d73bfaf317de6c53613f1c17a41a2c`. Se cotejaron los archivos enviados con ese commit. El empaquetado sin VCS no registra gitCommitHash en EAS.
- APK standalone, debuggable=false, API HTTPS y accesos demo ocultos.
- Firma valida e igual a 1.0.6. Descarga anonima completa: tamano y hash coinciden con el artefacto de Expo.

## Cambios

Correcciones de coma decimal, validacion de fechas e importes, doble envio,
teclado y area segura, regreso a Mi cocina y recarga de listas al recuperar
foco. El backend vincula el stock al cierre de compras y evita volver a
ingresar movimientos ya procesados. Las compras historicas ambiguas requieren
revision y no se reparan automaticamente.

## Publicacion web

- Commit de metadatos: `6daa2612854e76aea4e0c502d7d6605bb44f51cf`.
- Render Live: `dep-daq09g0u01pc73e5vsag`.
- Landing, login, registro y salud: HTTP 200.
- Landing: Descargar APK 1.0.7.
- `/descargas/android`: HTTP 302 al archivo de esta release, con no-store.
- API publica en dominio propio y onrender.com: version 1.0.7, build 8.
- Pruebas locales de descarga y contrato HTTP de version aprobadas, sin consultas SQL ni cookies de sesion para el chequeo.
- Se conservaron las APK anteriores. No se cambiaron planes, secretos ni datos de los usuarios durante esta publicacion.

## Comprobacion en el celular

La firma y las pruebas automatizadas no acreditan por si solas la instalacion
ni el comportamiento fisico. La prueba de esta APK en el Samsung se realiza
despues de la publicacion.

1. Mantener 1.0.6 instalada y cerrar completamente la app.
2. Reabrir con internet; comprobar el aviso Actualizacion disponible.
3. Pulsar Ir a descargar; comprobar que abre la landing y ofrece 1.0.7.
4. Instalar como actualizacion, sin desinstalar, y comprobar la sesion.
5. Reabrir 1.0.7: no debe ofrecer actualizar al mismo build.

El aviso se consulta al iniciar el proceso, no al volver de cualquier pantalla.
No se modifico la metadata publica para simular versiones futuras.
