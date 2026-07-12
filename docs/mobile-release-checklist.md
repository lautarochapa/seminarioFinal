# Checklist de release — CocinaComidaControl Mobile

## Versionado

- [ ] `mobile/app.json` → `expo.version` incrementada.
- [ ] `mobile/eas.json` → perfil correcto seleccionado (`development` / `preview` / `production`).
- [ ] Changelog o notas de versión comunicadas al equipo.

## Variables de entorno

- [ ] `EXPO_PUBLIC_API_URL` apunta al backend correcto para el ambiente del build (no usar `10.0.2.2` fuera del emulador Android).
- [ ] `EXPO_PUBLIC_SHOW_DEMO_USERS=false` en builds que no sean de desarrollo/QA interno.
- [ ] `EXPO_PUBLIC_ALLOW_INSECURE_API` **sin definir** (o `false`) salvo entorno interno explícito — de lo contrario la app falla al iniciar si `EXPO_PUBLIC_API_URL` no es HTTPS fuera de desarrollo.
- [ ] Variables cargadas vía `eas.json` (`build.<profile>.env`) o secrets del proyecto EAS, no hardcodeadas en el repo.

## Build

- [ ] `npm run typecheck` sin errores.
- [ ] `npm run lint` sin errores.
- [ ] `npm test` — toda la suite en verde.
- [ ] `npx expo-doctor` — 20/20 checks (o los que correspondan) en verde.
- [ ] `npx expo export` corre sin errores (valida que el bundle compila antes de invertir tiempo en un build nativo).
- [ ] Backend: `reset-demo.ps1`, `validate-demo.ps1`, `run-test-suite.ps1` en verde si el build apunta a datos demo.

## Tests

- [ ] Tests de contratos nuevos (historial de precios, promociones globales, generación desde receta) en verde.
- [ ] Tests de red (retry GET, bloqueo de mutaciones offline, reconexión) en verde.
- [ ] Tests de seguridad (logout limpia storage, guard HTTPS en producción) en verde.
- [ ] Tests de navegación (rutas dinámicas, not-found, settings) en verde.

## Permisos

- [ ] Android: solo se declaran los permisos usados (`ACCESS_COARSE_LOCATION`/`ACCESS_FINE_LOCATION` para sucursales cercanas, `CAMERA` para el escáner de código de barras). No hay permisos de notificaciones push declarados porque no están implementadas.
- [ ] El plugin `expo-location` tiene el mensaje de permiso en español revisado en `app.json`.
- [ ] El plugin `expo-camera` tiene el mensaje de permiso en español revisado en `app.json`; probar el flujo cuando el usuario deniega el permiso (debe caer al ingreso manual del código, no romper la pantalla).

## Íconos y splash

- [ ] `assets/icon.png`, `assets/android-icon-foreground.png`, `assets/android-icon-background.png`, `assets/android-icon-monochrome.png` presentes y actualizados.
- [ ] Splash (`expo-splash-screen` plugin) con el color de marca correcto (`#04AC85`).

## API

- [ ] `android.package` / `ios.bundleIdentifier` (`com.cccontrol.mobile`) son los definitivos — no cambiarlos entre builds de la misma app ya publicada.
- [ ] Todos los endpoints nuevos consumidos por mobile están documentados en `docs/mobile-api.md`.

## Login y logout

- [ ] Login con credenciales reales (no demo) probado contra el backend del ambiente objetivo.
- [ ] Logout limpia token, usuario, grupo activo y cache offline (verificado por test automatizado; confirmar manualmente antes de un release público).
- [ ] Sesión expirada (401) redirige a login sin dejar pantallas colgadas.

## Backend local para celular físico

Para probar contra un dispositivo Android real conectado por Wi-Fi (no emulador), el backend debe escuchar en todas las interfaces de red, no solo `localhost`:

```powershell
C:\xampp\php74\php.exe artisan serve --host=0.0.0.0 --port=8000
```

Después, averiguar la IP LAN de la PC (Windows: `ipconfig`, buscar el adaptador Wi-Fi activo, campo "Dirección IPv4"), por ejemplo `192.168.1.50`, y usar:

```text
http://192.168.1.50:8000
```

Reglas:

- `10.0.2.2` **solo** sirve para el emulador Android (túnel especial del emulador hacia `localhost` de la PC host) — nunca funciona en un celular físico.
- `127.0.0.1` / `localhost` en el celular físico apunta al propio celular, no a la PC — nunca usar.
- La IP LAN (`192.168.x.x` o `10.x.x.x` según el router) solo funciona si el celular y la PC están en la **misma red Wi-Fi**, y el firewall de Windows permite conexiones entrantes al puerto 8000 (`php artisan serve` escuchando en `0.0.0.0`).
- Para QA con terceros fuera de la misma red, o para builds `production`, el backend debe estar publicado con HTTPS real — no usar la IP LAN.
- Con `EXPO_PUBLIC_API_URL=http://<IP-LAN>:8000` (sin HTTPS), un build `preview`/`production` (no-`__DEV__`) va a fallar al iniciar salvo que también se defina `EXPO_PUBLIC_ALLOW_INSECURE_API=true` — ya seteado así en el perfil `preview` de `eas.json` para este caso de uso.

