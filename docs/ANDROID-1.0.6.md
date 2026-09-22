# Android 1.0.6 (7) - Registro de release

## Alcance

- Version 1.0.6; versionCode 7; paquete `com.cccontrol.mobile`.
- Perfil `preview`: APK standalone, API HTTPS, usuarios demo ocultos.
- Correcciones del recorrido probado en Android 1.0.5: finalizacion de compras
  con productos manuales, importes, reintentos, refresco de stock y validaciones.
- Ajustes de formularios, teclado, areas seguras y contraste de barra de estado.
- Aviso opcional de actualizacion mediante `GET /api/v1/mobile/android/version`.
- Fotos de productos en catalogo, stock y detalle, con alternativa para productos
  sin imagen o archivos que no se puedan cargar.
- No requiere migraciones de base de datos ni cambios de plan de alojamiento.

## Publicacion

En preparacion. La landing conserva la version anterior hasta verificar el nuevo
binario, su firma, su descarga publica y el despliegue de la web.

## Aceptacion fisica

Pendiente sobre el binario 1.0.6. Instalar sobre 1.0.5 sin desinstalar; comprobar
sesion, compras con productos manuales, importes, stock, fotos y controles con
teclado. Las pruebas automatizadas no sustituyen estas comprobaciones.

El aviso de actualizacion se incorpora en 1.0.6: no puede aparecer en APK
anteriores que todavia no contienen esa funcionalidad.
