# CocinaComidaControl — API Reference para Mobile

Documentación de los endpoints utilizados por la app mobile. Verificada contra el backend Laravel 7 + PostgreSQL.

---

## Base URL

| Ambiente          | URL                                    |
|-------------------|----------------------------------------|
| Android emulator  | `http://10.0.2.2:8000`                 |
| iOS simulator     | `http://127.0.0.1:8000`               |
| Dispositivo físico| `http://<IP-LOCAL-PC>:8000`            |
| Staging           | `https://staging.cccontrol.com`        |
| Producción        | `https://api.cccontrol.com`            |

Configurar mediante variable de entorno `EXPO_PUBLIC_API_URL` en `.env.local`.

---

## Headers comunes

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <access_token>   (en todos los endpoints autenticados)
```

---

## Autenticación

### Login

```
POST /api/v1/auth/login
```

**No requiere Authorization header.**

**Body:**
```json
{
  "email": "usuario@cccontrol.test",
  "password": "12345678"
}
```

**Respuesta 200:**
```json
{
  "data": {
    "id": 1,
    "name": "Usuario",
    "lastname": "Demo",
    "username": "demo_user",
    "email": "usuario@cccontrol.test",
    "phone": null,
    "avatar_url": null,
    "status": "active",
    "last_login_at": "2026-07-01T19:06:09+00:00",
    "email_verified_at": "2026-07-01T13:46:14+00:00",
    "roles": [{ "code": "user", "name": "Usuario comun" }],
    "permissions": ["profile.manage", "family.manage", "..."],
    "created_at": "2026-07-01T13:46:14+00:00"
  },
  "token": {
    "access_token": "eyJ0eXAiOiJKV1...",
    "token_type": "Bearer",
    "expires_at": "2026-08-01T00:00:00+00:00",
    "roles": [{ "code": "user", "name": "Usuario comun" }],
    "permissions": ["profile.manage", "..."]
  },
  "trace_id": "uuid"
}
```

> **IMPORTANTE:** El token está en `response.token.access_token`, NO en `response.data.access_token`.

**Errores:**
| Status | Code | Descripción |
|--------|------|-------------|
| 401 | `AUTH_INVALID_CREDENTIALS` | Email o contraseña incorrectos |
| 422 | `VALIDATION_ERROR` | Campos inválidos |
| 429 | `TOO_MANY_REQUESTS` | Rate limit: 10 req/min. Respeta `Retry-After` header |
| 500 | `INTERNAL_ERROR` | Error del servidor |

---

### Me (identidad actual)

```
GET /api/v1/auth/me
```

**Requiere Authorization Bearer.**

**Respuesta 200:**
```json
{
  "data": {
    "id": 1,
    "name": "Usuario",
    "lastname": "Demo",
    "email": "usuario@cccontrol.test",
    "roles": [...],
    "permissions": [...]
  },
  "token_payload": {},
  "trace_id": "uuid"
}
```

> Usar para validar sesión al iniciar la app.

**Errores:** 401 si el token es inválido o expiró.

---

### Logout

```
POST /api/v1/auth/logout
```

**Requiere Authorization Bearer.**

**Respuesta:** `204 No Content`

> Limpiar sesión local aunque el servidor devuelva error.

---

### Recuperación de contraseña (implementado)

```
POST /api/v1/auth/forgot-password
```

**Body:** `{ "email": string }`

**Rate limit:** `throttle:5,1` (5 req/min por IP).

**Respuesta 200 (siempre igual, exista o no el email — sin enumeración):**
```json
{ "data": { "message": "Si el email está registrado, recibirás un enlace de recuperación." }, "trace_id": "uuid" }
```

**Errores:** `422` solo por formato de email inválido, `429` por rate limit.

```
POST /api/v1/auth/reset-password
```

**Body:** `{ "token": string, "email": string, "password": string, "password_confirmation": string }`

**Rate limit:** `throttle:60,1`.

**Respuesta 200:** `{ "data": { "message": "Contraseña restablecida correctamente." }, "trace_id": "uuid" }`

**Errores:** `422` con `error.code = "AUTH_RESET_TOKEN_INVALID"` (token inválido o vencido — expira a los 60 minutos, `config('auth.passwords.users.expire')`), `422` `VALIDATION_ERROR` por campos faltantes/contraseña débil/confirmación distinta.

**Deep link:** el email de reset apunta a `cccontrol://reset-password?token={token}&email={email}` (configurado vía `ResetPassword::createUrlUsing` en `AppServiceProvider`, no a la vista web legacy de Laravel UI). La pantalla `mobile/app/(auth)/reset-password.tsx` lee `token`/`email` con `useLocalSearchParams`. Si faltan, muestra un mensaje de enlace inválido con botón para solicitar uno nuevo — nunca expone el token en logs ni en la UI.