## Alternativas de build/dev para QA

| Alternativa | Cuándo usarla | Cómo |
|---|---|---|
| Expo Go | QA rápida sin generar APK, iteración veloz | `npx expo start`, escanear el QR con la app Expo Go en el celular (misma red Wi-Fi que la PC) |
| APK preview (EAS) | QA real, más cercano al build final, no requiere Expo Go instalado | `eas build --platform android --profile preview` → descargar `.apk` → instalar con `adb install <archivo>.apk` |
| Dispositivo físico por USB | Debug con logs nativos, developer build | `npx expo run:android` con el celular conectado y depuración USB habilitada, o instalar un `development` build de EAS |

> Expo Go no soporta necesariamente todos los módulos nativos custom del proyecto (ej. `expo-camera` sí es compatible, pero confirmar antes de asumir paridad total con el build standalone). Ante cualquier discrepancia entre Expo Go y el APK real, el APK real es la fuente de verdad.

## Flujos core (ver checklist manual de QA)

- [ ] Login → Inicio → Perfil → Grupo familiar.
- [ ] Catálogo → detalle de producto → agregar/editar stock.
- [ ] Crear lista → agregar item → iniciar compra → finalizar compra → ver compra.
- [ ] Budget.
- [ ] Receta → favorito → generar lista desde receta → abrir lista generada.
- [ ] Planning / meal plans.
- [ ] Supermercados → sucursal → ubicación → comparar precios → historial → promociones.
- [ ] Notificaciones → navegación al recurso relacionado.
- [ ] Reportes.
- [ ] Ajustes → cambiar grupo, preferencias de notificaciones, cerrar sesión.

> Este flujo requiere verificación manual en emulador o dispositivo real — los tests automatizados no reemplazan la prueba visual.

## Flujo obligatorio de prueba física (cierre de MVP)

Ejecutar este flujo completo en al menos un Android real antes de declarar el MVP validado. El emulador solo sirve como fallback documentado si no hay dispositivo real disponible (dejar constancia explícita del motivo en "Observaciones").

Completar una fila por cada ejecución (fecha + dispositivo). Si un paso falla, crear el bug en el tracker del equipo y anotar su ID en "Bug asociado" — no dejar el paso en blanco.

