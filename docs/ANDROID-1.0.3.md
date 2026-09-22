# Android 1.0.3 (4) - Registro de release

## Alcance

Nueva APK de prueba para continuar la aceptacion en el Samsung S23 Ultra.
No agrega modulos ni habilita acceso social. Incluye las correcciones AND-01
a AND-08 y el estado real de envio/reenvio de invitaciones familiares.

- Version: 1.0.3; versionCode: 4.
- Paquete: `com.cccontrol.mobile`, sin cambios.
- Fuente: `ed86ff9d9798ce4cfa4e21f8f3fa04b984bc981d`.
- EAS: `ddb51881-1120-495b-ae8f-52b0c132d8c4`.
- Perfil: `preview`, APK standalone, distribucion interna.
- Backend: `https://cocinacomidacontrol.onrender.com`.
- `EXPO_PUBLIC_SHOW_DEMO_USERS=false`.
- `EXPO_PUBLIC_ALLOW_INSECURE_API=false`.
- Inicio de compilacion: 21/09/2026 23:57 ART (22/09 02:57 UTC).

## Correcciones incluidas

1. Puesta en marcha con etiquetas legibles y retorno guiado tras guardar.
2. Grupo familiar con boton centrado y formulario accesible con el teclado.
3. Invitaciones con estado real del correo y reenvio controlado.
4. Login/registro recuperan su estado tras un error y permiten reintentar.
5. Perfil compatible con apellido ausente.
6. Total parcial de compra multiplicado por la cantidad.
7. Finalizacion de compra muestra el resumen o error de la respuesta actual.

En la web se retiraron el SDK, botones y manejadores de Google. Tambien se
retiro el boton de Facebook que apuntaba al mismo acceso legacy. Los endpoints
existentes no se eliminaron en este ajuste de interfaz. En mobile no habia
botones sociales. La recuperacion por email permanece disponible.

## Verificaciones previas

- 44 suites / 233 pruebas moviles aprobadas sobre una copia materializada.
- TypeScript y lint de los archivos modificados aprobados.
- Export Android/Hermes aprobado (1568 modulos).
- Backend: 34 pruebas de autenticacion / 92 assertions en base local aislada.
- Smoke de doble envio de formulario y render de login/registro aprobados.
- Formularios web revisados en escritorio y ancho movil.
- Archivo EAS inspeccionado: 291 archivos, solo `mobile`; 289 archivos fuente
  contrastados con el workspace. Sin `.env`, claves ni dependencias locales.

Los archivos sincronizados de Windows hicieron que Jest descubriera solo 15
suites en el workspace original. La regresion completa se ejecuto sobre una
copia fisica de los archivos con 44 suites y timeout de test de 15 segundos.
Persisten avisos de `act()` en tests de planificacion ya existentes; no se
ocultaron ni se consideran prueba de aceptacion visual en Android.

## Estado del artefacto

Compilacion finalizada, firma verificada y descarga publica disponible el
22/09/2026. [Descargar desde la web](https://cocinacomidacontrol.com.ar/descargas/android).

- Archivo: `CocinaComidaControl-1.0.3-4.apk`.
- Tamano: 129076727 bytes (aprox. 129 MB).
- SHA-256: `3553c4fcdb31f1d7c192910bff5411b066715245a7066f8fc171730960ab136a`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- Firma comprobada con Android apksig 8.8.2; coincide con la APK 1.0.1 (2).
- Manifiesto nativo: paquete `com.cccontrol.mobile`, version `1.0.3`, codigo `4`,
  `debuggable=false`. Contiene el bundle JS standalone.
- Release publicada: `android-v1.0.3-4`, conservando la anterior para rollback.
- Descarga anonima desde GitHub contrastada con el SHA-256 del archivo de EAS.
- Web desplegada desde `c905b461`: verificacion 22/09 00:18:57-00:19:00 ART.
  Salud, landing, login, registro y JavaScript: 200. Descarga: 302 al archivo
  nuevo, con `Cache-Control: no-store`. Sin controles sociales ni demos en
  los formularios publicos. El boton anuncia `Descargar APK 1.0.3`.

## Aceptacion pendiente

Actualizar sobre la APK anterior sin desinstalar y comprobar en el Samsung:
sesion conservada, version 1.0.3, onboarding, teclado/grupos, correo, productos,
escaner y compra. Estas comprobaciones requieren el telefono; los tests y la
firma de la APK no sustituyen la aceptacion fisica.
