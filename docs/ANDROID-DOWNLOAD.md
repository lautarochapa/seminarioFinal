# Descarga de Android desde la web

La landing ofrece un enlace publico y estable: `/descargas/android`.
Laravel redirige al archivo APK de una release de GitHub, sin iniciar sesion
en Expo. El binario no se incluye en Git ni en la imagen Docker de Render.

## Version publicada

- Version: 1.0.1, compilacion 2, version de prueba.
- Archivo: `CocinaComidaControl-1.0.1-2.apk`.
- Tamano: 129071115 bytes.
- SHA-256: `eaf90f9e5cdfa16568af49af8a7bc394104509a464f81fa45a433f1719aab5ef`.
- EAS Build: `ebef7f28-428f-4417-8346-d126a02911da` (2026-09-16).
- No incluye los ajustes moviles hechos durante las pruebas posteriores.

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

`public/images/landing/web-dashboard.png` y `android-home.png` son capturas
reales de las pruebas con cuentas ficticias, no maquetas. Se muestran enteras,
sin deformar, y pueden ampliarse. Para reemplazarlas, usar capturas sin datos
personales y actualizar las dimensiones HTML si cambia su formato.
