# Android 1.0.5 (6) - Registro de release

## Alcance

APK de prueba para corregir dos incidencias de Compras observadas en el
Samsung S23 Ultra con Android 16 sobre la version 1.0.4. No agrega modulos
ni incluye borradores de tesis.

- Version: 1.0.5; versionCode: 6.
- Paquete: `com.cccontrol.mobile`, sin cambios.
- Fuente: `854bf266aba0a06f3fe669f1eb0c96083122248c`.
- EAS: `0c5f0377-4dd7-44d5-b21c-bbf0a1315b2f`, finalizado el 22/09/2026.
- Perfil: `preview`, APK standalone, distribucion interna.
- Backend: `https://cocinacomidacontrol.onrender.com`.
- Usuarios demo ocultos; API insegura deshabilitada.

## Correcciones respecto de 1.0.4

1. AND-13: los formularios de agregar producto y finalizar compra respetan
   los margenes seguros superior e inferior del dispositivo. El boton de
   confirmacion no debe quedar detras de la barra de navegacion Android.
2. AND-14: el listado de compras se recarga al volver a la pantalla, para
   reflejar una compra finalizada sin arrastrar manualmente para actualizar.
   No duplica la carga inicial ni consulta listas si no hay grupo seleccionado.

Conserva los arreglos anteriores de sesion, grupo familiar, busqueda de
productos y refresco de stock. AND-10 (contraste de la barra de estado en
Perfil) sigue pendiente y no forma parte de esta version.

## Verificaciones locales

- 49 suites / 255 pruebas aprobadas, con timeout habitual de 5 segundos.
- TypeScript y lint de archivos modificados aprobados.
- Export Android/Hermes generado correctamente, con bundle standalone.
- Archivo EAS inspeccionado: 296 archivos cotejados por SHA-256 con la fuente;
  solo mobile, sin `.env`, claves, dependencias locales ni documentos de tesis.
- Commit de fuente publicado antes de compilar; sin otros cambios pendientes
  incluidos en el paquete de compilacion.

Persisten avisos de `act()` en tests de planificacion preexistentes. Las
pruebas automatizadas no sustituyen la aceptacion fisica.

## Artefacto

- Archivo: `CocinaComidaControl-1.0.5-6.apk`.
- Tamano: 129076999 bytes (aprox. 129 MB).
- SHA-256: `16e0199f303d9f5a9dfddd65a1df11481483fa3d0169a818dbc3a4faedaeda94`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- APK verificada con Android apksig 8.8.2; mismo certificado que 1.0.4 (5).
- Manifiesto nativo: paquete correcto, version 1.0.5, codigo 6,
  `debuggable=false`; contiene `assets/index.android.bundle`.

## Estado de publicacion

- Release publicada: [android-v1.0.5-6](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.5-6).
- Descarga anonima comprobada; tamano y SHA-256 coinciden con el archivo de EAS.
- La version anterior se conserva como respaldo, sin sobrescribir su APK.
- Configuracion de la landing actualizada; despliegue web pendiente de verificar.

## Aceptacion fisica pendiente

Instalar como actualizacion sobre 1.0.4, sin desinstalar ni borrar datos:

1. Comprobar la version 1.0.5 y que continua la sesion y el hogar activo.
2. Crear una lista nueva para esta prueba. No volver a finalizar la lista 5,
   que ya fue completada durante las pruebas de 1.0.4.
3. Abrir Agregar producto y Finalizar compra; comprobar los controles visibles
   fuera de la barra de navegacion Android, incluso al usar el teclado.
4. Finalizar una sola compra y volver al listado; debe figurar Completada sin
   refresco manual. Confirmar tambien la actualizacion de stock e Inicio.
5. Repetir las comprobaciones pendientes de escaner, correo, recetas y coccion
   con datos adecuados. Esta release no acredita todos los modulos.

No considerar estos pasos aprobados hasta ejecutarlos sobre el binario nuevo.
