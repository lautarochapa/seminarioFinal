<?php

namespace Tests\Feature\Api\V1\E2E;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un usuario completamente nuevo debe poder recorrer el flujo principal
 * usando SOLO el catalogo global y las APIs normales de la aplicacion:
 *
 *   registro -> grupo -> perfil -> presupuesto -> plan -> lista -> comparador
 *   -> compra -> stock -> alertas -> "que cocinar" -> cocinar -> stock actualizado
 *
 * No usa DemoScenarioSeeder ni a Laura Demo: solo GlobalCatalogSeeder (datos de
 * plataforma). Incluye una verificacion de aislamiento entre grupos.
 */
class NewUserFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once database_path('seeds/GlobalCatalogSeeder.php');
        $this->seed(\GlobalCatalogSeeder::class);

        // El escenario demo NO debe existir en esta prueba.
        $this->assertDatabaseMissing('users', ['email' => 'laura.demo@cccontrol.test']);
        $this->assertDatabaseMissing('family_groups', ['name' => 'Familia Demo']);
    }

    // ─────────────────────────────────────────────────────────── helpers

    /** Registra un usuario via API (flujo real) y devuelve su modelo + token emitido. */
    private function register(string $email): array
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Persona',
            'lastname'              => 'Nueva',
            'email'                 => $email,
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertStatus(201);

        $id    = (int) $res->json('data.id');
        $token = (string) $res->json('token.access_token');
        $this->assertNotEmpty($token, 'El registro debe emitir un token de API.');

        return ['id' => $id, 'user' => User::findOrFail($id), 'token' => $token];
    }

    private function actingApi(array $who): self
    {
        return $this->actingAs($who['user']);
    }

    private function unitId(string $code): int
    {
        return (int) DB::table('unit_measures')->where('code', $code)->value('id');
    }

    private function productIdByBarcode(string $barcode): int
    {
        return (int) DB::table('product_barcodes')->where('barcode', $barcode)->value('product_id');
    }

    private function recipeId(string $normalized): int
    {
        return (int) DB::table('recipes')->where('normalized_name', $normalized)->value('id');
    }

    private function createGroup(array $who, string $name): int
    {
        return (int) $this->actingApi($who)
            ->postJson('/api/v1/family-groups', ['name' => $name])
            ->assertStatus(201)->json('data.id');
    }

    private function stockQty(int $groupId, string $barcode): float
    {
        $pid = $this->productIdByBarcode($barcode);
        return (float) DB::table('stock_items')
            ->where('family_group_id', $groupId)->where('product_id', $pid)->where('status', 'active')
            ->sum('quantity');
    }

    /** Un no-miembro debe recibir un error de cliente (403 recurso ajeno / 404 no visible). */
    private function assertRejected(\Illuminate\Testing\TestResponse $res, string $ctx = ''): void
    {
        $status = $res->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $status, "Deberia rechazar {$ctx} (fue {$status}).");
        $this->assertLessThan(500, $status, "No deberia ser error de servidor en {$ctx} (fue {$status}).");
        $this->assertContains($status, [401, 403, 404, 409, 422], "Estado inesperado {$status} en {$ctx}.");
    }

    private function generateAndApprovePlan(array $who, int $groupId): int
    {
        $planId = (int) $this->actingApi($who)->postJson("/api/v1/family-groups/{$groupId}/meal-plans/generate", [
            'period_type' => 'weekly',
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(6)->toDateString(),
        ])->assertStatus(201)->json('data.id');

        $this->actingApi($who)->postJson("/api/v1/family-groups/{$groupId}/meal-plans/{$planId}/approve")->assertStatus(200);

        return $planId;
    }

    // ─────────────────────────────────────────────────────────── recorrido completo

    public function test_new_user_completes_full_flow_from_registration(): void
    {
        // 1. Registro. El token emitido autentica de verdad (Bearer).
        $a = $this->register('nueva.persona.' . uniqid() . '@example.test');
        $this->withToken($a['token'])->getJson('/api/v1/auth/me')
            ->assertStatus(200)->assertJsonPath('data.id', $a['id']);
        $this->flushHeaders();

        // 2. Sin grupo tras el registro
        $this->actingApi($a)->getJson('/api/v1/family-groups')->assertStatus(200)->assertJsonCount(0, 'data');

        // 3. Crea su grupo y queda como owner activo
        $gid = $this->createGroup($a, 'Grupo Nuevo');
        $this->actingApi($a)->getJson('/api/v1/family-groups')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/members")
            ->assertStatus(200)->assertJsonPath('data.0.role', 'owner');
        $this->assertDatabaseHas('family_group_members', [
            'family_group_id' => $gid, 'user_id' => $a['id'], 'role_in_group' => 'owner', 'status' => 'active',
        ]);

        // 4. Perfil: peso, objetivo, comidas por dia, restriccion
        $objectiveId = (int) $this->actingApi($a)->getJson('/api/v1/catalog/objectives')->assertStatus(200)->json('data.0.id');
        $this->actingApi($a)->patchJson('/api/v1/users/me/profile', [
            'height_cm'         => 170,
            'current_weight_kg' => 80,
            'target_weight_kg'  => 74,
            'meals_per_day'     => 4,
            'activity_level'    => 'moderate',
            'objective_ids'     => [$objectiveId],
        ])->assertStatus(200);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $a['id'], 'target_weight_kg' => 74, 'meals_per_day' => 4]);
        $this->assertDatabaseHas('user_objectives', ['user_id' => $a['id'], 'objective_id' => $objectiveId]);

        $restrictionId = (int) $this->actingApi($a)->getJson('/api/v1/catalog/dietary-restrictions')->assertStatus(200)->json('data.0.id');
        $this->actingApi($a)->postJson('/api/v1/users/me/dietary-restrictions', ['dietary_restriction_id' => $restrictionId])
            ->assertSuccessful();
        $this->assertDatabaseHas('user_dietary_restrictions', ['user_id' => $a['id'], 'dietary_restriction_id' => $restrictionId]);

        // 5. Presupuesto nuevo
        $now = now();
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/budgets", [
            'year' => (int) $now->year, 'month' => (int) $now->month, 'total_amount' => 120000, 'currency' => 'ARS',
        ])->assertStatus(201);
        $budget = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/budgets/current")->assertStatus(200)->json('data');
        $this->assertEqualsWithDelta(120000, (float) $budget['total_amount'], 0.01);
        $budgetId = (int) $budget['id'];
        $summary0 = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/budgets/{$budgetId}/summary")->assertStatus(200)->json('data');
        $this->assertEqualsWithDelta(0, (float) $summary0['spent_amount'], 0.01);
        $this->assertEqualsWithDelta(120000, (float) $summary0['available_amount'], 0.01);

        // 6. Estado inicial SIN stock: plan + lista funcionan y la lista trae todo
        $planId = $this->generateAndApprovePlan($a, $gid);

        $preview = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/meal-plans/{$planId}/shopping-list-preview")
            ->assertStatus(200)->json('data');
        $this->assertNotEmpty($preview);
        $arrozMissingNoStock = null;
        foreach ($preview as $row) {
            $this->assertEqualsWithDelta($row['required_quantity'], $row['missing_quantity'], 0.01,
                'Sin stock, el faltante debe ser igual al requerido.');
            if (strtolower($row['ingredient']['name']) === 'arroz blanco') {
                $arrozMissingNoStock = (float) $row['missing_quantity'];
            }
        }
        $this->assertNotNull($arrozMissingNoStock);

        $listId = (int) $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/meal-plans/{$planId}/generate-shopping-list")
            ->assertStatus(201)->json('data.id');
        $items = DB::table('shopping_list_items')->where('shopping_list_id', $listId)->get();
        $this->assertGreaterThan(0, $items->count());
        foreach ($items as $it) {
            $this->assertNotNull($it->product_id, "Item {$it->id} sin product_id.");
            $this->assertNotNull($it->unit_id);
            $this->assertGreaterThanOrEqual(1, (float) $it->quantity);
        }

        // 7. Comparar supermercados de SU lista (precios del catalogo global)
        $branches = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/shopping-lists/{$listId}/compare-supermarkets")
            ->assertStatus(200)->json('data.branches');
        $this->assertGreaterThanOrEqual(3, count($branches));
        $totals = [];
        foreach ($branches as $b) {
            $this->assertSame(0, $b['missing_count']);
            $this->assertGreaterThan(0, $b['total']);
            $totals[$b['branch']['name']] = $b['total'];
        }
        $this->assertGreaterThan(1, count(array_unique($totals)));

        $optimize = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/shopping-lists/{$listId}/optimize")
            ->assertStatus(200)->json('data');
        $this->assertNotNull($optimize['cheapest_complete']);
        $this->assertLessThanOrEqual($optimize['cheapest_complete']['total'], $optimize['combined']['total']);
        $this->assertGreaterThanOrEqual(0, $optimize['estimated_savings']);

        // 8. Cargar stock como usuario y verificar el descuento en una nueva lista
        $locId = (int) $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock-locations", ['name' => 'Despensa'])
            ->assertStatus(201)->json('data.id');

        // 8a. Producto conocido -> scan -> stock
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock/scan", [
            'barcode' => '7791111000063', // arroz, paquete = kg
            'stock_location_id' => $locId,
            'quantity' => 1,
            'unit_id' => $this->unitId('kg'),
        ])->assertSuccessful();
        $this->assertGreaterThan(0, $this->stockQty($gid, '7791111000063'));

        // 8b. Producto desconocido -> carga manual -> pending_review -> stock (solo del grupo)
        $unknownBarcode = '9990000000123';
        $manual = $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock/manual-product", [
            'product' => ['name' => 'Yerba para mate', 'barcode' => $unknownBarcode, 'unit_id' => $this->unitId('g')],
            'stock'   => ['quantity' => 500, 'unit_id' => $this->unitId('g'), 'stock_location_id' => $locId],
        ])->assertStatus(201)->json();
        $manualProductId = (int) $manual['data']['product']['id'];
        $this->assertSame('pending_review', $manual['data']['review_status']);
        $this->assertDatabaseHas('products', ['id' => $manualProductId, 'status' => 'pending_review', 'origin' => 'user_created', 'family_group_id' => $gid]);
        $this->assertDatabaseHas('product_requests', ['product_id' => $manualProductId, 'family_group_id' => $gid, 'status' => 'pending']);
        // Reintento con el mismo barcode -> no duplica
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock/manual-product", [
            'product' => ['name' => 'Yerba para mate', 'barcode' => $unknownBarcode, 'unit_id' => $this->unitId('g')],
            'stock'   => ['quantity' => 250, 'unit_id' => $this->unitId('g'), 'stock_location_id' => $locId],
        ])->assertStatus(200);
        $this->assertSame(1, DB::table('products')->where('family_group_id', $gid)->where('status', 'pending_review')->count());

        // 8c. Nuevo plan/lista -> el arroz cargado se descuenta
        $planId2 = $this->generateAndApprovePlan($a, $gid);
        $preview2 = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/meal-plans/{$planId2}/shopping-list-preview")
            ->assertStatus(200)->json('data');
        $arroz = collect($preview2)->first(fn ($r) => strtolower($r['ingredient']['name']) === 'arroz blanco');
        $this->assertNotNull($arroz, 'El arroz sigue haciendo falta pero en menor cantidad.');
        // El plan es deterministico: el requerido de arroz es el mismo que sin stock,
        // pero ahora se descuenta 1 kg (1000 g) del stock cargado.
        $this->assertLessThan($arrozMissingNoStock, (float) $arroz['missing_quantity'],
            'Con arroz en stock, el faltante debe ser menor que sin stock.');
        $this->assertGreaterThanOrEqual(0.0, (float) $arroz['missing_quantity']);

        // 9. Alertas: bajo stock (regla creada por el usuario) + proximo vencimiento
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock-minimum-rules", [
            'product_id' => $this->productIdByBarcode('7791111000063'),
            'minimum_quantity' => 5,
            'unit_id' => $this->unitId('kg'),
        ])->assertStatus(201);
        $low = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/stock/low-stock")->assertStatus(200)->json('data');
        $this->assertNotEmpty($low);

        // Stock proximo a vencer: leche a +2 dias
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock", [
            'product_id' => $this->productIdByBarcode('7791111000049'),
            'stock_location_id' => $locId,
            'quantity' => 1,
            'unit_id' => $this->unitId('l'),
            'expiration_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(201);
        $expiring = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/stock/expiring")->assertStatus(200)->json('data');
        $this->assertNotEmpty($expiring);

        // 10. "Que cocinar hoy" en base a SU stock: cargar lo necesario para una receta
        foreach ([['7791111000094', 'kg'], ['7791111000087', 'kg'], ['7791111000056', 'l']] as [$bc, $u]) {
            $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/stock/scan", [
                'barcode' => $bc, 'stock_location_id' => $locId, 'quantity' => 1, 'unit_id' => $this->unitId($u),
            ])->assertSuccessful();
        }

        $available = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/recipes/available")->assertStatus(200)->json('data');
        $availableNames = array_map(fn ($r) => strtolower($r['name'] ?? ''), $available);
        $this->assertContains('ensalada tibia de arroz', $availableNames);

        $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/recipes/almost-available")->assertStatus(200);
        $byExpiring = $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/recipes/by-expiring-stock")->assertStatus(200)->json('data');
        $this->assertContains('panqueques caseros', array_map(fn ($r) => strtolower($r['name'] ?? ''), $byExpiring));

        // 11. Cocinar y descontar stock
        $ensaladaId = $this->recipeId('ensalada tibia de arroz');
        $arrozBefore  = $this->stockQty($gid, '7791111000063');
        $tomateBefore = $this->stockQty($gid, '7791111000094');
        $this->actingApi($a)->postJson("/api/v1/recipes/{$ensaladaId}/cook", [
            'servings' => 2, 'family_group_id' => $gid, 'deduct_stock' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('recipe_cook_logs', ['recipe_id' => $ensaladaId, 'family_group_id' => $gid, 'user_id' => $a['id']]);
        $this->assertGreaterThan(0, DB::table('stock_movements')
            ->where('family_group_id', $gid)->where('related_recipe_id', $ensaladaId)
            ->where('movement_type', 'consumption')->where('reason', 'recipe_cook')->count());

        // La receta pide 200 g de arroz y 200 g de tomate para 2 porciones; el stock
        // esta cargado en kg, asi que se descuentan 0,2 kg de cada uno.
        $this->assertEqualsWithDelta($arrozBefore - 0.2, $this->stockQty($gid, '7791111000063'), 0.001);
        $this->assertEqualsWithDelta($tomateBefore - 0.2, $this->stockQty($gid, '7791111000094'), 0.001);

        // 12. Historial
        $this->actingApi($a)->getJson('/api/v1/users/me/cooked-recipes')->assertStatus(200)->assertJsonCount(1, 'data');
    }

    // ─────────────────────────────────────────────────────────── aislamiento

    public function test_group_data_is_isolated_between_users(): void
    {
        $a = $this->register('owner.a.' . uniqid() . '@example.test');
        $b = $this->register('owner.b.' . uniqid() . '@example.test');

        $groupA = $this->createGroup($a, 'Grupo A');
        $this->createGroup($b, 'Grupo B');

        $locA = (int) $this->actingApi($a)->postJson("/api/v1/family-groups/{$groupA}/stock-locations", ['name' => 'Alacena A'])
            ->assertStatus(201)->json('data.id');
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$groupA}/stock/scan", [
            'barcode' => '7791111000070', 'stock_location_id' => $locA, 'quantity' => 2, 'unit_id' => $this->unitId('kg'),
        ])->assertSuccessful();
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$groupA}/budgets", [
            'year' => (int) now()->year, 'month' => (int) now()->month, 'total_amount' => 50000, 'currency' => 'ARS',
        ])->assertStatus(201);
        $listA = (int) $this->actingApi($a)->postJson("/api/v1/family-groups/{$groupA}/shopping-lists", [
            'source_type' => 'manual',
        ])->assertStatus(201)->json('data.id');

        $polloBefore = $this->stockQty($groupA, '7791111000070');
        $this->assertEqualsWithDelta(2, $polloBefore, 0.01);

        // B no puede ver nada de A
        $blocked = [
            ['GET', "/api/v1/family-groups/{$groupA}"],
            ['GET', "/api/v1/family-groups/{$groupA}/stock"],
            ['GET', "/api/v1/family-groups/{$groupA}/budgets/current"],
            ['GET', "/api/v1/family-groups/{$groupA}/purchases"],
            ['GET', "/api/v1/family-groups/{$groupA}/shopping-lists/{$listA}"],
        ];
        foreach ($blocked as [$method, $url]) {
            $this->assertRejected($this->actingApi($b)->json($method, $url), "{$method} {$url}");
        }

        // B no puede escribir stock de A
        $this->assertRejected($this->actingApi($b)->postJson("/api/v1/family-groups/{$groupA}/stock/scan", [
            'barcode' => '7791111000070', 'stock_location_id' => $locA, 'quantity' => 5, 'unit_id' => $this->unitId('kg'),
        ]));

        // B no puede cocinar descontando stock de A
        $recipeId = $this->recipeId('arroz con pollo');
        $this->assertRejected($this->actingApi($b)->postJson("/api/v1/recipes/{$recipeId}/cook", [
            'servings' => 2, 'family_group_id' => $groupA, 'deduct_stock' => true,
        ]));

        // El stock de A quedo intacto y no hay cocciones en A
        $this->assertEqualsWithDelta($polloBefore, $this->stockQty($groupA, '7791111000070'), 0.01);
        $this->assertSame(0, DB::table('recipe_cook_logs')->where('family_group_id', $groupA)->count());
    }

    // ─────────────────────────────── meal plan preferences (respetar presupuesto)

    /** Nombres de receta usados en el plan (via meal_plan_items). */
    private function planRecipeNames(int $planId): array
    {
        return DB::table('meal_plan_items as i')
            ->join('recipes as r', 'r.id', '=', 'i.recipe_id')
            ->where('i.meal_plan_id', $planId)
            ->distinct()->pluck('r.normalized_name')->map(fn ($n) => strtolower($n))->all();
    }

    public function test_new_user_configures_meal_plan_preferences_to_respect_budget(): void
    {
        $a = $this->register('planner.' . uniqid() . '@example.test');
        $gid = $this->createGroup($a, 'Grupo Planner');

        // Presupuesto chico: solo alcanzan las recetas con costo <= 3000
        // (excluye "arroz con pollo" $5200 y "pollo al horno con papas" $5600).
        $this->actingApi($a)->postJson("/api/v1/family-groups/{$gid}/budgets", [
            'year' => (int) now()->year, 'month' => (int) now()->month, 'total_amount' => 3000, 'currency' => 'ARS',
        ])->assertStatus(201);

        // GET de preferencias antes de configurarlas: devuelve los defaults (sin crear fila).
        $this->actingApi($a)->getJson("/api/v1/family-groups/{$gid}/meal-plan-preferences")
            ->assertStatus(200)
            ->assertJsonPath('data.family_group_id', $gid)
            ->assertJsonPath('data.respect_budget', true);
        $this->assertDatabaseMissing('meal_plan_preferences', ['family_group_id' => $gid]);

        // 1) Sin preferencia guardada -> el generador NO filtra por presupuesto:
        //    las recetas caras aparecen igual.
        $planNoPref = $this->generateAndApprovePlan($a, $gid);
        $this->assertContains('arroz con pollo', $this->planRecipeNames($planNoPref));

        // 2) El usuario configura la preferencia por API.
        $this->actingApi($a)->patchJson("/api/v1/family-groups/{$gid}/meal-plan-preferences", [
            'respect_budget' => true,
            'avoid_repetition' => true,
        ])->assertStatus(200)->assertJsonPath('data.respect_budget', true);
        $this->assertDatabaseHas('meal_plan_preferences', [
            'family_group_id' => $gid, 'user_id' => null, 'respect_budget' => true,
        ]);

        // 3) Nuevo plan -> ahora SÍ respeta el presupuesto: las recetas caras quedan afuera.
        $planWithPref = $this->generateAndApprovePlan($a, $gid);
        $names = $this->planRecipeNames($planWithPref);
        $this->assertNotContains('arroz con pollo', $names, 'La receta de $5200 supera el presupuesto de $3000.');
        $this->assertNotContains('pollo al horno con papas', $names, 'La receta de $5600 supera el presupuesto de $3000.');
        $this->assertContains('panqueques', $names);
        foreach ($names as $n) {
            $this->assertNotContains($n, ['arroz con pollo', 'pollo al horno con papas']);
        }

        // Se puede volver a desactivar.
        $this->actingApi($a)->patchJson("/api/v1/family-groups/{$gid}/meal-plan-preferences", [
            'respect_budget' => false,
        ])->assertStatus(200)->assertJsonPath('data.respect_budget', false);
    }

    public function test_meal_plan_preferences_are_isolated_and_require_membership(): void
    {
        $a = $this->register('pref.a.' . uniqid() . '@example.test');
        $b = $this->register('pref.b.' . uniqid() . '@example.test');
        $groupA = $this->createGroup($a, 'Grupo Pref A');
        $this->createGroup($b, 'Grupo Pref B');

        // B (no miembro de A) no puede leer ni modificar las preferencias de A.
        $this->assertRejected($this->actingApi($b)->getJson("/api/v1/family-groups/{$groupA}/meal-plan-preferences"));
        $this->assertRejected($this->actingApi($b)->patchJson("/api/v1/family-groups/{$groupA}/meal-plan-preferences", [
            'respect_budget' => false,
        ]));
        $this->assertDatabaseMissing('meal_plan_preferences', ['family_group_id' => $groupA]);

        // El owner de A sí puede.
        $this->actingApi($a)->patchJson("/api/v1/family-groups/{$groupA}/meal-plan-preferences", [
            'respect_budget' => false,
        ])->assertStatus(200);
        $this->assertDatabaseHas('meal_plan_preferences', ['family_group_id' => $groupA, 'respect_budget' => false]);
    }
}
