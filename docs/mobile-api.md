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
