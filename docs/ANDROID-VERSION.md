# Aviso de nueva version Android

Implementacion local del 22/09/2026. No desplegada en Render ni incluida aun
en una APK publicada. No cambia la version anunciada: sigue siendo 1.0.5 (6)
en la configuracion local, hasta publicar un nuevo binario.

## Contrato HTTP

`GET /api/v1/mobile/android/version`

Publico, sin token, sesion ni consultas a la base de datos. Tiene el limite
general de la API (60 solicitudes por minuto) y `Cache-Control: no-store`.
Usa `config/mobile.php`, igual que la landing, para evitar dos registros
independientes de la version publicada.

```json
{
  "data": {
    "platform": "android",
    "version": "1.0.5",
    "build": 6,
    "download_page_url": "https://cocinacomidacontrol.com.ar/#descarga-app"
  },
  "trace_id": "identificador-de-la-solicitud"
}
```

Si falta una version, compilacion positiva o URL HTTPS valida de pagina/APK,
responde 503 con codigo `ANDROID_RELEASE_UNAVAILABLE`, sin datos de descarga.
La ruta no acepta una URL de destino enviada por el cliente. No consulta
GitHub en cada inicio; verificar el binario es parte de la publicacion.

## Comportamiento de la app

- Comprueba la version una vez al iniciar el proceso, despues de restaurar
  el estado inicial de autenticacion; tambien funciona sin iniciar sesion.
- Usa el versionCode real del binario, obtenido con expo-application. No usa
  comparacion alfabetica de etiquetas: build 11 supera a build 9, aunque
  las etiquetas visibles sean 1.0.10 y 1.0.9. Igual build o menor no avisa.
- Si hay una compilacion superior: muestra version disponible e instalada,
  con Mas tarde / Ir a descargar. Se puede descartar sin actualizar.
- Solo el boton de descarga abre el navegador, en la seccion de la landing.
  No descarga, instala ni cambia la sesion automaticamente.
- Si la respuesta llega estando en segundo plano, espera al primer plano
  para mostrar el aviso. No repite consultas por navegar o cambiar de foco.
- Red ausente, respuesta invalida, HTTP 404 de un backend anterior o error
  del servidor no bloquean el inicio ni producen una alerta de actualizacion.
  Se utiliza el cliente HTTP existente con sus reintentos GET acotados.
- No compara Expo Go ni otras plataformas con la APK Android. Sin numero
  nativo disponible, omite el chequeo. No envia token ni identificadores del
  dispositivo para obtener los datos publicos de la release.

La dependencia expo-application se agrega en la version compatible con el
SDK instalado. Referencia: [Expo Application](https://docs.expo.dev/versions/latest/sdk/application/).

## Publicar la proxima version

1. Incrementar `expo.version`, `android.versionCode` y la version del paquete
   movil, manteniendo applicationId y firma. Compilar/probar la APK.
2. Publicar el archivo versionado y comprobar descarga anonima, firma y hash.
3. Solo entonces actualizar juntos version/build/tamano/hash/download_url
   en `config/mobile.php`, como se indica en [ANDROID-DOWNLOAD.md](ANDROID-DOWNLOAD.md).
4. Mantener `download_page_url` apuntando a la landing. Puede sobrescribirse
   mediante `ANDROID_DOWNLOAD_PAGE_URL`; entrecomillar el valor en archivos
   .env para conservar el fragmento `#descarga-app`. Si se usa ANDROID_APK_URL,
   actualizar tambien ese destino para que no descargue una version anterior.
5. Desplegar el backend y renovar su cache de configuracion mediante el
   procedimiento habitual de Render. Comprobar API, texto de landing y APK
   descargada antes de anunciar la release.

**Primera instalacion del chequeo:** la APK 1.0.5 no contiene esta funcion.
Hace falta actualizarla manualmente una vez a una APK que la incluya; desde
esa version, las siguientes actualizaciones podran anunciarse al iniciar.
No anunciar builds futuros antes de tener un archivo descargable probado.

## Verificacion

- `tests/Smoke/mobile-version.php`: solicitud HTTP anonima por el kernel de
  Laravel, datos compartidos con landing, cero consultas SQL/cookies,
  configuracion de nueva version, encabezados e invalidos con respuesta 503.
- `mobile/tests/appVersion.test.ts`: compilaciones mayores/iguales/menores,
  misma etiqueta con build nuevo, metadatos invalidos y URLs inseguras.
- `mobile/tests/appUpdateNotice.test.tsx`: aviso unico, segundo plano, cierre,
  fallo de red, apertura explicita, navegador ausente y Expo Go/iOS.
- `mobile/tests/apiClient.test.ts`: el endpoint no lee ni envia el token.

Resultado local del 22/09/2026: 56 suites y 326 pruebas moviles aprobadas;
TypeScript sin errores, ESLint de los archivos de esta funcion aprobado y
exportacion Android completada. Las pruebas HTTP de version y descarga de
landing tambien pasaron. Los 292 archivos comparados entre el proyecto movil
y la copia materializada usada para probar coinciden. Persisten advertencias
previas de `act()` en pruebas de planificacion, sin casos fallidos.
La exportacion valida el bundle, no reemplaza la compilacion de una nueva APK
ni el retest en el Samsung.

Retest fisico pendiente: con una nueva APK instalada, confirmar que no avisa
para la version vigente, y probar un escenario controlado con metadata de una
APK superior realmente disponible. Verificar Mas tarde y el enlace de descarga.
No alterar la version publica solo para forzar un aviso durante la tesis.