---

## Perfil de usuario

```
GET /api/v1/users/me/profile
```

**Respuesta 200:**
```json
{
  "data": {
    "id": 1,
    "name": "Usuario",
    "lastname": "Demo",
    "email": "usuario@cccontrol.test",
    "phone": null,
    "birth_date": "1992-05-15",
    "gender": "male",
    "height_cm": 178,
    "current_weight_kg": 80.5,
    "target_weight_kg": 75,
    "activity_level": "moderate",
    "meals_per_day": 4,
    "notes": null,
    "objectives": [],
    "preferences": {
      "uses_app_for_health": true,
      "uses_app_for_budget": true,
      "uses_app_for_organization": true
    }
  },
  "trace_id": "uuid"
}
```

> **Sin `meta`** — respuesta de objeto único.

---

## Grupos familiares

### Listar grupos del usuario

```
GET /api/v1/family-groups
```

**Respuesta 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Familia Demo",
      "owner_user_id": 1,
      "city_id": 1,
      "default_address": "Mitre 1234, Bariloche",
      "default_latitude": "-41.1335000",
      "default_longitude": "-71.3103000",
      "status": "active",
      "created_at": "2026-07-01T13:46:16.000000Z",
      "updated_at": "2026-07-01T13:46:16.000000Z",
      "deleted_at": null
    }
  ],
  "trace_id": "uuid"
}
```

> **Sin `meta`** — NO es respuesta paginada. `data` es un array directo.

---

### Detalle de un grupo

```
GET /api/v1/family-groups/{id}
```

**Respuesta 200:** igual que un elemento del listado, dentro de `data`.

**Errores:** 403 si el usuario no pertenece al grupo (incluso si el grupo existe — prevención IDOR).

---

### Crear grupo

```
POST /api/v1/family-groups
```

**Body:**
```json
{
  "name": "Familia García",
  "default_address": "Av. San Martín 100",
  "city_id": 1
}
```

**Respuesta 201:** grupo creado en `data`.

**Errores:**
| Status | Code | Descripción |
|--------|------|-------------|
| 409 | `USER_ALREADY_HAS_FAMILY_GROUP` | El usuario ya tiene un grupo activo |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

---

### Miembros del grupo

```
GET /api/v1/family-groups/{id}/members
```

**Respuesta 200:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "name": "Usuario",
      "email": "usuario@cccontrol.test",
      "role": "owner",
      "status": "active",
      "joined_at": "2026-07-01T13:46:16.000000Z"
    }
  ],
  "trace_id": "uuid"
}
```

---

### Invitar miembro

```
POST /api/v1/family-groups/{id}/invitations
```

**Solo el owner del grupo puede invitar.**

**Body:**
```json
{ "email": "nuevo@usuario.com" }
```

**Respuesta 201:** invitación creada.

**Errores:**
| Status | Code | Descripción |
|--------|------|-------------|
| 409 | `*_ALREADY_MEMBER` | Ya es miembro o tiene invitación pendiente |
| 404 | `USER_NOT_FOUND` | No existe usuario con ese email |
| 422 | `VALIDATION_ERROR` | Email inválido |

> En desarrollo, el email de invitación se loguea en `storage/logs/laravel.log` (no se envía por SMTP).

---

## Formato de errores

Todos los errores devuelven:

```json
{
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "message": "Mensaje legible para el usuario.",
    "details": [],
    "field_errors": {
      "email": ["El campo email es obligatorio."]
    }
  },
  "trace_id": "uuid"
}
```

**Nunca** se devuelven errores con status 200.

---

