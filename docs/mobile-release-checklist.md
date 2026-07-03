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

- [ ] Android: solo se declaran los permisos usados (`ACCESS_COARSE_LOCATION`, `ACCESS_FINE_LOCATION` — ubicación para sucursales cercanas). No hay cámara ni notificaciones push declaradas porque no están implementadas.
- [ ] El plugin `expo-location` tiene el mensaje de permiso en español revisado en `app.json`.

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
