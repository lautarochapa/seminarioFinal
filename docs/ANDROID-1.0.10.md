# Android 1.0.10 (11)

Fecha: 09/10/2026. Retiro global de promociones y métodos de pago, confirmado por Lautaro.

## Artefacto verificado

- EAS Build: `edabaa87-787f-44f4-95d6-0d1d04a1a43e`, finalizado el 09/10 a las 17:11:19 UTC.
- Fuente de ejecución: `7d949d7feaffa22468e500b298cccabb63b18ccf`. Se inspeccionaron 317 archivos antes de enviarlos; después sólo se ajustaron expectativas del test de versión, sin cambiar el código móvil compilado.
- Archivo: `CocinaComidaControl-1.0.10-11.apk`; 128741927 bytes.
- SHA-256: `7f1fa7afd043d1800fd510390a8593e6cdde9bda2ddf4351cd9c4174e57b9ff3`.
- Paquete `com.cccontrol.mobile`; versión 1.0.10; versionCode 11; debuggable=false; bundle standalone incorporado.
- Certificado SHA-256: `e29884d881b7fe8c45f2f403970c4b301ea5d61726be3766304da526f4bff483`, igual al APK anterior 1.0.9 (10).

## Cambios y validación

Se retiran pantallas, tarjetas, hooks y peticiones de promociones/métodos de pago. Los enlaces antiguos quedan ocultos y redirigen a Sucursales/Perfil sin solicitar las API retiradas. Las compras conservan el campo de lectura histórica, pero los nuevos envíos omiten la asociación. Sucursales mantiene precios y comparador.

Android: 46 pruebas focalizadas en 6 suites y TypeScript PASS. Web/backend: 43 pruebas web, 163 controles aislados del backend y 117 rutas/39 pantallas renderizadas PASS. El retiro web se verificó por 22 controles HTTP en ambos dominios. [Evidencia de la tanda](qa/2026-10-09-RETIRO-COMERCIO/README.md).

La validación de firma/paquete/bundle y las pruebas automáticas no sustituyen la instalación física en Samsung. Instalar como actualización, sin desinstalar la versión anterior, y comprobar sesión, hogar, sucursales y compras. La instalación física de 1.0.10 queda pendiente.

## Publicación

[Release Android 1.0.10 (11)](https://github.com/lautarochapa/seminarioFinal/releases/tag/android-v1.0.10-11).

Descarga anónima completa verificada el 09/10/2026 a las 17:20:47 UTC: tamaño y SHA-256 exactos. Se conservan las releases anteriores. La configuración de la web se actualizó sólo después de esa verificación; la landing y `/api/v1/mobile/android/version` utilizan la misma metadata. Smokes de landing/descarga y API de versión se ejecutan antes de publicar esa configuración.
