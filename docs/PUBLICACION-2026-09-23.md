# Publicacion de catalogo y correcciones

Fecha: 2026-09-23. Verificacion del catalogo en produccion: 14:08 UTC.

## Catalogo publicado

Se incorporaron 100 productos nuevos, revisados y activos en el catalogo compartido por web y Android. Las 100 altas tienen nombre, presentacion, unidad, imagen, codigo de barras y precio en ARS asociado a Carrefour. No se inventaron valores nutricionales ni se modificaron productos de los hogares.

- Fuente: sitio publico de Carrefour Argentina.
- Recoleccion: 11 consultas de una pagina, con pausas de al menos 20 segundos entre consultas y sin reintentos automaticos. No se recibieron bloqueos HTTP 403 ni 429.
- Resultado: 132 candidatos nuevos; 110 superaron la revision y se seleccionaron exactamente 100, distribuidos en 14 categorias de la fuente.
- Controles: duplicados, pertinencia alimentaria, presentacion, unidad, precio positivo, codigo de barras y disponibilidad HTTP/tipo de contenido de la imagen.
- Publicacion mediante el servicio existente de aprobacion, con transacciones y auditoria. La identidad tecnica de importacion permanece deshabilitada y sin roles.
- Los candidatos anteriores y los 32 candidatos nuevos no seleccionados no se publicaron en esta operacion.
- Las imagenes se referencian por URL; no se almacenaron binarios de imagen en Neon.

Los precios corresponden al momento de consulta y pueden variar segun fecha, sucursal y promociones. No se configuro scraping continuo.

## Comprobaciones de produccion

| Control | Resultado |
| --- | --- |
| Productos nuevos activos | 100 de 100 |
| Imagen principal, precio ARS y codigo de barras en base | 100 de 100 para cada control |
| Visibilidad mediante API autenticada | 100 de 100, con imagen y unidad |
| Consulta de precios por API | 3 muestras correctas |
| Busqueda por codigo de barras por API | Coincidencia correcta |
| Total de productos en base | 109: 100 activos y 9 preexistentes sin promover |
| Trabajos de scraping activos al cierre | 0 |

Tamano de Neon antes de la operacion: 16.621.568 bytes. Al cierre: 17.571.840 bytes. Diferencia observada: 950.272 bytes (aproximadamente 0,95 MB), incluyendo candidatos, productos, relaciones, precios y auditorias; no es una medicion aislada de cada tabla.

## Web y backend

Las correcciones del commit `7cb8559d23d73bfaf317de6c53613f1c17a41a2c` quedaron activas en Render mediante el despliegue `dep-dapt7brbc2fs73bv2qjg`. Se verificaron respuestas HTTP 200 para la landing, `/healthz` y la API de version Android.

El cierre de compras vincula los items y registra el ingreso a stock en la misma transaccion. La reparacion no vuelve a ingresar stock ya procesado. Las sesiones historicas con vinculos ambiguos devuelven `STOCK_REPAIR_REQUIRES_REVIEW`, sin alterar sus movimientos anteriores. No se repararon automaticamente la lista #9 ni la compra #8.

## Android y pruebas automatizadas

El codigo movil 1.0.7 (8) esta publicado en GitHub. Incluye correcciones de coma decimal, validaciones y doble envio, teclado y area segura, navegacion de stock y recarga de listas al recuperar foco.

- Backend: 1.701 pruebas, 5.875 aserciones, sin fallos ni errores, sobre PostgreSQL local de QA aislado.
- Mobile: 60 suites y 360 pruebas aprobadas. Se mantienen avisos preexistentes de `act` en pruebas de planificacion.
- TypeScript: `tsc --noEmit` aprobado.
- Exportacion Android Metro/Hermes: 1.574 modulos y 46 assets, aprobada.
- Memoria del ejecutor PHPUnit local: 512 MB; esta configuracion no modifica el limite del servicio Render.

La APK **1.0.7 (8)** fue compilada en Expo y publicada despues de la autorizacion de envio del codigo movil. Se verificaron firma, paquete, version, bundle standalone y descarga anonima completa. La landing y la API ya anuncian 1.0.7 (8). La instalacion y comprobacion fisica en Samsung son una etapa separada; no se dan por aprobadas por el resultado de compilacion. Ver [registro de release](ANDROID-1.0.7.md).

Este documento actualiza el estado de publicacion posterior al [registro de correcciones locales](ANDROID-1.0.6-CORRECCIONES.md). No reemplaza ni modifica sus evidencias historicas.

## Evidencia local

Los informes operativos se conservan fuera del repositorio publico: `.runtime/catalog100-20260923.json`, `.runtime/catalog100-db-verification.json`, `.runtime/catalog100-api-verification.json`, `.runtime/release-1.0.7-jest.json` y `.runtime/release-1.0.7-backend-all.xml`. No se incluyeron credenciales, configuraciones privadas ni borradores academicos en esta publicacion.
