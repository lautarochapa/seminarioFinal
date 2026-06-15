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
