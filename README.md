# CC Control - Seminario Final

Aplicacion Laravel para gestion familiar de alimentacion, stock, compras, presupuesto, recetas, reportes y documentacion de tesis.

## Requisitos locales

- Windows con PowerShell.
- PostgreSQL instalado. En esta PC se uso `C:\Program Files\PostgreSQL\18`.
- Base local recomendada: `cccontrol`.
- Usuario local por defecto: `postgres`.
- Password local por defecto: `1234`.

El proyecto incluye un runtime PHP en `.runtime\php8229\php.exe`, usado por los scripts para evitar incompatibilidades con otras instalaciones de PHP.

## Inicializar la base de datos local

Desde la raiz del proyecto:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-database.ps1
```

Ese comando:

- Copia/configura el `.env` local.
- Crea la base PostgreSQL `cccontrol` si no existe.
- Ejecuta todas las migrations en orden.
- Ejecuta una pasada final de `artisan migrate` para aplicar cualquier migration nueva pendiente.
- Ejecuta los seeds basicos de catalogos, roles, permisos, canales y feature flags.
- Crea/actualiza el usuario administrador local.

Credenciales iniciales:

```text
Email: admin@cccontrol.test
Password: password123
```

Si PostgreSQL esta en otra ruta o la password local cambia:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-database.ps1 -Database cccontrol -DbUsername postgres -DbPassword 1234 -PostgresBin "C:\Program Files\PostgreSQL\18\bin"
```

## Levantar la app

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-app.ps1
```

Luego abrir:

```text
http://127.0.0.1:8000
```

## Deploy en Render

El repo ya queda preparado para desplegar en Render usando Docker:

- `Dockerfile`
- `.dockerignore`
- `render.yaml`
- health check web en `/healthz`

### Opcion recomendada

En Render, crear un **Web Service** con:

- `Runtime`: `Docker`
- `Docker Build Context Directory`: `.`
- `Dockerfile Path`: `./Dockerfile`
- `Health Check Path`: `/healthz`
- `Pre-Deploy Command`: `php artisan migrate --force`

### Variables obligatorias

Configurar manualmente en Render:

```text
APP_KEY=base64:...
APP_URL=https://<tu-servicio>.onrender.com
ASSET_URL=https://<tu-servicio>.onrender.com
DB_HOST=<host de postgres>
DB_DATABASE=<db>
DB_USERNAME=<user>
DB_PASSWORD=<password>
```

Y dejar:

```text
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_PORT=5432
DB_SSLMODE=require
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

### Importante

- No usar `.env.qa` como archivo secreto tal cual está hoy si contiene credenciales reales.
- Conviene rotar la password de la base si alguna vez quedó commiteada o compartida.
- Si Render no completa el primer deploy por una migration, volver a correr el deploy luego de revisar logs suele alcanzar.

## Ambientes

- `.env` queda apuntando a PostgreSQL local.
- `.env.qa` queda apuntando a la base QA de Render.
- `.env.qa.example` queda como plantilla sin secretos para compartir.

## Roles principales

- `user`: usuario comun.
- `dietologist`: dietologo / profesional.
- `catalog_admin`: admin catalogo.
- `supermarket_admin`: admin supermercados.
- `recipe_admin`: admin recetas / chef.
- `teacher`: docente.
- `super_admin`: control total.
- `system_jobs`: procesos automaticos del sistema.

Los roles y permisos se cargan de forma idempotente desde `SecuritySeeder` y tambien desde la migration `2026_06_15_000015_seed_actor_roles.php`.

## Script maestro real

El script que hoy debe usarse para dejar una base nueva completamente inicializada es:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-database.ps1
```

Ese script llama a `scripts/bootstrap-local.ps1` y ahora tambien ejecuta una pasada final de `artisan migrate --force`, para no perder migrations nuevas como permisos o pantallas agregadas despues.

Si una PC nueva devuelve `403` en pantallas web o APIs protegidas, normalmente significa que no se aplicaron migrations de permisos/roles. En ese caso, volver a correr el script anterior deberia corregirlo.

## Validacion de cierre

Backend:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\scripts\run-test-suite.ps1
```

Mobile:

```powershell
cd mobile
npm.cmd test -- --runInBand
npm.cmd run typecheck
```

## Stock vencido

El procesamiento automatico de vencimientos se ejecuta con:

```powershell
C:\xampp\php74\php.exe artisan stock:process-expired
```

El comando es idempotente: usa `stock_waste_logs.stock_item_id` para no procesar dos veces el mismo item, crea un movimiento `expiration`, deja el stock en cantidad cero y registra alerta, notificacion y auditoria. Esta programado diariamente en `app/Console/Kernel.php`.

## Mobile en dispositivo fisico

La app mobile toma la URL del backend desde `EXPO_PUBLIC_API_URL`. No dejar IPs LAN hardcodeadas en el codigo fuente.

Para probar en un celular fisico dentro de la misma red:

```powershell
C:\xampp\php74\php.exe artisan serve --host=0.0.0.0 --port=8000
cd mobile
$env:EXPO_PUBLIC_API_URL="http://<IP-LAN-DE-LA-PC>:8000"
$env:EXPO_PUBLIC_ALLOW_INSECURE_API="true"
npx eas-cli@latest build --platform android --profile preview
```

Para produccion, usar una URL HTTPS real y no activar `EXPO_PUBLIC_ALLOW_INSECURE_API`.

## Presupuesto

El presupuesto no almacena un gasto acumulado duplicado. El total gastado se calcula dinamicamente a partir de las compras confirmadas o con stock cargado (`confirmed`, `stock_added`) y excluye compras canceladas, eliminadas o no confirmadas.

## Taxonomias de ingredientes

Las categorias y taxonomias de soporte de ingredientes quedan versionadas en:

```text
database/data/ingredient_categories.json
database/data/ingredient_supporting_taxonomies.json
```

Se cargan con:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\migrate-ingredient-taxonomies.ps1
```

El script agrega las columnas necesarias a `ingredient_categories` y carga categorias, `food_tags` y `allergies` de forma idempotente.
