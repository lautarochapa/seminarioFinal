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

Ejecutar este flujo completo en al menos un Android real antes de declarar el MVP validado. El emulador solo sirve como fallback documentado si no hay dispositivo real disponible (dejar constancia explícita del motivo).

| Paso | Resultado esperado | Android real | Emulador | Estado | Evidencia | Observaciones |
|---|---|---|---|---|---|---|
| Instalar APK | Se instala sin errores, ícono/nombre/splash correctos | | | Pendiente | | |
| Crear cuenta | Registro exitoso, sesión iniciada automáticamente | | | Pendiente | | |
| Cerrar y abrir app | La sesión persiste (no vuelve a login) | | | Pendiente | | |
| Recuperar contraseña | Mensaje neutral, email de reset recibido, deep link abre `ResetPasswordScreen` con token/email precargados | | | Pendiente | | Verificar también token vencido/inválido |
| Crear grupo | Grupo creado y seleccionado como activo | | | Pendiente | | |
| Escanear producto | Cámara abre, código detectado, producto mostrado o mensaje "no encontrado" | | | Pendiente | | Probar también con permiso de cámara denegado (debe ofrecer ingreso manual) |
| Agregar stock | Item agregado a stock del grupo | | | Pendiente | | |
| Generar lista desde receta | Lista creada con items faltantes calculados correctamente | | | Pendiente | | Probar una receta con stock parcial en otra unidad (ej. receta en gramos, stock en kg) |
| Ver equivalencias / sustituciones | Si el ingrediente no tiene producto propio pero hay un sustituto configurado, se muestra el aviso "Se usará X como reemplazo de Y" | | | Pendiente | | |
| Ver precio estimado | Cada item muestra precio unitario, origen del precio (badge) y subtotal; ítems sin precio muestran "Sin precio disponible" (nunca "$0") | | | Pendiente | | |
| Cerrar y abrir lista | Se puede salir de la pantalla de la lista y volver a entrar | | | Pendiente | | |
| Confirmar que precio persiste | Al reabrir la lista, el precio y su origen son los mismos que al generar, aunque el precio de mercado haya cambiado mientras tanto | | | Pendiente | | |
| Iniciar compra | Sesión de compra creada, items pendientes visibles | | | Pendiente | | |
| Ingresar precio real | Precio real guardado por item, sin bloquear el resto del flujo | | | Pendiente | | |
| Finalizar | Resumen muestra stock creado/actualizado/omitidos y el presupuesto actualizado ("Gastado este mes"/"Disponible") si existe un presupuesto para el período | | | Pendiente | | |
| Ver stock | Los productos comprados aparecen agregados o incrementados en stock | | | Pendiente | | |
| Ver purchase | La compra muestra precios reales y total correcto | | | Pendiente | | |
| Ver presupuesto actualizado | El resumen de presupuesto refleja el gasto de la compra recién finalizada | | | Pendiente | | |
| Probar offline | Sin conexión: banner visible, lecturas cacheadas disponibles, mutaciones bloqueadas con mensaje claro (no hay cola offline) | | | Pendiente | | |
| Probar permisos | Denegar cámara/ubicación no rompe la app; se puede seguir usando el resto de las funciones | | | Pendiente | | |
| Logout | Limpia token, usuario, grupo activo y cache offline; vuelve a login | | | Pendiente | | |
| Login nuevamente | Login exitoso post-logout, estado limpio (sin datos de la sesión anterior) | | | Pendiente | | |

> Estado del MVP mientras este flujo siga en `Pendiente`: **MVP AUTOMÁTICAMENTE VALIDADO — PENDIENTE QA FÍSICA**. No usar "MVP FUNCIONALMENTE CERRADO" hasta completar esta tabla en un dispositivo real.

## APK

- [ ] `eas build --platform android --profile preview` ejecutado con sesión de EAS activa (`eas login`).
- [ ] Artefacto `.apk` descargado y verificado (tamaño razonable, no es un build corrupto).
- [ ] Build instalado en un dispositivo/emulador limpio (sin datos de builds anteriores) y probado el flujo de login.

## Instalación

- [ ] APK instalado vía `adb install <archivo>.apk` o transferencia directa (no usar Play Store para builds `preview`).
- [ ] Verificar que el ícono, nombre y splash se ven correctos post-instalación.

## Rollback

- [ ] Conservar el `.apk` de la versión anterior hasta confirmar que la nueva versión es estable.
- [ ] Si el backend tiene cambios de contrato incompatibles, coordinar el rollback de mobile y backend juntos (no hay versionado de API por ahora — un mobile viejo contra un backend nuevo puede romper contratos).
- [ ] Documentar en este archivo cualquier incidente post-release y su resolución.