## Status HTTP utilizados

| Status | Significado |
|--------|-------------|
| 200 | Éxito |
| 201 | Creado |
| 204 | Éxito sin body |
| 401 | No autenticado / token inválido |
| 403 | Sin permiso o recurso ajeno |
| 404 | No encontrado |
| 409 | Conflicto / duplicado |
| 422 | Error de validación |
| 429 | Rate limit |
| 500 | Error del servidor |

---

## Trazabilidad

Cada respuesta incluye `trace_id` (UUID). Incluirlo en reportes de soporte.

En desarrollo, el cliente mobile puede mostrar el `trace_id` al usuario para facilitar el diagnóstico.

---

## Compras y sesiones

### Finalizar sesión de compra

```
POST /api/v1/family-groups/{groupId}/shopping-sessions/{sessionId}/finish
Body: { "stock_location_id"?: number | null }
```

**Requiere Authorization Bearer.**

Comportamiento verificado en backend:
- crea una `Purchase` confirmada (`status: "confirmed"`, `purchase_date: hoy`);
- para cada scan matcheado con producto y cantidad > 0: crea o incrementa un `StockItem` (match por `product_id`+`unit_id`+`stock_location_id`+`status=active`+`expiration_date IS NULL`), crea `StockMovement` y `PurchaseItem`;
- scans sin producto o con cantidad 0 se omiten (`stock_skipped_count`, `stock_warnings`) sin afectar stock;
- `stock_location_id`: si no se envía, usa la única ubicación activa del grupo (si hay más de una, exige que se envíe explícitamente); una ubicación inexistente/inactiva para el grupo devuelve `422 STOCK_LOCATION_NOT_FOUND`;
- calcula `estimated_total`/`actual_total` de la `Purchase` a partir de los items comprados;
- marca la sesión como `finished` y la lista como `completed`;
- una segunda finalización devuelve `409 SHOPPING_SESSION_ALREADY_FINISHED` (no crea una segunda `Purchase`).

**Impacto en presupuesto:** no hay un paso adicional que "aplicar" — `GET .../budgets/{id}/summary` y `.../projection` calculan `spent_amount` dinámicamente (`SUM(purchases.actual_total)` del grupo/período con `status != cancelled` y no eliminadas), así que la compra recién finalizada queda reflejada apenas se consulta el presupuesto de nuevo. Mobile debe volver a pedir `budgetsApi.current(groupId)` después de finalizar (no calcular el gasto localmente).

**Respuesta 200:**
```json
{
  "data": {
    "id": 10,
    "shopping_list_id": 1,
    "family_group_id": 5,
    "user_id": 1,
    "supermarket_branch_id": null,
    "purchase_id": 123,
    "started_at": "2026-07-01T19:00:00+00:00",
    "finished_at": "2026-07-01T19:30:00+00:00",
    "status": "finished"
  },
  "summary": {
    "purchase_id": 123,
    "stock_created_count": 2,
    "stock_updated_count": 1,
    "stock_skipped_count": 0,
    "stock_warnings": []
  },
  "trace_id": "uuid"
}
```

> Mobile debe navegar a `/(app)/purchases/{purchase_id}` cuando `purchase_id` esté presente. No debe crear una Purchase duplicada. El resumen final debe mostrar `summary` (stock creado/actualizado/omitido) y, si existe presupuesto para el período, el `spent_amount`/`available_amount` actualizados desde `budgetsApi.current()`.

---

## Convención Expo Router

En Expo Router, un archivo físico:

```text
app/(app)/shopping-lists/[id].tsx
```

se registra en `Stack.Screen`/`Tabs.Screen` como:

```text
shopping-lists/[id]
```

No registrar:

```text
shopping-lists/[id]/index
```

Para carpetas con `index.tsx`, como:

```text
app/(app)/stock/[id]/index.tsx
```

el nombre registrado esperado para el detalle es:

```text
stock/[id]
```

---

## Supermercados Mobile

Endpoints consumidos:

