# Auditoría de cierre del alcance inicial (MVP)

Última actualización: 2026-07-08.

Metodología: para cada módulo se verificó (a) existencia de rutas/controladores/servicios backend, (b) cobertura de tests automatizados backend (`tests/Feature/Api/V1/...`), (c) existencia de pantalla mobile y su wiring a la API real (sin `fetch` directo ni datos hardcodeados), (d) tests mobile relacionados. **Ningún módulo fue probado manualmente en dispositivo físico o emulador durante esta auditoría** (bloqueado por falta de aceleración de hardware en el entorno de desarrollo — ver `docs/mobile-release-checklist.md`). Por lo tanto, todo módulo marcado `COMPLETO` se refiere a completitud de implementación y cobertura automatizada, no a validación manual end-to-end; `Pendiente manual` es `Sí` en todos los casos salvo que se indique lo contrario.

Estados usados: `COMPLETO`, `COMPLETO CON LIMITACIÓN`, `PENDIENTE`, `FUERA DEL MVP`.

---

## Autenticación y cuenta

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Registro | COMPLETO | N/A (mobile-only) | COMPLETO | `RegisterScreen`/backend register tests | — | Sí |
| Login | COMPLETO | COMPLETO (legacy) | COMPLETO | tests backend + `loginValidation.test.ts` | — | Sí |
| Logout | COMPLETO | COMPLETO | COMPLETO | cubierto en `AuthContext` | — | Sí |
| Perfil | COMPLETO | COMPLETO | COMPLETO (`ProfileScreen`) | — | — | Sí |
| **Recuperación de contraseña** | COMPLETO | N/A | COMPLETO | 14 tests backend (`PasswordResetTest`), 9 tests mobile (`passwordReset.test.tsx`) | El email de reset usa el deep link `cccontrol://reset-password` (via `ResetPassword::createUrlUsing`), no una vista web legacy. Requiere que el dispositivo tenga la app instalada con el scheme registrado para que el link abra la pantalla in-app. | Sí — probar en dispositivo real que el link de email abre la app. |
| Google Login | COMPLETO (backend) | — | **PENDIENTE** | — | Endpoint `POST /api/v1/auth/google` existe y funciona (`GoogleAuthController`), pero no hay botón ni flujo en el mobile. Explícitamente fuera de esta tanda por pedido del usuario. | — |
| RBAC web | COMPLETO | COMPLETO | N/A | — | — | Sí |

## Grupos y preferencias

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Grupos familiares | COMPLETO | COMPLETO | COMPLETO | tests backend `FamilyGroup`, `familyGroupContext.test.ts` | — | Sí |
| Preferencias / objetivos | COMPLETO | COMPLETO | COMPLETO (`profile-objectives`) | tests backend `Objectives`, `HealthPreferences` | — | Sí |

## Catálogo

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Ingredientes | COMPLETO | COMPLETO (ABM admin) | COMPLETO (tab en `catalog`) | tests `Ingredients` | — | Sí |
| Nutrientes | COMPLETO | COMPLETO (ABM) | COMPLETO CON LIMITACIÓN (solo en detalle de ingrediente, sin pantalla propia) | tests `Nutrients` | Sin pantalla dedicada por diseño (ver CLAUDE.md, "Módulos de soporte") | Sí |
| Unidades / conversiones | COMPLETO | COMPLETO (ABM) | COMPLETO CON LIMITACIÓN (uso implícito en cantidades) | tests `Units` | Sin pantalla dedicada por diseño | Sí |
| Equivalencias de ingredientes | COMPLETO | COMPLETO (ABM) | COMPLETO CON LIMITACIÓN | tests `IngredientEquivalences`, `RecipeSubstitutions`; nuevos tests de sustitución en generación de lista (`RecipeShoppingListTest`) | Hasta esta tanda, la generación de lista de compras ignoraba `ingredient_equivalences`; ahora se usa como fallback ordenado (producto directo → sustituto configurado) y se reporta en `data.substitutions`. La pantalla de "ver equivalencias" (`GET /recipes/{id}/substitutions`) no tiene UI mobile dedicada aún — solo se muestra el aviso de sustitución dentro del resumen de generación. | Sí |
| Stock / ubicaciones | COMPLETO | COMPLETO | COMPLETO | tests `HouseholdStock`, `StockLocations`, `StockMovements`, `StockAlerts` | — | Sí |
| Código de barras | COMPLETO | COMPLETO (admin) | COMPLETO (`BarcodeScannerScreen`, cámara + manual) | tests `Barcodes`, mobile `barcodeScanner.test.tsx` (6 tests) | Requiere Chromium 83+/`expo-camera` soportado; fallback manual siempre disponible | Sí |

