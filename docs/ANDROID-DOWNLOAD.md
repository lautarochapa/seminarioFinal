# Descarga de Android desde la web

La landing ofrece un enlace publico y estable: `/descargas/android`.
Laravel redirige al archivo APK de una release de GitHub, sin iniciar sesion
en Expo. El binario no se incluye en Git ni en la imagen Docker de Render.

## Version publicada

Comprobado el 22/09/2026 a las 00:19 ART:
https://cocinacomidacontrol.com.ar/descargas/android redirige con 302 a la
release `android-v1.0.3-4`, sin cache. [Registro de release](ANDROID-1.0.3.md).

- Version: 1.0.3, compilacion 4, version de prueba.
- Archivo: `CocinaComidaControl-1.0.3-4.apk`.
- Tamano: 129076727 bytes.
- SHA-256: `3553c4fcdb31f1d7c192910bff5411b066715245a7066f8fc171730960ab136a`.
- EAS Build: `ddb51881-1120-495b-ae8f-52b0c132d8c4`.
- Incluye AND-01/08. Firma valida e igual a 1.0.1, mismo paquete, no debug.
- Descarga anonima comprobada; prueba de actualizacion en Samsung pendiente.
- La APK 1.0.1 (2) se conserva en su release anterior como respaldo.

## Antecedente: build 1.0.2 (no contiene los ultimos arreglos)

El repaso del 21/09 corrigio AND-05 a AND-08 localmente. El build siguiente es
anterior y no los incluye. Recompilar desde el codigo corregido; no confundir
la configuracion 1.0.2 con un artefacto nuevo validado. Ver
[informe de app](entrega-final/evidencias-android/2026-09-21/README.md).

- Version 1.0.2, compilacion 3, fuente `bd08f21b`.
- EAS Build `144cf45c-0c5a-4b86-9e4f-73164a6178c3`.
- Incluye correcciones de onboarding, rotulos, formulario de grupo y estados
  de envio/reenvio de invitaciones. Export Android y TypeScript aprobados.
- Este build no se eligio para la entrega; fue reemplazado por 1.0.3 (4).
  Su historial no acredita aceptacion fisica ni publicacion de la version 1.0.2.
- API HTTPS en onrender.com, usuarios demo ocultos y mismo applicationId.
  El dominio propio no modifica automaticamente el backend de una APK.

## Publicar una nueva version

1. Compilar y probar una APK con el mismo applicationId y la misma clave de
   firma Android. Incrementar version y versionCode para actualizar la app
   instalada sin desinstalarla.
2. Descargar el artefacto final de EAS, comprobar tamano y SHA-256. No usar una
   AAB para la descarga directa ni una compilacion que dependa de Metro.
3. Crear una release de prueba en GitHub con un tag nuevo, por ejemplo
   `android-v1.0.2-3`, y adjuntar la APK con nombre versionado. No sobrescribir
   el archivo anterior. Publicar primero el archivo y comprobar su descarga
   anonima e integridad antes de ofrecerlo en la web.
4. Actualizar juntos `version`, `build`, `size_bytes`, `sha256` y la URL por
   defecto en `config/mobile.php`. `ANDROID_APK_URL` permite cambiar el destino
   por entorno; si esta variable ya existe en Render, actualizarla tambien o
   quitarla para usar el valor del repositorio.
5. Ejecutar `php tests/Smoke/landing-download.php` (actualizar sus expectativas
   de version y tamano al cambiar la release) y
   `node tests/Smoke/navbar-responsive.cjs`. Desplegar en Render.
6. Comprobar que el boton anuncia la nueva version, que `/descargas/android`
   devuelve 302 con `Cache-Control: no-store`, y que el archivo descargado
   coincide con el SHA-256 de la release. Instalar sobre la version anterior
   en un celular y validar que mantiene los datos de acceso.

Si no hay URL configurada, la landing no muestra un boton de descarga y la
ruta responde 503. Solo se admiten destinos HTTPS configurados en el servidor;
la ruta no acepta URLs provistas por quien la visita.

## Capturas de la landing

- `public/images/landing/web-dashboard-martin.jpg`: captura de la web local
  funcionando con una base temporal aislada y el usuario ficticio Martin Lopez.
- `public/images/landing/mobile-home-preview-martin.jpg`: vista previa simulada
  en navegador, basada en `mobile/src/screens/HomeScreen.tsx`, el tema y los
  iconos actuales. Usa el mismo resumen del hogar ficticio, no una captura
  nativa de Android. La landing lo indica tanto en texto visible como en el alt.
- Renovacion: 2026-09-21. Hogar Lopez, ocho productos y dos proximos a vencer;
  no se modificaron cuentas existentes ni datos de produccion.
- Las imagenes anteriores (`web-dashboard.png` y `android-home.png`) y las
  evidencias de tesis permanecen intactas. La simulacion no reemplaza pruebas
  en el Samsung ni acredita el funcionamiento de una nueva APK.

Las capturas se pueden ampliar. Los nombres nuevos evitan reutilizar las
imagenes anteriores desde cache. Al renovarlas, actualizar archivo, dimensiones
HTML, alt y procedencia; no incluir datos personales ni credenciales.
