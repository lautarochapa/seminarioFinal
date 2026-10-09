# QA-WEB-01: etiquetas administrativas en español

09/10/2026. Base: `b43c070c5982c39d4ed6ebcdbdaa130b7133cfa4`.

Pedido: corregir etiquetas en inglés visibles en admin y superadmin.

## Cambio

- Menú, encabezados, métricas, formularios y opciones de las 39 pantallas vigentes en español. Resumen, importación web, ejecuciones, etiquetas y funciones reemplazan los nombres técnicos ingleses visibles.
- Etiquetas de estados, tipos, fuentes, reportes y auditoría traducidas mediante un diccionario de presentación compartido. Los identificadores desconocidos y el texto libre se conservan. Los detalles JSON de auditoría mantienen el registro original.
- Valores de formulario, filtros, rutas, permisos y payloads mantienen sus códigos. Por ejemplo, se muestra `Activo` y se envía `active`.
- Idioma HTML del layout administrativo: `es-AR`; no se cambia la configuración global de idioma ni la validación del servidor.
- El encabezado de publicaciones aclara «por cadena o sucursal», cerrando la observación menor del resumen anterior.

## Verificación local

- **66/66 pruebas** integradas: formularios de catálogo, importaciones, permisos, auditoría, reportes, etiquetas estáticas, menú y resumen.
- **117/117 rutas**: 39 pantallas con tres roles sintéticos. Permisos esperados: catálogo 24, recetas 9 y superadmin 39; los restantes accesos devuelven 403.
- **39/39 pantallas renderizadas**: pestañas, formularios originales, diálogos, foco, IDs únicos. Smoke adicional: 39 ramas Blade y 44 formularios, incluidas ramas históricas; esto no las vuelve accesibles.
- Contrato estático: 1.814 atributos HTML, 90 claves de métricas/permisos y 57 pares ruta/permiso del menú conservados. Bloques retirados sin cambios.
- Sintaxis JS/PHP y `git diff --check` correctos. Revisión independiente de valores/escape y contratos.
- Navegador real con cuenta sintética local: listado y edición muestran `Activo`/`Inactivo`; lectura del select confirma `active`/`inactive`. Cancelación sin guardar. Revisión móvil sin desborde del diálogo; override retirado al terminar.

Evidencia adjunta: `labels-integrated.log`, `routes-report.json`, `dom-rendered-report.json`, `labels-static-contract.json` y capturas locales.

## Límites y continuidad

Pruebas con SQLite aislado y APIs simuladas sin red de terceros. No se ejecutaron migraciones, scraping ni cambios en datos de negocio productivos. Las cuentas docentes y los documentos académicos quedan fuera del alcance. La verificación posterior del despliegue se registra en el backlog y la copia canónica de esta carpeta.

QA-WEB-01 general sigue abierto. Hallazgo previo: `/admin-web/prices` aún utiliza un panel genérico; esta tanda traduce su texto, pero no implementa un nuevo módulo de precios. Se registra para la siguiente revisión funcional.
