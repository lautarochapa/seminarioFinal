# Android 1.0.6 (7) - Registro de release

## Alcance

- Version 1.0.6; versionCode 7; paquete `com.cccontrol.mobile`.
- Perfil `preview`: APK standalone, API HTTPS, usuarios demo ocultos.
- Correcciones del recorrido probado en Android 1.0.5: finalizacion de compras
  con productos manuales, importes, reintentos, refresco de stock y validaciones.
- Ajustes de formularios, teclado, areas seguras y contraste de barra de estado.
- Aviso opcional de actualizacion mediante `GET /api/v1/mobile/android/version`.
- Fotos de productos en catalogo, stock y detalle, con alternativa para productos
  sin imagen o archivos que no se puedan cargar.
- No requiere migraciones de base de datos ni cambios de plan de alojamiento.

## Publicacion

- APK compilada y publicada como prerelease el 22/09/2026.
- [Release Android 1.0.6 (7)](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.6-7).
- Archivo: `CocinaComidaControl-1.0.6-7.apk`, 129110371 bytes (aprox. 129 MB).
- SHA-256: `7d6258973e7270f91c5472f1ad0c930464711b275c6dfdc41110132e91d9a81e`.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`.
- Firma comprobada con apksig: coincide con 1.0.5. Manifiesto 1.0.6 (7),
  paquete correcto, debuggable=false y bundle standalone presente.
- Configuracion de web/API actualizada en conjunto. La release anterior se
  conserva como respaldo, sin sobrescribir archivos ni cambiar la firma.
- Descarga anonima completa comprobada: tamano y SHA-256 iguales al binario de EAS.

Verificar Render Live sobre el commit de metadatos, salud HTTP 200,
`GET /api/v1/mobile/android/version` con version 1.0.6/build 7 y
`/descargas/android` con 302 a la APK publicada. La URL estable es
`https://cocinacomidacontrol.com.ar/descargas/android`.

### Resultado online del 22/09/2026, 21:15 Argentina

- Render Live: `dep-daphimnf3r2c73em6flg`, commit
  `034f12d460dccc17a1acd5a29bb0f0c77dd257bc`.
- Tras un intento automatico sin avance visible en clonado, se cancelo ese
  intento y se completo un despliegue con Clear build cache & deploy. No se
  cambiaron el plan, los secretos ni la base de datos.
- Salud, landing, login y registro respondieron HTTP 200; la landing muestra
  Descargar APK 1.0.6 y la descarga estable responde 302 al archivo correcto.
- API publica comprobada en dominio propio y onrender.com: version 1.0.6,
  build 7, pagina de descarga HTTPS, no-store y sin cookies de sesion.
- Firma, integridad y descarga anonima aprobadas. La aceptacion fisica sobre
  esta version sigue pendiente; no se instalo en el celular durante esta release.

## Verificacion previa

- Fuente: `13ff394f76338ca45999d5a141dbdfefb4361005`.
- Build EAS: `247c6ab3-295c-4350-a096-dea8bad43f18`.
- 58 suites / 343 pruebas moviles aprobadas sobre la copia final de la release.
- TypeScript aprobado; ESLint sin errores (un aviso en el setup de tests).
- Export Android/Hermes completado.
- Smoke PostgreSQL local aprobado: compras manuales, precios, rechazo de
  cierres duplicados, stock, presupuesto, fotos activas y ausencia de consultas N+1.
- Smoke HTTP de version aprobado: acceso anonimo, sin sesion ni consultas SQL,
  metadatos validos, errores controlados y cache no-store.
- 312 archivos del paquete EAS cotejados con el commit; sin .env, secretos,
  dependencias locales ni documentos de tesis. Se excluye .claude/settings.json.

El empaquetado EAS se hizo sin VCS para limitar el envio al directorio mobile.
Por eso EAS no registra gitCommitHash: la trazabilidad se comprueba comparando
el archivo inspeccionado con la fuente del commit, normalizando solo CRLF/LF.
Persisten avisos previos de act() en tests de planificacion, sin fallos.

## Aceptacion fisica

Pendiente sobre el binario 1.0.6. Instalar sobre 1.0.5 sin desinstalar; comprobar
sesion, compras con productos manuales, importes, stock, fotos y controles con
teclado. Las pruebas automatizadas no sustituyen estas comprobaciones.

El aviso de actualizacion se incorpora en 1.0.6: no puede aparecer en APK
anteriores que todavia no contienen esa funcionalidad.
