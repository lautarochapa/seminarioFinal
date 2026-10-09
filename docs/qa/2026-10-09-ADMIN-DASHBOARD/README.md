# QA-WEB-01 - Resumen de administracion

Pedido de Lautaro del 09/10/2026: explicar el dashboard y mostrar cantidades de
supermercados, sucursales, ingredientes, productos y los demas modulos relevantes.

## Problema y cambio

El dashboard anterior contaba todas las ejecuciones de scraping, todos los
candidatos de productos y las filas de alertas/errores. No representaba el catalogo
publicado. Sus cuatro tarjetas inferiores repetian textos fijos
`Estado: Listo`, `Origen: PostgreSQL` y `Accion: ABM`; no eran estados reales.

El resumen nuevo utiliza consultas de conteo de solo lectura, filtradas por los
permisos de acceso a cada pantalla. Los productos, ingredientes, cadenas y
sucursales aparecen primero. Se distinguen registros totales, productos publicados,
publicaciones por cadena o sucursal, historial de precios, recetas oficiales, importaciones,
pendientes, actividad de scraping y cuentas/roles. Configuracion del catalogo queda
en una seccion propia. Cada cifra explica su alcance y enlaza al listado real.
La navegacion por secciones no oculta cantidades.

Los productos publicados usan el mismo filtro del catalogo publico: no eliminados,
`status=active` e `is_active=true`. Los demas totales aclaran cuando incluyen
inactivos. Los registros soft-deleted se excluyen mediante Eloquent. Las recetas
oficiales no cuentan recetas privadas. Ejecuciones, alertas y errores se separan
por productos/recetas; un historial de errores no se anuncia como falla actual.

El dashboard no ejecuta scraping ni consulta supermercados. Las otras pantallas
administrativas ahora calculan solo las metricas que muestran, en lugar de contar
todas las tablas en cada visita.

## Entorno y limites

- Copia aislada: OneDrive `seminarioFinal/.runtime/admin-ui-20261009`, rama
  `fix/admin-ui-20261009`, base `8289341e2c53d5ad5c57551fe8b1eeda3db7d63f`.
- Pruebas locales con SQLite aislado y cuentas sinteticas de catalogo, recetas y
  superadmin. No se reutiliza la configuracion productiva ni se ejecutan semillas
  de cuentas reales. HTTP externo de la app bloqueado en el harness.
- Sin migraciones, cambios de permisos, scraping ni modificaciones de datos
  productivos. Cuentas docentes y entregables academicos fuera del alcance.
- La verificacion local no certifica migraciones ni precision numerica especificas
  de PostgreSQL. QA-WEB-01 general conserva sus otros pendientes.

## Validacion

- Backend: **662/662 PASS**, fixtures transaccionales con huellas de todas las
  tablas identicas tras rollback. Comprueba estados, soft-delete, permisos antes
  de consultar, destinos reales, separacion de tipos de scraping y GET sin
  escrituras/reconciliacion. Catalogo:29 tarjetas/28 conteos; recetas:10/9;
  superadmin:42/40. Una pantalla de marcas hace solo un conteo.
- Frontend: **4/4 PASS**, rama Blade real y scripts compartidos; ceros, miles,
  escape HTML, anclas reales, permisos del contrato y ausencia de placeholders.
  Adaptador general:39 ramas/44 formularios PASS.
- Regresion HTTP local: **117/117** rutas de39 pantallas para3 roles; accesos
  permitidos catalogo24, recetas9, superadmin39. DOM real:39/39 PASS.
- Navegador real: superadmin42 tarjetas y catalogo29; catalogo no muestra cuentas
  ni recetas. Escritorio1366x900 y movil390x844 sin desborde horizontal; ancla de
  pendientes desplaza y enfoca el titulo, Tab llega a enlaces con foco visible.
  Enlace Productos abre el listado de los dos productos sinteticos. Consola sin
  errores en el recorrido. Capturas locales adjuntas: sus cifras son sinteticas.
- PHP lint3/3 y `git diff --check` correctos. Revision independiente sin hallazgos
  accionables. No se ejecuto la suite global PHPUnit.

Los JSON y logs adjuntos preservan el detalle. La fuente de los resultados es la
copia de trabajo basada en8289341e mas el diff de esta tanda; ese SHA de base no
identifica por si solo la implementacion nueva.

## Primer control online y ajuste de texto

Fuente inicial publicada `c376985557466940cde0c0bfb11b95cf39c18109`.
09/10/2026 14:44:09ART: CSS SHA256 exacto y salud200 en ambos dominios,4/4PASS.
La sesion anterior caduco al desplegar y se inicio sesion con la cuenta interna
Lautaro Catalogo18. Navegador mostro las29 tarjetas, sin cuentas ni recetas, con
227productos registrados,218publicados,2ingredientes,3cadenas y0sucursales.
La autenticacion puede actualizar metadata de acceso de esa cuenta; no se
realizaron ABM ni operaciones de negocio, y no se usaron cuentas docentes.

Este control detecto una imprecision de texto: las217publicaciones pueden
pertenecer a una cadena sin sucursal asignada. Se aclara "por cadena o sucursal";
no cambia ninguna consulta ni cantidad. Verificacion final de este texto y
cierre operativo quedan en el informe canonico de continuidad.