## Recetas

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Recetas (ABM/búsqueda) | COMPLETO | COMPLETO (admin) | COMPLETO | tests `Recipes`, `RecipeSearch` | — | Sí |
| Favoritos | COMPLETO | COMPLETO | COMPLETO | tests `RecipeFavoritesCooked` | — | Sí |
| Sugerencias | COMPLETO | COMPLETO | COMPLETO | tests `RecipeSuggestions`, `recipeSuggestionsNormalizer.test.ts` | — | Sí |
| Disponibilidad / faltantes | COMPLETO | COMPLETO | COMPLETO | tests `RecipeAvailability` + nuevo test de descuento de stock parcial cross-unit (g/kg) | — | Sí |
| Sustituciones | COMPLETO (ya existía) | COMPLETO | COMPLETO CON LIMITACIÓN | tests `RecipeSubstitutions` (endpoint standalone) + nueva integración en generación de lista | Solo lectura: el mobile no permite todavía "aceptar/excluir" una sustitución antes de generar (mostraría un cambio de contrato para pasar exclusiones — documentado como mejora futura) | Sí |

## Planificación y compras

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Meal plans | COMPLETO | COMPLETO | COMPLETO | tests `MealPlans`, `MealPlanGeneration`, etc. | — | Sí |
| Listas de compras | COMPLETO | COMPLETO | COMPLETO | tests `ShoppingLists`, `ShoppingListItems`, `ShoppingListGeneration`, `ShoppingListPreview` | — | Sí |
| **Generación desde receta + precios estimados** | COMPLETO | N/A (flujo mobile) | COMPLETO | 25 tests (`RecipeShoppingListTest`) | Prioridad de precio: sucursal → cadena → historial de compras del grupo → mejor precio global → `null` (nunca `0`). Paquetes con redondeo hacia arriba. | Sí |
| **Persistencia de metadata de precio** | COMPLETO | N/A | COMPLETO | 4 tests nuevos de persistencia + 4 tests de invalidación manual (`ShoppingListItemsTest`) | Migración `2026_07_08_000039_add_price_metadata_to_shopping_list_items` agrega `price_source`, `price_updated_at`, `supermarket_chain_id`, `supermarket_branch_id`, `source_type`, `source_id` (todas nullable). `estimated_subtotal` **no** se persiste — se deriva en el Resource (`estimated_price * quantity`) para evitar incoherencia si se edita la cantidad. Editar `product_id`/`unit_id` manualmente invalida la estimación (`price_source='manual'`, precio en `null` salvo que se envíe uno explícito). | Sí |
| Sesiones de compra | COMPLETO | COMPLETO | COMPLETO | tests `ShoppingSessions` (19, incluye doble-finish e impacto en presupuesto) | — | Sí |
| Compras | COMPLETO | COMPLETO | COMPLETO | tests `Purchases`, `PurchaseItems`, `PurchaseConfirmation` | Sin flujo de edición/reversión de una compra confirmada — solo cancelar (soft delete + `status=cancelled`), documentado como inmutabilidad intencional | Sí |
| **Presupuesto — impacto automático** | COMPLETO | COMPLETO | COMPLETO | tests `BudgetSummary` (9, ya existían) + 2 tests nuevos de integración compra→presupuesto | El "gastado" se calcula **dinámicamente** (`SUM(purchases.actual_total)` filtrado por grupo/período/`status != cancelled`/`deleted_at IS NULL`), no vía un ledger persistido — ya era así antes de esta tanda. Esto hace la doble-finalización, cancelación y corrección de compra automáticamente correctas sin trabajo adicional (nunca se duplica el gasto). Existe una tabla `budget_movements` para ajustes manuales, desconectada del cálculo de `spent_amount` (ver `Mejoras post-MVP`). Sin soporte real multi-moneda (`Purchase` no tiene columna de moneda). | Sí — confirmar que la pantalla de compra muestra "Gastado este mes"/"Disponible" actualizados tras finalizar. |
| Presupuesto (ABM/alertas/categorías) | COMPLETO | COMPLETO | COMPLETO | tests `Budgets`, `BudgetAlerts`, `BudgetCategories`, `BudgetMovements` | — | Sí |

