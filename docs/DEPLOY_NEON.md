# PostgreSQL en Neon y web en Render

La base local se conserva para desarrollo. La base online se inicializa por
separado: no subir un respaldo local completo sin revisar datos personales,
tokens y credenciales demo. Guardar los respaldos fuera de Git, por ejemplo en
`.runtime/backups/`.

## Entorno elegido

- Web/API: `https://cocinacomidacontrol.onrender.com`, Render Free en Oregon.
- Proyecto Neon: `cocinacomidacontrol`, plan Free, AWS Ohio, PostgreSQL 18.
- Rama Neon: `production`. Base: `neondb`.
- Desarrollo: PostgreSQL local, base `cccontrol`, configurado en `.env`.
- Migraciones online: conexion directa en `.env.neon`, excluido de Git.

El nombre de la rama de Neon no selecciona la rama Git: Render despliega
`main`. Las contrasenas y la `APP_KEY` se guardan como secretos, no aqui.

## Crear y conectar

1. Crear la cuenta Neon y aceptar sus terminos desde la cuenta personal.
2. Crear un proyecto Free, sin upgrades ni tarjeta, en una region cercana a
   Oregon (region actual del servicio Render). Se puede utilizar la base
   predeterminada `neondb`; no hace falta renombrarla a `cccontrol`.
3. Obtener la conexion PostgreSQL directa desde **Connect** para migraciones.
   El host con `-pooler` puede usarse para las conexiones de la aplicacion.
4. Configurar en Render `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y `DB_SSLMODE=require` con los
   valores de Neon. Si existe `DATABASE_URL`, actualizarlo o quitarlo: tiene
   prioridad sobre los campos separados. No pegar claves en Git ni en logs.
5. Mantener `APP_ENV=production`, `APP_DEBUG=false`, la `APP_KEY` existente y
   `APP_URL=https://cocinacomidacontrol.onrender.com`.

### Compatibilidad con la imagen legacy de Render

La imagen PHP 7.4/Bullseye usa libpq anterior a 14, sin SNI. Ademas de las
variables `DB_*`, configurar en Render estas variables del cliente libpq:

```dotenv
PGOPTIONS=endpoint=ep-young-resonance-b5sf0djb
PGCHANNELBINDING=require
```

El endpoint corresponde al proyecto actual; cambiarlo si cambia la base.
Usar el host directo para esta configuracion. No modificar la contrasena
para agregar el endpoint ni desactivar TLS. La conexion con endpoint
explicito se probo desde psql y PDO con SNI desactivado solo en la prueba;
no configurar `PGSSLSNI=0` en Render.

Referencias: [conexion de clientes antiguos a Neon](https://neon.com/docs/connect/connection-errors)
y [variables de libpq](https://www.postgresql.org/docs/18/libpq-envars.html).
Este ajuste no reemplaza la actualizacion pendiente de PHP/Laravel.

## Inicializar sin borrar datos

El plan gratuito de Render no tiene Shell ni Pre-Deploy Command. Ejecutar
desde una PC con PHP y el proyecto instalado, usando un archivo **ignorado**
`.env.neon` con `APP_ENV=production`, `APP_DEBUG=false` y la conexion directa.
No reemplazar `.env`, que pertenece a la base local. Antes, verificar que no
exista una configuracion cacheada en `bootstrap/cache/config.php`.
La clave de aplicacion no es necesaria para estas migraciones/catalogos;
si se utiliza el entorno para autenticar usuarios o cifrar datos, debe tener
la misma `APP_KEY` que Render. Nunca generar otra clave sobre datos cifrados.

```powershell
.\.runtime\php8229\php.exe -d error_reporting=8191 artisan app:initialize-database --env=neon --plan --seed-catalogs
.\.runtime\php8229\php.exe -d error_reporting=8191 artisan app:initialize-database --env=neon --seed-catalogs --force
```

En Linux usar `php` en lugar de la ruta del runtime. El comando ejecuta las
bases compartidas de 2014/2019 y `profiles`, luego las migraciones de 2026 en
orden. No ejecutar `migrate:fresh`, `db:wipe` ni las migraciones legacy de
productos/recetas de 2020 sobre este esquema. Roles y permisos forman parte
de las migraciones; los catalogos se cargan con `--seed-catalogs`.

Para futuras actualizaciones, ejecutar sin `--seed-catalogs` para conservar
personalizaciones de los catalogos. El comando nunca borra tablas ni datos.

## Usuarios y datos de prueba

En QA/produccion la migracion de usuarios demo no crea cuentas con las claves
publicadas en el codigo. El login tampoco muestra esas credenciales. Esto no
modifica ni elimina usuarios preexistentes: una base restaurada exige revisar
esas cuentas antes de publicarla.

Registrar una cuenta propia con clave unica y asignarle el rol necesario de
forma controlada. No ejecutar `DatabaseSeeder`, `demo:prepare` ni los scripts
de admin local indiscriminadamente online: pueden crear/resetear cuentas con
contrasenas conocidas. Preparar datos de demostracion sinteticos por separado.

## Verificacion

- Confirmar HTTP 200 en `/healthz`, `/` y `/login`.
- Probar registro/login y `/api/v1/auth/me` con un token real.
- Verificar permisos para usuario, admin y docente, y rechazo de visitantes.
- Probar stock, recetas, compras y presupuesto desde web y mobile.
- Revisar consumo de almacenamiento, logs y scraping en Neon.
- Exportar un respaldo antes de cambios importantes y ensayar su restauracion
  en una base separada. La copia local es el plan alternativo para la defensa.

`/healthz` verifica la web, no la disponibilidad de PostgreSQL: una respuesta
200 no reemplaza las pruebas autenticadas ni la consulta a la base.

## Prueba local del inicializador

```powershell
.\.runtime\php8229\php.exe -d error_reporting=8191 tests/Smoke/initialize-cloud.php
```

Requiere PostgreSQL local y un usuario con permiso para crear bases. Genera
una base descartable con nombre aleatorio, comprueba inicializacion y segunda
ejecucion, catalogos, permisos y aislamiento de cuentas demo, y elimina solo
esa base al terminar. No utiliza la base `cccontrol` para las pruebas.
