# QA-WEB-01 — Interfaz de administración

Fecha: 09/10/2026. Base publicada: `ef3246b11a73389a4f44526f0bd7cb4aabe4b00c`.

Se unificó la interfaz de las 41 pantallas administrativas vigentes con el panel de usuario: navbar con controles y teclado compartidos, botones consistentes, formularios en diálogos y secciones en pestañas accesibles. Las altas y ediciones conservan los nodos originales y sus permisos/eventos; los errores mantienen lo escrito y los guardados exitosos cierran el diálogo. Se retiraron acciones duplicadas y enlaces de encabezado sin funcionalidad implementada.

Casos particulares incluidos: configuración dinámica, recetas oficiales/importadas, validación de candidatos, solicitudes y alertas, unidades/conversiones, nutrientes, precios y mapa de sucursales. El marcado de pestañas nativas conserva sus loaders y el enlace al tab de Auditoría. Se conserva el foco visible de teclado.

## Evidencia

- `routes-report.json`: 123/123 solicitudes del kernel Laravel correctas. Catálogo permite 26 pantallas; recetas 9; superadmin 41. Se comprobaron 76 respuestas 200 y 47 rechazos 403 esperados.
- `dom-rendered-report.json`: 41/41 HTML renderizados con los scripts reales. Formularios originales conservados dentro de su root, etiquetas, IDs únicos, tabs y diálogos accesibles.
- `tests/JavaScript/admin-crud-modals.test.cjs` y `navbar-menu.test.cjs`: 30/30 casos PASS. Incluyen 19 ABM, errores 422, cancelar/nuevo, detalles, precios, mapa y menús por rol/teclado.
- `tests/JavaScript/admin-workflows.test.cjs`: 6/6 PASS. Configuración protegida/redacted, doble envío, valores ante error, recetas oficiales, importación URL/texto y preferencias de salud. Usa Blade/JS reales con API simulada, sin servidor ni DB.
- `handlers-report.json` y `review-handlers-report.json`: 14 y 11 verificaciones locales adicionales sobre HTML renderizado; API simulada y tráfico externo bloqueado en los harnesses.
- `tests/Smoke/admin-panel-ui.cjs`: 41 ramas del template y 46 formularios fuente PASS. Este análisis estático incluye dos ramas retiradas; la cobertura de las 41 rutas vigentes la demuestra la matriz HTTP, no ese conteo de ramas.
- Regresión del panel común: 8 pantallas PASS; navbar responsive PASS; cocción decimal 21/21 PASS. El smoke anterior esperaba cuatro diálogos de planificación e ignoraba el quinto de cocción ya publicado. Se reprodujo el mismo fallo en `ef3246b` y se actualizó sólo esa expectativa, preservando la cobertura del diálogo existente.
- Sintaxis de los 41 scripts `admin-*.js` y revisión del diff correctas. Revisión independiente sin hallazgos bloqueantes.

## Navegador real

Servidor local aislado, cuentas sintéticas Lautaro QA. Verificado en escritorio 1366×900 y móvil 390×844: navegación por navbar, selección de tabs con flechas, alta/edición en modal, cancelar y restauración de foco. Se editó exclusivamente la descripción del producto sintético Arroz QA local y se verificó el mensaje, cierre y detalle actualizado. En móvil no se observó desborde horizontal de página; diálogo desplazable dentro de la pantalla. Consola sin errores ni advertencias en los recorridos revisados.

- `productos-desktop.jpg`: catálogo con pestañas, botones y datos sintéticos.
- `modal-producto-mobile.jpg`: formulario dentro de modal en móvil.
- `salud-desktop.jpg`: listado a ancho completo, sin formulario lateral ni panel vacío.

## Alcance y límites

La base de QA es un SQLite exclusivo del worktree. Sus copias locales de migraciones adaptan instrucciones exclusivas de PostgreSQL; no se modificaron migraciones productivas. Esta ronda verifica interfaz, eventos y permisos de vistas; no afirma una nueva regresión completa del SQL, transacciones o negocio PostgreSQL.

No se consultaron ni modificaron cuentas docentes; no se ejecutaron scrapings ni mutaciones de producción. Las pruebas no utilizaron datos productivos. Los entregables académicos y los cambios locales del repositorio principal se preservaron. La publicación y sus hashes se registran por separado en el backlog de continuidad.

## Reproducir tests de interfaz

Con Node, `jsdom` (dependencia existente de mobile) y PHP con vendor disponibles:

```text
node --test tests/JavaScript/navbar-menu.test.cjs tests/JavaScript/admin-crud-modals.test.cjs tests/JavaScript/admin-workflows.test.cjs
node tests/Smoke/admin-panel-ui.cjs
node tests/Smoke/panel-ui.cjs
node tests/Smoke/navbar-responsive.cjs
```

Si `jsdom` no está en node_modules raíz, establecer NODE_PATH al node_modules de mobile. La prueba de navbar admite PHP_BINARY. El harness SQLite y el servidor son artefactos locales de `.runtime/qa-admin`; no se publica su base de datos.
