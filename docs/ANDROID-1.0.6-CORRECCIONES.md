# Correcciones posteriores a Android 1.0.6 (7)

Fecha: 2026-09-23.
Estado: codigo corregido y verificado localmente. Sin despliegue ni APK nueva en esta ronda.

Este registro tecnico complementa las [seis incidencias fisicas](entrega-final/evidencias-android/2026-09-23-1.0.6/BUGS.md). No reemplaza sus evidencias ni afirma una nueva aprobacion en el Samsung.

## Cambios

| Incidencia | Correccion | Verificacion local |
| --- | --- | --- |
| A106-01 | El alta normal y rapida de stock interpreta cantidades y precios con coma decimal. Se conservan los campos opcionales, el precio cero y la posibilidad existente de stock cero en el alta normal. | Valores exactos enviados a la API, alta rapida y reintento sin perder datos. |
| A106-02 | Validacion de fechas reales y precios no negativos; errores locales y HTTP 422 junto al campo correspondiente. Bloqueo de envios simultaneos. | Fecha imposible, precio negativo, fecha bisiesta, errores del servidor y doble pulsacion. |
| A106-03 | Ajuste de altura al abrir el teclado Android, desplazamiento para fecha/precio y margen inferior segun el area segura. El componente de campo conserva su foco visual al ejecutar callbacks. | Simulacion del evento nativo: altura disponible, inset inferior, foco y desenfoque. |
| A106-04 | Retorno explicito a Mi cocina desde alta/detalle; desde edicion al mismo producto. Boton fisico Android equivalente, con listener activo solo en la pantalla enfocada. | Destinos de retorno, guardado de edicion y limpieza del listener. |
| A106-05 | Detalle de lista recargado al recuperar foco; refresh esperable y descarte de respuestas antiguas. | Regreso desde sesion finalizada, cambio de hogar, solicitudes superpuestas y errores tardios. |
| A106-06 | Cierre de sesion registra purchase_item_id y stock_processed_at dentro de la transaccion que ingresa el stock. Reparaciones posteriores no lo duplican. | Cierre seguido de reparaciones repetidas, sin nuevas cantidades, movimientos ni PurchaseItems. |

## Compras de versiones anteriores

Se detectan sesiones finalizadas con ingresos a stock cuyos PurchaseItems no tienen vinculo con los items de lista. En esas listas, la API expone stock_repair_requires_review y rechaza la reparacion con HTTP 409 / STOCK_REPAIR_REQUIRES_REVIEW. La app no ofrece volver a agregar esos articulos.

La proteccion del servidor cubre tambien clientes anteriores que no conocen el campo nuevo. No se infieren correspondencias ambiguas ni se reescriben movimientos historicos. Las omisiones genuinas de otras listas conservan su reparacion idempotente.

No se modificaron Neon, la lista #9, la compra #8 ni el stock del usuario de prueba. Una reconciliacion de vinculos historicos exige revisar sus datos; no es una nueva compra.

## Resultados

- Mobile: 31 suites, 200 pruebas aprobadas.
- Backend: 1701 pruebas, 5875 aserciones, sin fallos; PostgreSQL local de QA aislado con guardas de conexion. No se uso la base de produccion.
- TypeScript: tsc --noEmit aprobado.
- ESLint: aprobado para los archivos modificados, incluido AppInput y las nuevas pruebas.
- Android: exportacion Metro/Hermes aprobada, 1574 modulos y 46 assets.
- Se actualizo la auditoria de rutas para reconocer exclusivamente GET/HEAD de la API publica de version. No se cambiaron rutas ni permisos de produccion.

La suite movil conserva avisos preexistentes de act en las pruebas de planificacion. No representan fallos de las aserciones, pero deben distinguirse de una suite sin advertencias.

La exportacion desde mobile repitio el problema local de indexado ya documentado (archivo existente no resuelto por Metro). La exportacion exitosa se hizo desde una copia normal de 246 archivos, cotejados por SHA256, con las mismas dependencias y sin archivos .env. No se cambio la configuracion del bundler para eludir el error. La [verificacion generada](qa/android-106-correcciones.json) registra hashes y cantidades.

Resultados detallados locales: .runtime/android-106-mobile-all.json, .runtime/android-106-backend-all.xml y .runtime/android-106-export-clean/metadata.json. La configuracion de QA permanece local y no debe publicarse con credenciales.

## Cierre de la publicacion

1. Publicar las correcciones backend para proteger compras y clientes anteriores.
2. Compilar y firmar una nueva APK con versionCode superior a 7; actualizar la descarga y la version anunciada solo cuando el binario exista.
3. Actualizar sobre la instalacion actual sin borrar sus datos; comprobar que mantiene la sesion.
4. Repetir en Samsung: alta con precio 250,50, errores de fecha/precio, visibilidad con teclado y barra inferior, retorno de detalle/edicion y refresco de compra finalizada.
5. Confirmar en una compra nueva que el stock aumenta una sola vez y no aparece reparacion. La lista historica protegida no debe usarse para repetir una compra.

La exportacion Hermes no valida Gradle, firma, instalacion ni el teclado fisico del Samsung. La version instalada y la publicada siguen siendo 1.0.6 (7) hasta completar este cierre.