- `GET /api/v1/supermarkets`
- `GET /api/v1/supermarkets/{id}`
- `GET /api/v1/supermarket-branches`
- `GET /api/v1/supermarket-branches/{id}`
- `GET /api/v1/supermarket-branches/nearby?lat={lat}&lng={lng}&radius={km}`
- `GET /api/v1/supermarket-branches/{id}/products`
- `GET /api/v1/supermarket-branches/{id}/promotions`
- `GET /api/v1/products/{productId}/supermarket-prices`
- `GET /api/v1/products/{productId}/best-price`
- `GET /api/v1/products/{productId}/price-history?chain_id=&branch_id=&date_from=&date_to=&per_page=`
- `POST /api/v1/family-groups/{groupId}/stock/manual-product`
- `POST /api/v1/product-requests`
- `GET /api/v1/promotions?chain_id=&branch_id=&product_id=&payment_method_id=&day=&active=&per_page=`
- `GET /api/v1/payment-methods`
- `GET /api/v1/notifications`
- `GET /api/v1/notifications/unread-count`
- `PATCH /api/v1/notifications/{id}/read`
- `PATCH /api/v1/notifications/read-all`
- `GET /api/v1/users/me/notification-preferences`
- `PATCH /api/v1/users/me/notification-preferences`
- `GET /api/v1/family-groups/{id}/reports/stock`
- `GET /api/v1/family-groups/{id}/reports/stock-value`
- `GET /api/v1/family-groups/{id}/reports/waste`
- `GET /api/v1/family-groups/{id}/reports/purchases`
- `GET /api/v1/family-groups/{id}/reports/budget-vs-actual`

Campos reales usados:

- Cadenas: `id`, `name`, `website_url`, `status`.
- Sucursales: `id`, `name`, `address`, `latitude`, `longitude`, `opening_hours`, `delivery_available`, `pickup_available`, `status`, `chain`, `city`, `distance_km`.
- Productos por sucursal: `product`, `branch`, `current_price`, `source_name`, `last_scraped_at`, `status`.
- Precios: `price`, `currency`, `scraped_at/captured_at`, `valid_from`, `valid_to`, `source`, `status`.
- Promociones: `name`, `description`, `discount_type`, `discount_value`, `valid_from`, `valid_to`, `day_of_week`, `requires_payment_method`, `status`.
- Productos manuales: mobile debe usar `POST /api/v1/family-groups/{groupId}/stock/manual-product` cuando una búsqueda o barcode no encuentra resultados. El endpoint crea en una transacción un producto `pending_review`, stock item, movimiento y `ProductRequest` vinculada. El usuario puede usarlo inmediatamente en su grupo; el catálogo global queda curado hasta aprobación admin.
- Solicitudes de producto: `POST /api/v1/product-requests` sigue disponible como cola explícita, pero el flujo principal de stock/barcode no debe bloquear al usuario esperando aprobación.

Ubicacion y mapa:

- `expo-location` se instala con `npx expo install expo-location` para Expo SDK 57.
- `react-native-maps` se instala con `npx expo install react-native-maps` para SDK 57.
- La app no solicita ubicacion al abrir. La pantalla de sucursales solicita permiso solo al tocar `Usar mi ubicacion`.
- Se manejan permiso denegado, permiso bloqueado, servicios apagados, timeout, ubicacion no disponible y sucursales sin coordenadas.
- Si no hay coordenadas, se conserva la lista funcional.

Origen de datos:

- Precios muestran `Origen: manual`, `Origen: scraping` o `Origen: demo` segun `current_price.source` y metadatos disponibles.
- Promociones no exponen origen en el Resource publico actual; mobile las etiqueta como demo cuando no existe otro campo contractual.
- Para scraping se muestran fuente (`source_name`) y fecha (`last_scraped_at` / `scraped_at`) cuando estan disponibles.

Errores parciales:

- Comparacion de precios consume `supermarket-prices` y `best-price` por separado desde `pricesApi.compare`.
- Si una fuente parcial falla, la UI conserva resultados disponibles cuando existan y expone el mensaje de error.
- Precios vencidos con `valid_to` no se destacan como vigentes.

Bloqueos contractuales detectados:

