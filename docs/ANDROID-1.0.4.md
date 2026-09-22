# Android 1.0.4 (5) - Registro de release

## Alcance

APK de prueba para repetir las incidencias encontradas en el Samsung S23
Ultra sobre 1.0.3. No agrega modulos ni incluye borradores de tesis.

- Version: 1.0.4; versionCode: 5.
- Paquete: `com.cccontrol.mobile`, sin cambios.
- Fuente: `13c4256e8d2c845cf8bcd9a9b2a7ed65882832cb`.
- EAS: `90f28011-e477-4789-b662-2fc0c90613f7`, finalizado el 22/09/2026.
- Perfil: `preview`, APK standalone, distribucion interna.
- Backend: `https://cocinacomidacontrol.onrender.com`.
- Usuarios demo ocultos; API insegura deshabilitada.

## Correcciones respecto de 1.0.3

1. AND-09: recupera el grupo familiar al iniciar con una sesion persistida;
   evita que una respuesta tardia restaure un grupo tras cerrar sesion o
   sobrescriba una seleccion manual mas reciente.
2. AND-11: Inicio y Mi cocina recargan al volver a la pantalla, para reflejar
   productos creados o editados; Inicio descarta respuestas obsoletas.
3. AND-12: el buscador de productos de Compras incluye el grupo familiar
   de la lista, tanto al abrir como al escribir una busqueda. El temporizador
   de busqueda se limpia al salir.

Conserva las correcciones anteriores de onboarding, teclado, invitaciones,
reintento de autenticacion, perfil y resumen de compra. AND-10 (contraste de
la barra de estado en Perfil) no se corrige en esta version.

## Verificaciones

- 47 suites / 249 pruebas aprobadas, con timeout habitual de 5 segundos.
- TypeScript y lint de archivos modificados aprobados.
- Export Android/Hermes: 1568 modulos, bundle standalone generado.
- Archivo EAS inspeccionado: 294 archivos cotejados por SHA-256 con la fuente;
  solo mobile, sin `.env`, claves, dependencias locales ni documentos de tesis.
- APK verificada con Android apksig 8.8.2; mismo certificado que 1.0.3 (4).
- Manifiesto nativo: paquete correcto, version 1.0.4, codigo 5,
  `debuggable=false`; contiene `assets/index.android.bundle`.

Persisten avisos de `act()` en tests de planificacion preexistentes. Las
pruebas automatizadas y la firma no sustituyen la aceptacion fisica.

## Artefacto

- Archivo: `CocinaComidaControl-1.0.4-5.apk`.
- Tamano: 129076719 bytes (aprox. 129 MB).
- SHA-256: `d642dc7fc77643ab9a4be0ef7ff171d5b7c5b58f2c664c33d08e1ca4d8838543`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- Release publicada: [android-v1.0.4-5](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.4-5).
- Descarga anonima comprobada; tamano y SHA-256 coinciden con el archivo de EAS.
- Smoke local de landing/descarga y navbar responsive aprobados.
- La version anterior se conserva como respaldo, sin sobrescribir su APK.

## Aceptacion fisica pendiente

Instalar como actualizacion sobre 1.0.3, sin desinstalar ni borrar datos:

1. Comprobar la version 1.0.4 y que continua la sesion.
2. Cerrar y abrir la app; confirmar que restaura el hogar seleccionado.
3. Revisar que Inicio y Mi cocina coinciden; crear o editar un producto y
   volver para comprobar el refresco sin reiniciar la app.
4. Buscar ese producto desde una lista del mismo hogar y agregarlo una vez.
5. Continuar las pruebas pendientes de compra, escaner, correo y coccion.

No considerar estos pasos aprobados hasta ejecutarlos sobre el binario nuevo.