## Supermercados y precios

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Supermercados / sucursales | COMPLETO | COMPLETO | COMPLETO | tests `Supermarkets`, `SupermarketBranches` | Sin `logo_url` en `supermarket_chains` (documentado en sesión previa) | Sí |
| Precios | COMPLETO | COMPLETO | COMPLETO | tests `SupermarketPrices`, `PriceHistory`, `PriceRefreshRequests`, `SupermarketComparison` | — | Sí |
| Promociones | COMPLETO | COMPLETO | COMPLETO | tests `Promotions` | — | Sí |
| Métodos de pago | COMPLETO | COMPLETO | COMPLETO | tests `PaymentMethods` | — | Sí |

## Notificaciones y reportes

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Notificaciones internas | COMPLETO | COMPLETO | COMPLETO | tests `Notifications` | — | Sí |
| Push notifications | **FUERA DEL MVP** | — | — | — | No existe tabla `device_tokens` ni integración FCM/APNs. `notification_preferences.push_enabled` existe como flag pero no dispara nada. Explícitamente fuera de esta tanda. | — |
| Reportes | COMPLETO | COMPLETO | COMPLETO | tests `PersonalReports`, `GroupReports`, `AdminReports`, `ReportExports` | — | Sí |

## Administración y soporte

| Módulo | Backend | Web | Mobile | Tests | Limitaciones | Pendiente manual |
|---|---|---|---|---|---|---|
| Scraping | COMPLETO | COMPLETO (admin) | N/A (admin-only) | tests `Scraping`, `ScrapingAlerts`, `ScrapingCandidates`, `RecipeScraping` | — | Sí |
| Auditoría | COMPLETO | COMPLETO (admin) | N/A | Reutilizado en tests de cada módulo (`AuditLog::create`) | — | Sí |
| Mobile offline básico | COMPLETO CON LIMITACIÓN | N/A | COMPLETO CON LIMITACIÓN | `secureStorage.test.ts`, `errorAndSettingsStage.test.ts` | `networkStatus.ts` + `offlineCache.ts` existen (detección de conectividad + banner + cache de lectura); no hay cola de escritura offline ni sincronización diferida | Sí |
| APK / build | COMPLETO CON LIMITACIÓN | N/A | COMPLETO CON LIMITACIÓN | `expo-doctor` (20/20), `expo export --platform android` OK | `eas.json` tiene perfil `development`/`preview` con `buildType: apk`; no se generó ni firmó un APK real en esta sesión (requiere cuenta EAS/credenciales) | Sí |

---

## Resumen del criterio de cierre (ver también respuesta final del turno)

Los 4 puntos originales de esta tanda (recuperación de contraseña, impacto de compra en presupuesto, persistencia de precios estimados, uso real de equivalencias) están **COMPLETO** o **COMPLETO CON LIMITACIÓN** según el detalle de cada fila. Ninguno fue validado en un dispositivo físico — ver `docs/mobile-release-checklist.md` para el flujo de QA pendiente.

## Mejoras post-MVP

No implementadas en esta tanda, no bloquean el cierre funcional automatizado:

- Google Login (mobile UI) — backend ya existe.
- Push notifications (tabla `device_tokens`, integración FCM/APNs, `entity_type`/`entity_id` en `notifications` para deep-link directo a un recurso).
- `logo_url` en `supermarket_chains`.
- Tabla dedicada `ingredient_product_mappings` — hoy la resolución producto↔ingrediente usa `Product.ingredient_id` como enlace directo y `ingredient_equivalences` como fallback ordenado; una tabla dedicada permitiría marcar un producto "preferido" explícito sin depender de que sea el único con `ingredient_id` asignado.
- Alta colaborativa de productos desconocidos (cuando un código de barras no matchea ningún producto).
- Edición/anulación de compras con reversión de stock (hoy `cancel()` solo cambia `status`+soft-delete; no revierte los `StockItem`/`StockMovement` generados).
- Reconciliar `budget_movements` (ledger de ajustes manuales) con el cálculo dinámico de `spent_amount`, o documentar definitivamente que son mecanismos independientes.
- Sincronización offline completa (cola de escrituras pendientes).
- Build iOS y publicación en Play Store.
- Toggle mobile para aceptar/excluir una sustitución de ingrediente antes de generar la lista (hoy es automática y solo informativa).