| # | Paso | Resultado esperado | Fecha | Dispositivo | Versión APK | Resultado | Evidencia | Bug asociado |
|---|---|---|---|---|---|---|---|---|
| 1 | Instalar APK | Se instala sin errores, ícono/nombre/splash correctos | | | | Pendiente | | |
| 2 | Crear cuenta | Registro exitoso, sesión iniciada automáticamente | | | | Pendiente | | |
| 3 | Login | Login exitoso con la cuenta recién creada o una demo | | | | Pendiente | | |
| 4 | Forgot password | Mensaje neutral (no revela si el email existe) | | | | Pendiente | | |
| 5 | Reset password por deep link | El link del email abre `cccontrol://reset-password` con token/email precargados; reset exitoso permite loguearse con la contraseña nueva | | | | Pendiente | Verificar también token vencido/inválido |
| 6 | Crear grupo | Grupo creado y seleccionado como activo | | | | Pendiente | |
| 7 | Cambiar grupo | Si hay más de un grupo, cambiar de grupo activo refresca correctamente stock/listas/presupuesto del nuevo grupo | | | | Pendiente | |
| 8 | Escanear código de barras | Cámara abre, código detectado, producto mostrado o mensaje "no encontrado" | | | | Pendiente | Probar también con permiso de cámara denegado (debe ofrecer ingreso manual) |
| 9 | Cargar producto manual | Si el producto no existe en stock/barcode, se crea producto pendiente y stock en una sola operación; el producto queda visible para el grupo con badge de revisión | | | | Pendiente | Verificar duplicado pendiente, barcode existente y barcode inexistente |
| 10 | Agregar stock | Item agregado a stock del grupo usando un producto aprobado del catálogo interno | | | | Pendiente | |
| 11 | Editar stock | Cantidad/ubicación de un item de stock se actualiza correctamente | | | | Pendiente | |
| 12 | Crear receta/favorito (si corresponde) | Marcar/desmarcar favorito persiste y sobrevive a cerrar/abrir la app | | | | Pendiente | |
| 13 | Generar lista desde receta | Lista creada con items faltantes calculados correctamente | | | | Pendiente | Probar una receta con stock parcial en otra unidad (ej. receta en gramos, stock en kg) |
| 14 | Ver sustituciones/equivalencias | Si el ingrediente no tiene producto propio pero hay un sustituto configurado, se muestra el aviso "Se usará X como reemplazo de Y" | | | | Pendiente | |
| 15 | Ver precio estimado | Cada item muestra precio unitario, origen del precio (badge) y subtotal; ítems sin precio muestran "Sin precio disponible" (nunca "$0") | | | | Pendiente | |
| 16 | Cerrar y reabrir lista | Se puede salir de la pantalla de la lista y volver a entrar sin perder datos | | | | Pendiente | |
| 17 | Confirmar persistencia de precio | Al reabrir la lista, el precio y su origen son los mismos que al generar, aunque el precio de mercado haya cambiado mientras tanto | | | | Pendiente | |
| 18 | Iniciar compra | Sesión de compra creada, items pendientes visibles | | | | Pendiente | |
| 19 | Cargar precio real | Precio real guardado por item, sin bloquear el resto del flujo | | | | Pendiente | |
| 20 | Finalizar compra | Resumen muestra stock creado/actualizado/omitidos y el presupuesto actualizado ("Gastado este mes"/"Disponible") si existe presupuesto para el período | | | | Pendiente | |
| 21 | Ver stock actualizado | Los productos comprados aparecen agregados o incrementados en stock | | | | Pendiente | |
| 22 | Ver purchase | La compra muestra precios reales y total correcto | | | | Pendiente | |
| 23 | Ver presupuesto actualizado | El resumen de presupuesto refleja el gasto de la compra recién finalizada | | | | Pendiente | |
| 24 | Probar offline | Sin conexión: banner visible, lecturas cacheadas disponibles, mutaciones bloqueadas con mensaje claro (no hay cola offline) | | | | Pendiente | |
| 25 | Probar reconnect | Al recuperar conexión, el banner desaparece y las lecturas se refrescan (no hace falta reiniciar la app) | | | | Pendiente | |
| 26 | Probar notificaciones internas | La lista de notificaciones internas carga y navega al recurso relacionado al tocar una | | | | Pendiente | |
| 27 | Probar permisos | Denegar cámara/ubicación no rompe la app; se puede seguir usando el resto de las funciones | | | | Pendiente | |
| 28 | Logout | Limpia token, usuario, grupo activo y cache offline; vuelve a login | | | | Pendiente | |
| 29 | Reiniciar app y validar sesión | Tras logout, cerrar la app por completo y reabrirla: debe pedir login (no quedar sesión residual) | | | | Pendiente | |
| 30 | Login nuevamente | Login exitoso post-logout, estado limpio (sin datos de la sesión anterior filtrados de un grupo/usuario previo) | | | | Pendiente | |

> Estado del MVP mientras este flujo siga con pasos en `Pendiente`: **MVP AUTOMÁTICAMENTE VALIDADO — PENDIENTE QA FÍSICA** (o **READY FOR APK BUILD** si ya hay un APK generado). No usar "MVP FUNCIONALMENTE CERRADO" hasta completar esta tabla en un dispositivo real con todos los pasos en `OK`.

## APK

Login a EAS (una sola vez por máquina/usuario):

```bash
npx eas-cli@latest login
```

Antes de buildear para QA en un celular físico por Wi-Fi, editar `mobile/eas.json` → `build.preview.env.EXPO_PUBLIC_API_URL` con la IP LAN real de la PC (ver "Backend local para celular físico" arriba) — **no commitear** ese valor si es una IP interna de una red específica que no debería quedar en el repo compartido; revertir a un valor neutro (o quitarlo) antes de hacer commit si el equipo no quiere fijar una IP en el repo. `EXPO_PUBLIC_ALLOW_INSECURE_API=true` ya está seteado en el perfil `preview` porque ese perfil está pensado para HTTP en LAN, no HTTPS.

```bash
npx eas-cli@latest build --platform android --profile preview
```

- [ ] `eas build --platform android --profile preview` ejecutado con sesión de EAS activa.
- [ ] Artefacto `.apk` descargado y verificado (tamaño razonable, no es un build corrupto).
- [ ] Build instalado en un dispositivo/emulador limpio (sin datos de builds anteriores) y probado el flujo de login.
- [ ] Si el build se generó con `EXPO_PUBLIC_API_URL` apuntando al `10.0.2.2` por defecto (sin editar `eas.json` antes de buildear), **no sirve para QA en dispositivo físico** — solo para emulador. Regenerar con la IP LAN correcta antes de la prueba física.

## Instalación

- [ ] APK instalado vía `adb install <archivo>.apk` o transferencia directa (no usar Play Store para builds `preview`).
- [ ] Verificar que el ícono, nombre y splash se ven correctos post-instalación.

## Rollback

- [ ] Conservar el `.apk` de la versión anterior hasta confirmar que la nueva versión es estable.
- [ ] Si el backend tiene cambios de contrato incompatibles, coordinar el rollback de mobile y backend juntos (no hay versionado de API por ahora — un mobile viejo contra un backend nuevo puede romper contratos).
- [ ] Documentar en este archivo cualquier incidente post-release y su resolución.