- `GET /api/v1/supermarkets` expone `branches_count` (real, vía `withCount`), pero `logo_url` siempre es `null`: la tabla `supermarket_chains` no tiene columna de logo. Ver `Requerimientos de base de datos` más abajo.
- `PromotionResource` no expone tope (`cap`) ni condiciones estructuradas de la promoción (solo `discount_type`, `discount_value`, `day_of_week`, `requires_payment_method`, y ahora `payment_methods[]` cuando la promoción los tiene asociados).

### Requerimientos de base de datos

```text
- Tabla: supermarket_chains
- Cambio requerido: agregar logo_url (string nullable)
- Motivo: exponer logo de cadena en catálogo mobile/web
- Bloquea: SupermarketChainResource.logo_url (actualmente siempre null)
```

### Historial de precios (implementado)

```http
GET /api/v1/products/{productId}/price-history?chain_id=&branch_id=&date_from=&date_to=&per_page=
```

Paginado. Devuelve `price`, `currency`, `captured_at`, `valid_from`, `valid_to`, `is_current`, `status`, `source`, `chain`, `branch`. Consumido por `pricesApi.history()` y mostrado en `PriceComparisonScreen` (lista simple, sin gráficos) vía `usePriceHistory`.

### Promociones globales (implementado)

```http
GET /api/v1/promotions?chain_id=&branch_id=&product_id=&payment_method_id=&day=&active=&per_page=
```

Por defecto solo devuelve promociones vigentes (`active` implícito `true`); `active=false` incluye vencidas/futuras. Consumido por `promotionsApi.global()` y mostrado en `PromotionsScreen` (tab "Todas" además del tab histórico "Por sucursal").

## Recetas Mobile

### Listado y búsqueda

```http
GET /api/v1/recipes
GET /api/v1/recipes/search
GET /api/v1/recipes/{id}
GET /api/v1/recipe-categories
GET /api/v1/recipe-tags
```

La app mobile usa estos campos cuando están presentes:
- `name`
- `description`
- `prep_time_minutes`
- `cook_time_minutes`
- `servings`
- `category`
- `tags`
- `ingredients`
- `steps`
- `images`

### Favoritos

```http
GET    /api/v1/users/me/favorite-recipes
POST   /api/v1/recipes/{id}/favorite
DELETE /api/v1/recipes/{id}/favorite
```

Mobile persiste favoritos en backend y revierte la UI si falla el cambio optimista.

### Sugerencias

```http
GET /api/v1/recipes/suggestions?family_group_id={id}
GET /api/v1/family-groups/{id}/recipes/available
GET /api/v1/family-groups/{id}/recipes/almost-available
GET /api/v1/family-groups/{id}/recipes/by-expiring-stock
GET /api/v1/family-groups/{id}/recipes/by-budget
GET /api/v1/family-groups/{id}/recipes/by-objectives
```

Mobile muestra el motivo solo si la API lo entrega.

### Generar lista desde receta (implementado)

```http
POST /api/v1/family-groups/{groupId}/recipes/{recipeId}/shopping-list
Body: { "servings"?: number, "shopping_list_id"?: number, "supermarket_branch_id"?: number, "supermarket_chain_id"?: number }
```

