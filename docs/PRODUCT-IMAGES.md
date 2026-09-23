# Fotos de productos

## Diagnostico del 22/09/2026

El backend ya tiene `product_images`, carga administrativa por archivo o URL
y asociacion de fotos al revisar candidatos de scraping. El catalogo web ya
renderiza miniaturas y galeria con esos registros.

La app, en cambio, mostraba un icono fijo en catalogo/detalle y stock/detalle.
Su tipo ProductImage declaraba `url` aunque la API entrega `image_url`.
La respuesta de stock tampoco incluia las fotos del producto.

## Correccion incorporada en 1.0.6

- ProductPhoto compartido en las cuatro pantallas Android, con prioridad
  para la foto principal activa, dimensiones estables y ajuste contain.
- Si no hay foto o falla la carga, conserva el icono de producto. Una foto
  fallida no impide que se muestre la de un producto diferente.
- Usa `image_url`; resuelve rutas de almacenamiento contra el servidor API,
  admite HTTPS y descarta protocolos inseguros/credenciales en la URL.
  HTTP solo se permite en desarrollo, no en la APK de produccion.
- Stock precarga la relacion product.images, sin consultas por cada fila al
  serializar. Alta, edicion y detalle conservan esa relacion.
- Catalogo y stock solo entregan fotos activas. No cambian productos,
  permisos, marcas, existencias ni datos de produccion.

La correccion forma parte de Android 1.0.6 (7) y su backend. Consultar el
[registro de release](ANDROID-1.0.6.md) para comprobar su publicacion.
La version instalada 1.0.5 sigue igual hasta actualizarla.

## Cargar las fotos que falten

1. Entrar con permisos de administracion a Productos y abrir el producto.
2. En Imagenes, usar una foto real del envase correspondiente, por archivo
   o URL HTTPS autorizada. No asociar una foto de otra marca/presentacion.
3. Marcar la principal y guardar. Comprobar primero la foto en el catalogo web.
4. En Android 1.0.6 o posterior, refrescar catalogo/Mi cocina y abrir el detalle.

No se hicieron cargas masivas, scraping adicional ni cambios de datos online.
La consulta anonima al catalogo online devuelve 401; queda pendiente revisar
con sesion la cobertura real de fotos y sus enlaces. No asumir que todos los
productos tienen imagen porque ya existe el soporte.

Los archivos locales usan el disco public configurado en Laravel. Antes de
cargar archivos en produccion hay que verificar su persistencia entre
despliegues, la URL publica APP_URL y el acceso a /storage; la correccion del
visor no agrega almacenamiento persistente. Las fotos remotas se descargan
directamente en el cliente, no se procesan dentro de Render.

## Verificacion

- `mobile/tests/productPhotos.test.tsx`: seleccion principal/activa, rutas,
  HTTPS, ausencia/error de imagen y dimensiones estables.
- `mobile/tests/productPhotoScreens.test.tsx`: foto visible en las cuatro
  pantallas con una respuesta de API representativa.
- `tests/Smoke/product-images.php`, incluido en `initialize-cloud.php`:
  PostgreSQL descartable local, catalogo/stock, fotos desactivadas, alta,
  edicion, producto sin foto y cero consultas SQL al serializar las fotos.

Resultado local: 58 suites / 343 pruebas moviles aprobadas, TypeScript y
ESLint aprobados, exportacion Android completada y smoke de PostgreSQL
aprobado. Siguen los avisos previos de act() en tests de planificacion,
sin casos fallidos.

Pendiente de retest fisico en la nueva APK: una foto real disponible, un
producto sin foto, enlace roto y uso sin conexion. Los tests de componente
no sustituyen comprobar la descarga y visualizacion nativas en el Samsung.
