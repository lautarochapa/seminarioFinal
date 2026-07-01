# Smoke Test Manual — CocinaComidaControl Mobile

## Prerrequisitos

1. Backend Laravel corriendo: `php artisan serve --host=127.0.0.1 --port=8000`
2. Configurar `EXPO_PUBLIC_API_URL` en `.env.local`
3. Correr `npx expo start` desde `mobile/`

## Checklist

### Setup

- [ ] 1. Levantar Laravel (`php artisan serve`)
- [ ] 2. Configurar URL en `.env.local` según dispositivo:
  - Android emulator: `EXPO_PUBLIC_API_URL=http://10.0.2.2:8000`
  - iOS simulator: `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000`
  - Dispositivo físico: `EXPO_PUBLIC_API_URL=http://<IP-LOCAL>:8000`
- [ ] 3. Abrir Expo (`npx expo start`)

### Auth

- [ ] 4. Iniciar sesión con `usuario@cccontrol.test` / `12345678`
- [ ] 5. Verificar que llega a Home con saludo con nombre
- [ ] 6. Cerrar y reabrir app (o recargar)
- [ ] 7. Confirmar que la sesión se restauró automáticamente (sin volver a login)

### Perfil

- [ ] 8. Abrir "Mi perfil"
- [ ] 9. Verificar que muestra nombre, email, altura, peso, actividad
- [ ] 10. Pull-to-refresh → recarga sin error

### Grupos familiares

- [ ] 11. Abrir "Grupos familiares"
- [ ] 12. Verificar que aparece "Familia Demo"
- [ ] 13. Seleccionar el grupo → ver detalle con nombre y miembros
- [ ] 14. Pull-to-refresh en el detalle

### Logout

- [ ] 15. Volver a Home y pulsar "Cerrar sesión"
- [ ] 16. Verificar que vuelve a Login
- [ ] 17. Pulsar atrás → no debe volver a Home (sesión limpiada)

### Casos de error

- [ ] 18. Probar contraseña incorrecta → mensaje "Email o contraseña incorrectos."
- [ ] 19. Probar email inválido → error de validación de campo
- [ ] 20. Apagar el backend y probar login → mensaje de conexión fallida (no crash)
- [ ] 21. Apagar backend estando dentro de la app → error claro al abrir Perfil o Grupos

### Rate limiting

- [ ] 22. Hacer más de 10 intentos de login fallidos → mensaje "Demasiados intentos"
- [ ] 23. Botón bloqueado con countdown si llega `Retry-After`
