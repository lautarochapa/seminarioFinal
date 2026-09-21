# Correo con Resend

## Estado

Conector HTTPS compatible con Laravel 7 / SwiftMailer, preparado pero no
activado en produccion. No cambia el mailer por defecto ni incorpora claves
al repositorio. El paquete actual resend-laravel requiere Laravel 10 o posterior.

Sin dominio propio verificado, `onboarding@resend.dev` solo permite enviar
al email asociado a la cuenta de Resend. Gmail no puede verificarse como
dominio propio. No activar envios a usuarios reales en esta situacion.

### Evidencia del 20/09/2026

- Se creo la clave `CocinaComidaControl - prueba`, con permiso solo de envio.
- Se envio un unico correo desde el conector local al email de la cuenta,
  con asunto `CocinaComidaControl - prueba de correo`.
- Resend mostro estado **Delivered** para el envio
  `01a0c161-723d-7462-9bef-270b60492f71`. Esto confirma entrega al servidor
  destinatario, no lectura ni ubicacion en la bandeja principal.
- La clave no se guardo en archivos ni se configuro en Render. El servidor
  temporal de prueba se cerro al terminar. No se activaron correos a usuarios.
- Paso `tests/Smoke/resend-mail.php`: contenido texto/HTML, restricciones de
  destinatarios, errores del proveedor, idempotencia y comando sin envio.

## Prueba controlada

Crear una API key con permiso **Sending access**. Guardarla unicamente como
variable de entorno privada, nunca en Git, JavaScript, la APK o documentos.

```dotenv
RESEND_API_KEY=valor_privado_de_Resend
RESEND_TEST_RECIPIENT=cocinacomidacontrol.app@gmail.com
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME=CocinaComidaControl
```

No es necesario cambiar `MAIL_MAILER` para este comando: selecciona Resend
de forma explicita y no altera los demas correos de la app.

```sh
php artisan mail:test-resend
php artisan mail:test-resend --send
```

El primer comando valida presencia de configuracion sin contactar a Resend.
El segundo envia un unico mensaje de prueba al destinatario configurado.
La aceptacion de la API no confirma llegada a la bandeja: revisar el estado
en Resend y confirmar recepcion. No reintentar repetidamente envios inciertos.

La restriccion local bloquea To/Cc/Bcc distintos del destinatario de prueba.
Nunca redirige correos reales: un enlace de recuperacion no debe terminar
en una cuenta distinta de la del usuario. Con `resend.dev` esta proteccion
es obligatoria. El conector admite texto/HTML y rechaza adjuntos explicitamente.

## Antes de activar correos reales

1. Verificar un dominio propio en Resend y usar un remitente de ese dominio.
2. Configurar la clave como secreto en Render y actualizar MAIL_FROM_ADDRESS.
3. Quitar RESEND_TEST_RECIPIENT solo cuando se decida habilitar destinatarios
   reales. Configurar MAIL_MAILER=resend y regenerar la cache de configuracion.
4. Probar recuperacion de contrasena desde web y mobile; revisar sus enlaces.
5. Habilitar `MAIL_TRANSACTIONAL_ENABLED=true` solo cuando el remitente este
   verificado y las variables privadas esten configuradas. Su valor por defecto
   es `false`; la prueba `mail:test-resend --send` sigue siendo independiente.
6. Hacer una prueba de punta a punta con cuentas propias en Render antes de
   abrir el envio a usuarios. No se necesita recompilar Android para enviar;
   los nuevos mensajes de estado y reenvio en la app si requieren nueva APK.

## Integraciones implementadas (locales, pendientes de despliegue)

- Registro API/web legacy y alta nueva por Google: correo de bienvenida;
  iniciar sesion de nuevo no reenvia la bienvenida. No se agrego verificacion
  obligatoria del email ni se bloquea el acceso de cuentas existentes.
- Recuperacion web/API/mobile: correo en castellano con enlace web basado en
  APP_URL, no en el encabezado Host. Abre en PC o navegador del celular, sin
  depender de que este instalada la app. Conserva caducidad y uso unico del
  broker de Laravel. La respuesta publica no revela si existe la cuenta ni
  si fallo el proveedor. El flujo legacy tambien tiene limite de solicitudes.
- Invitacion familiar: se guarda antes de enviar y se mantiene si falla el
  proveedor. La respuesta incluye `data.email_delivery.status`: `accepted`
  (aceptado por el proveedor, no entregado), `disabled`, `restricted`, `failed`
  o `throttled`. La web y la app muestran ese estado sin afirmar entrega.
- El enlace conserva el numero de invitacion a traves del login/registro web
  y lo precarga; el destinatario debe confirmar. Abrir el enlace no acepta
  automaticamente ni agrega miembros. La API exige la cuenta destinataria.
- Reenvio: `POST /api/v1/family-groups/{id}/invitations/{invitationId}/resend`.
  Solo propietario/admin del mismo grupo; solo invitaciones pendientes y
  vigentes; no crea otra invitacion. Limite de un intento por minuto por
  invitacion y cinco solicitudes por minuto en creacion/reenvio.
- El campo role es opcional (member por defecto) para admitir la APK 1.0.1.
- No se usa una cola ni se reintentan automaticamente envios inciertos.
  Registro e invitaciones sobreviven a una falla del proveedor. Los logs
  registran solamente el tipo de correo fallido, nunca claves ni tokens.

No configurar el mailer `log` para estos flujos: fuera de testing se bloquea
para no almacenar enlaces privados ni afirmar que se enviaron.

Pruebas de integracion: `php tests/Smoke/transactional-mail.php`. Crea y elimina
su propia base PostgreSQL local, usa notificaciones falsas y un transporte
en memoria; no modifica la base de la app ni manda correos reales.

Validacion adicional: `node tests/Smoke/auth-mail-links.cjs`, `tsc --noEmit`
y suite movil (10 suites / 51 pruebas al 20/09/2026). Se verifico tambien en
el navegador local visitante -> login -> grupo familiar con el numero de
invitacion precargado; no se acepto ninguna invitacion real durante esa prueba.

Prueba local sin enviar correos: `php tests/Smoke/resend-mail.php`.

Referencias:
- https://resend.com/docs/knowledge-base/403-error-resend-dev-domain
- https://resend.com/docs/api-reference/emails/send-email