- Si no se envía `shopping_list_id`, crea una lista nueva (`source_type: "recipe"`) y responde `201`.
- Si se envía `shopping_list_id`, reutiliza esa lista (debe pertenecer al grupo y no estar `completed`; si está cerrada devuelve `409 SHOPPING_LIST_CLOSED`) y responde `200`.
- Calcula faltantes contra stock del grupo, convierte unidades cuando hay `UnitConversion` disponible (incluye conversiones dependientes del ingrediente, ej. g↔kg), evita duplicados (`items_skipped_duplicate`), e informa ingredientes sin unidad/ingrediente resolubles en `unmapped_ingredients`.
- Resolución de producto por ingrediente, en orden: (1) `specific_product_id` de la receta si existe, (2) producto vinculado directo (`Product.ingredient_id`), (3) el primer sustituto activo en `ingredient_equivalences` que tenga un producto propio. Cuando se usa (3), el ingrediente sustituto se reporta en `substitutions` y como advertencia en `warnings` — nunca se reemplaza en silencio.
- Precio estimado por producto resuelto, con prioridad: sucursal seleccionada (`supermarket_branch_id`) → mejor precio de la cadena seleccionada (`supermarket_chain_id`) → último precio pagado por el grupo → mejor precio disponible globalmente → `null` (nunca `0`) si no hay ninguno.
- Paquetes necesarios: `ceil(faltante_convertido_a_unidad_de_paquete / net_quantity_del_producto)`; si el producto no tiene `net_quantity`/`package_unit_id` cargados o la unidad no es convertible, no se estima precio (se informa en `warnings`).
- **La metadata de precio queda persistida** en cada `shopping_list_item` (`price_source`, `price_updated_at`, `supermarket_chain_id`, `supermarket_branch_id`, `source_type: "recipe_generation"`, `source_id: <recipe_id>`) — al reabrir la lista más tarde, el precio mostrado es el que se usó al generar, aunque el precio de mercado haya cambiado. Editar manualmente `product_id`/`unit_id` de un item invalida esa estimación (`price_source` pasa a `"manual"`, `estimated_price` vuelve a `null` salvo que se envíe uno explícito en el mismo `PATCH`); editar solo `quantity` no la invalida (`estimated_subtotal` no se persiste, se deriva de `estimated_price * quantity`).
- Respuesta: `{ shopping_list, items_added, items_skipped_duplicate, unmapped_ingredients, priced_items, estimated_total, items_without_price, warnings, substitutions }`.

Consumido por `recipeShoppingListApi.generate()` desde el botón "Generar lista de compras" en `RecipeDetailScreen`, que navega a la lista resultante y muestra un resumen (`ShoppingGenerationSummary`, `EstimatedPriceRow`, `PriceSourceBadge`). `ShoppingListDetailScreen` muestra la misma metadata persistida al reabrir la lista.

**Requerimiento de base de datos ya resuelto:** la persistencia de precio requirió la migración `2026_07_08_000039_add_price_metadata_to_shopping_list_items` (columnas nullable en `shopping_list_items`, sin romper datos existentes).

La generación desde meal plan sigue disponible por separado:

```http
POST /api/v1/family-groups/{id}/meal-plans/{planId}/generate-shopping-list
```

---

## Planning y Meal Plans

```http
GET    /api/v1/family-groups/{id}/meal-plans
POST   /api/v1/family-groups/{id}/meal-plans
GET    /api/v1/family-groups/{id}/meal-plans/{planId}
PATCH  /api/v1/family-groups/{id}/meal-plans/{planId}
DELETE /api/v1/family-groups/{id}/meal-plans/{planId}
GET    /api/v1/family-groups/{id}/meal-plans/{planId}/items
POST   /api/v1/family-groups/{id}/meal-plans/{planId}/items
PATCH  /api/v1/family-groups/{id}/meal-plans/{planId}/items/{itemId}
DELETE /api/v1/family-groups/{id}/meal-plans/{planId}/items/{itemId}
POST   /api/v1/family-groups/{id}/meal-plans/{planId}/generate-shopping-list
```

Mobile muestra `period_type`, `start_date`, `end_date`, `mode`, `status` e `items`. Las acciones profesionales quedan sujetas a permisos y endpoints profesionales separados.

---

## Credenciales demo (solo desarrollo local)

| Email | Contraseña | Rol |
|-------|-----------|-----|
| `usuario@cccontrol.test` | `12345678` | user |
| `superadmin@cccontrol.test` | `12345678` | super_admin |
| `nutricionista@cccontrol.test` | `12345678` | nutritionist |
| `admin@cccontrol.test` | `password123` | super_admin (seed manual) |

> Activar sección demo en la app: `EXPO_PUBLIC_SHOW_DEMO_USERS=true` en `.env.local`.

---

## Diferencias emulador vs dispositivo físico

| Situación | URL |
|-----------|-----|
| Android emulator | `http://10.0.2.2:8000` (10.0.2.2 = localhost del host) |
| iOS simulator | `http://127.0.0.1:8000` |
| Dispositivo físico (Android/iOS) | `http://<IP-LOCAL>:8000` (ej: 192.168.1.x) |
| Expo Go en dispositivo | igual que dispositivo físico |

---

## CORS

El backend tiene CORS completamente abierto para `/api/*`:
- `allowed_origins: ['*']`
- `allowed_methods: ['*']`
- No se requiere configuración adicional en el cliente mobile.

