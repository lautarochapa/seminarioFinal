# Rendimiento del panel web

## Cambios

- Roles y permisos se resuelven una vez por usuario y peticion. No se guardan permisos en cookies, sesion ni una cache compartida; se vuelven a consultar en la siguiente peticion.
- Cada pantalla calcula solo los contadores que muestra. Inicio, stock y perfil no consultan los doce contadores antiguos.
- La web utiliza sesion Laravel con cookie HttpOnly y SameSite=Lax, Secure por defecto en produccion, y CSRF para las escrituras. Los endpoints de Android y su autenticacion bearer conservan su contrato.
- Login y registro web usan /web-session/login y /web-session/register. Su respuesta ya contiene el usuario y el nuevo CSRF: no necesitan otra consulta a /auth/me para redirigir.
- /web-data/family-groups/{id}/stock agrega la pagina de stock, ubicaciones y totales. Reutiliza repositorios, permisos y validacion de pertenencia al hogar. Mantiene eager loading de producto, imagenes, unidad y ubicacion; calcula totales en SQL, sin hidratar todo el stock.
- Stock y perfil cargan las pestanas secundarias al abrirlas. Compras no espera los catalogos ni los planes de los modales para mostrar las listas. Las opciones del registro editado se conservan aunque los catalogos lleguen despues.
- Turbo Drive 8.0.23 conserva el encabezado entre Inicio, Mi cocina, Recetas, Plan, Compras, Presupuesto, Grupo familiar y Mi perfil. El menu se reemplaza cuando cambia la identidad o el conjunto de enlaces autorizado por el servidor.
- Las otras pantallas conservan navegacion completa. No se cambian los ciclos de camara, mapas ni exportaciones.
- Los modulos se inicializan de nuevo por visita, eliminan listeners y temporizadores al salir y descartan respuestas de lectura de la pantalla anterior. La navegacion espera las escrituras pendientes.
- No se hace prefetch ni se guardan snapshots privados en la cache de Turbo. Cada visita vuelve a pasar por la autorizacion del servidor.
- El HTML del panel usa Cache-Control: no-store, private. Al restaurar una pagina desde la cache de historial del navegador, se oculta su contenido anterior y se revalida la sesion mediante una carga completa.
- El panel ya no descarga el paquete legacy app.js de Vue/jQuery. Su menu usa un controlador pequeno con clic, flechas, Escape y foco. Leaflet se carga solo en Sucursales.

## Comparacion local

Fecha: 2026-09-24. Mismo alcance que el diagnostico inicial: controlador y render de Blade, usuario comun y base PostgreSQL local de QA.
No incluye middleware ni es una medicion de latencia de produccion.

| Pantalla | Consultas anteriores | Consultas actuales |
| --- | ---: | ---: |
| Mi cocina | 138 | 2 |
| Recetas | 136 | 3 |
| Plan | 136 | 4 |
| Compras | 136 | 4 |
| Presupuesto | 136 | 4 |
| Grupo familiar | 136 | 3 |
| Mi perfil | 136 | 2 |
| Inicio | 144 | 8 |

Los scripts locales referenciados por el panel pasan de aproximadamente 2,51 MB a 0,74 MB sin comprimir, una reduccion cercana al 71%.
Son tamanos de archivos, no bytes transferidos por HTTP. Los modulos principales comparten assets para que no sea necesario cargarlos otra vez al navegar.
La prueba de arranque de stock con hogar necesita dos lecturas: hogares y overview, frente a hasta catorce en el flujo anterior.

## Verificacion

- WebPerformanceTest: consultas acotadas incluyendo middleware, ausencia de contadores inutilizados, assets, cuatro roles y revocacion entre peticiones.
- WebSessionTest: CSRF real, login, registro, logout, ausencia de token nuevo en la web, bearer invalido y compatibilidad Android.
- WebStockOverviewTest: autorizacion por hogar, paginacion, totales equivalentes y consultas acotadas con productos.
- Smoke panel-performance.cjs: carga diferida, edicion con catalogos asincronos, menu por teclado, montaje unico, listeners, respuestas obsoletas, guardados pendientes y cambio de identidad.
- Smoke panel-ui.cjs, api-client-response.cjs y auth-form-submit.cjs: modales, pestanas, errores HTML/JSON y prevencion de doble registro.
- Recorrido manual local en Chrome: navegacion principal, formularios, guardados, historial y cierre de sesion.

## Publicacion y limites

Esta optimizacion no cambia el plan de Render, la region de Neon, el numero de workers ni el esquema de base de datos.
La sesion sigue usando el driver configurado; no promete persistencia entre reinicios cuando se utiliza almacenamiento efimero.
Los tiempos de produccion deben volver a medirse despues del despliegue con las mismas rutas y sin prueba de carga.
No se debe extrapolar el tiempo local a Render: las consultas restantes, la distancia Render-Neon y los arranques en frio siguen influyendo.

Turbo se distribuye localmente con su licencia MIT en public/js/vendor/turbo-8.0.23/.
Referencia: https://turbo.hotwired.dev/handbook/building
