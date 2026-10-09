# Retiro de promociones y métodos de pago

Fecha: 09/10/2026. Alcance confirmado por Lautaro: retirarlos de todo el sistema.

## Cambio

- Web: sin entradas de menú, pantallas ABM, controles ni llamadas a esos módulos para usuario, admin o superadmin.
- API: retiradas las 18 rutas de promociones y métodos de pago. Los permisos exclusivos se excluyen en ejecución; `catalog.manage` sigue disponible para el resto del catálogo.
- Comparación: precios base, sin consultar ni aplicar promociones históricas. La respuesta conserva `promotions: []` para clientes anteriores.
- Compras y precios nuevos rechazan asociaciones a los módulos retirados; omitir el campo o enviarlo como null conserva compatibilidad. Editar una compra antigua mantiene su método histórico.
- Android: eliminadas pantallas, hooks y tarjetas. Los enlaces antiguos redirigen a Sucursales/Perfil sin consultar las API retiradas. Se prepara 1.0.10, build 11, con el mismo paquete y firma.
- Se corrige la lectura web del contrato real de comparación/optimización. Los totales provienen del backend; la estimación aclara que incluye artículos con precio y no acredita cobertura completa.

## Conservación

No se ejecutan migraciones, seeders, borrados, scraping ni escrituras en la base productiva. Se conservan tablas, relaciones y datos históricos. La documentación académica y las cuentas docentes permanecen fuera de esta tarea. La copia principal con cambios locales no se restablece ni se mezcla con esta rama.

Checkout: `C:\Users\lauta\OneDrive\Documentos\GitHub\seminarioFinal\.runtime\admin-ui-20261009`, rama `fix/admin-ui-20261009`, base publicada `3d261b7ffa7813c564955c5fd8ed52ff48686540`.

## Evidencia local

Resultado: 43 pruebas web focalizadas PASS y lint de 20 archivos PHP PASS. La revisión independiente detectó y corrigió el descarte de moneda en el nuevo adaptador: se muestran las monedas originales y no se elige un ganador entre monedas diferentes. [Resumen web](web-report.json) y [lint PHP](php-lint.json).

- `tests/Smoke/retired-commerce.php`: 163/163 controles PASS, sólo contra SQLite aislado de `.runtime/qa-admin`; rechaza ejecutarse sin el aislamiento. Comprueba rutas retiradas, roles, precios base, asociaciones rechazadas, compras históricas, permisos expuestos y rollback con huellas de 22 tablas. [Resultado](backend-report.json).
- `tests/JavaScript/retired-commerce-surfaces.test.cjs`: ausencia de accesos y formularios en Blade/DOM real.
- `tests/JavaScript/retired-commerce-consumers.test.cjs`: sucursales, contrato de comparación vigente/anterior y optimización parcial/vacía.
- Matriz HTTP local: 39 pantallas por 3 roles, 117 solicitudes PASS; DOM renderizado: 39 pantallas, 40 formularios, 50 diálogos y 92 pestañas PASS. Los HTML antiguos de módulos retirados no se incluyen en el inventario vigente. [Rutas](routes-report.json) y [DOM](dom-rendered-report.json).
- Android: 17 pruebas focalizadas y TypeScript aprobados; 29 pruebas adicionales de autenticación/configuración de release y aviso de actualización aprobadas. Un timeout de arranque frío de Jest se resolvió al repetir la misma tanda, sin cambiar código ni ampliar límites.

La evidencia local no equivale a una prueba física del APK ni a ejecutar compras en producción. Los resultados finales, hashes y publicación se agregan al cerrar la entrega.

## Android en compilación

EAS Build `edabaa87-787f-44f4-95d6-0d1d04a1a43e`, perfil preview/APK, versión 1.0.10 (11). Antes del envío se compararon 317 archivos del archivo de EAS contra la fuente local y se verificó que no contuviera secretos ni dependencias. Después se actualizaron únicamente las expectativas del test `releaseAuth.test.tsx` a la nueva versión; no cambió código de ejecución móvil.

La web mantiene anunciada la APK 1.0.9 hasta contar con la nueva APK publicada y verificada. La instalación física sobre el teléfono continúa pendiente; no desinstalar la app existente para actualizarla.