---

## Manejo de red y modo offline

Implementado sin dependencias nativas de conectividad (no hay `NetInfo`): `src/utils/networkStatus.ts` es un store en memoria con 3 estados (`online` / `offline` / `reconnecting`) alimentado por los resultados reales de `fetch` en `src/api/client.ts`, más un heartbeat cada 10s mientras no está `online`.

- Banner global (`OfflineBanner`) en `app/_layout.tsx`, visible en toda la app (login incluido).
- Reintentos automáticos solo en `GET` (hasta 2 reintentos con backoff 500ms/1500ms). `POST/PATCH/PUT/DELETE` nunca se reintentan solos.
- Mutaciones bloqueadas de inmediato (sin llamar a `fetch`) cuando el estado es `offline`, devolviendo un `ApiError` con `code: 'OFFLINE'`; las pantallas conservan el formulario y muestran el mensaje sin perder los datos ingresados.
- Cache mínima de último contenido exitoso (`src/storage/offlineCache.ts`, sobre `@react-native-async-storage/async-storage`) para lecturas `GET` de: perfil (`/users/me/profile`), grupos familiares y sus sub-recursos (`/family-groups/**`, incluye stock y listas de compras), catálogo de productos (`/products`) y notificaciones (`/notifications`). Si el `fetch` falla, se devuelve la última respuesta cacheada en vez de propagar el error.
- La cache se limpia completamente al cerrar sesión o al expirar el token (`AuthContext.clearSession`).

## Seguridad mobile

- Token de sesión y grupo familiar activo se guardan únicamente en `expo-secure-store` (`src/storage/secureStorage.ts`). Nunca en `AsyncStorage`.
- `AsyncStorage` se usa solo para la cache no sensible descripta arriba.
- Al cerrar sesión (`AuthContext.clearSession`) se limpia: token, usuario, grupo activo y toda la cache offline.
- `src/config/env.ts` falla al iniciar (`throw`) si `EXPO_PUBLIC_API_URL` usa `http://` fuera de desarrollo (`!__DEV__`), salvo que se declare explícitamente `EXPO_PUBLIC_ALLOW_INSECURE_API=true` para un entorno interno.
- No se loguea `Authorization`, tokens ni contraseñas en ningún punto del cliente.

## Pantallas de error global

- `src/components/ErrorBoundary.tsx`: envuelve `<Slot />` en `app/_layout.tsx`. Ante un error de render de React muestra una pantalla con "Reintentar" y "Volver a inicio", sin dejar la pantalla en blanco.
- `app/+not-found.tsx`: ruta 404 de Expo Router para cualquier deep link o ruta inválida, con link para volver a inicio.
- Errores de red/servidor por pantalla siguen usando `ErrorState` (ya existente) con reintento y `trace_id` visible.

## Ajustes (`/(app)/settings`)

Accesible desde Perfil. Incluye: grupo familiar activo (con acceso para cambiarlo), preferencias de notificaciones (reutiliza `useNotificationPreferences`), versión de la app, ambiente y diagnóstico (`API URL`, ids) solo en desarrollo, información de soporte, política de privacidad marcada como pendiente de publicación, y cerrar sesión.

## Notificaciones push

No implementadas: el backend no tiene infraestructura de device tokens (no existen columnas/tablas `device_token`/`push_token` ni endpoints para registrarlos). Solo se implementaron notificaciones internas (listado, badge, marcar leída/todas, preferencias, navegación al recurso relacionado según `type` — ver `src/utils/notificationNavigation.ts`).

```text
Requerimientos de base de datos:
- Tabla: device_tokens (nueva)
- Columnas sugeridas: id, user_id, token, platform, created_at, updated_at
- Motivo: registrar tokens de Expo push por dispositivo/usuario
- Bloquea: registro y envío de push notifications reales desde mobile

- Tabla: notifications
- Cambio requerido: agregar entity_type (string nullable) y entity_id (bigint nullable)
- Motivo: permitir deep-link a un recurso específico (ej. una lista de compras puntual), no solo a la sección
- Bloquea: navegación exacta al recurso; actualmente solo navega a la sección según `type`
```
