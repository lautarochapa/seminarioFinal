<?php

namespace Tests\Feature\Api\V1\E2E;

use App\MealPlanItem;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recorrido principal de usuario — planificacion, lista y comparacion.
 *
 * Verifica el tramo:
 *   seed demo -> perfil -> presupuesto -> generar plan -> generar lista
 *   -> comprobar que el stock existente fue restado
 *   -> comparar supermercados (>=2 resultados) -> optimizar (ganador + ahorro).
 *
 * Deterministico: se apoya exclusivamente en DemoScenarioSeeder.
 */
class CoreUserFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\User */
    private $laura;
    private $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('seeds/DemoScenarioSeeder.php');
        $this->seed(\DemoScenarioSeeder::class);

        $this->laura = User::where('email', \DemoScenarioSeeder::USER_EMAIL)->firstOrFail();
        $this->groupId = (int) DB::table('family_groups')
            ->where('name', \DemoScenarioSeeder::GROUP_NAME)
            ->value('id');
    }

    public function test_generates_plan_list_and_compares_supermarkets(): void
    {
        $g = $this->groupId;

        // ── 1. Perfil: se guarda peso, objetivo y comidas por dia ──────────────
        $this->actingAs($this->laura)
            ->patchJson('/api/v1/users/me/profile', [
                'height_cm'        => 165,
                'current_weight_kg' => 72,
                'target_weight_kg'  => 66,
                'meals_per_day'     => 4,
                'activity_level'    => 'light',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('user_profiles', [
            'user_id'          => $this->laura->id,
            'target_weight_kg' => 66,
            'meals_per_day'    => 4,
        ]);

        // ── 2. Presupuesto vigente ────────────────────────────────────────────
        $budget = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/budgets/current")
            ->assertStatus(200)
            ->json('data');
        $this->assertEqualsWithDelta(90000, (float) $budget['total_amount'], 0.01);

        // ── 3. Generar planificacion semanal ─────────────────────────────────
        $start = now()->toDateString();
        $end   = now()->addDays(6)->toDateString();

        $genResponse = $this->actingAs($this->laura)->postJson(
            "/api/v1/family-groups/{$g}/meal-plans/generate",
            ['period_type' => 'weekly', 'start_date' => $start, 'end_date' => $end]
        )->assertStatus(201);

        $planId = (int) $genResponse->json('data.id');
        $this->assertGreaterThan(0, $planId);

        // 4 tipos de comida x 7 dias = 28 items, con las 8 recetas del dataset.
        $this->assertSame(28, MealPlanItem::where('meal_plan_id', $planId)->count());
        $distinctRecipes = MealPlanItem::where('meal_plan_id', $planId)
            ->whereNotNull('recipe_id')->distinct()->count('recipe_id');
        $this->assertSame(8, $distinctRecipes);

        // ── 4. Aprobar el plan ───────────────────────────────────────────────
        $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/meal-plans/{$planId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // ── 5. Preview de lista: el stock existente se resta ──────────────────
        $preview = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/meal-plans/{$planId}/shopping-list-preview")
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($preview, 'El preview deberia tener faltantes.');

        $byName = [];
        foreach ($preview as $row) {
            $byName[strtolower($row['ingredient']['name'])] = $row;
        }

        // Arroz: hay 150 g en stock -> el faltante es (requerido - 150), y es menor al requerido.
        $this->assertArrayHasKey('arroz blanco', $byName);
        $arroz = $byName['arroz blanco'];
        $this->assertGreaterThan(0, $arroz['missing_quantity']);
        $this->assertLessThan($arroz['required_quantity'], $arroz['missing_quantity']);
        $this->assertEqualsWithDelta(
            round($arroz['required_quantity'] - 150, 4),
            $arroz['missing_quantity'],
            0.01,
            'El faltante de arroz debe descontar los 150 g en stock.'
        );

        // Pechuga de pollo: 0 en stock -> el faltante es igual al requerido.
        $this->assertArrayHasKey('pechuga de pollo', $byName);
        $pollo = $byName['pechuga de pollo'];
        $this->assertEqualsWithDelta($pollo['required_quantity'], $pollo['missing_quantity'], 0.01);

        // ── 6. Generar la lista real desde el plan ───────────────────────────
        $genList = $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/meal-plans/{$planId}/generate-shopping-list")
            ->assertStatus(201)
            ->json('data');

        $listId = (int) $genList['id'];
        $items = ShoppingListItem::where('shopping_list_id', $listId)->get();

        $this->assertGreaterThan(0, $items->count());
        foreach ($items as $item) {
            $this->assertNotNull($item->product_id, "Item {$item->id} sin product_id: no seria comparable.");
            $this->assertNotNull($item->unit_id, "Item {$item->id} sin unit_id.");
            $this->assertGreaterThanOrEqual(1, (float) $item->quantity);
        }
        $this->assertGreaterThan(
            0,
            $items->whereNotNull('estimated_price')->count(),
            'Al menos un item deberia tener precio estimado.'
        );

        // ── 7. Comparar supermercados: al menos 2 resultados utiles ──────────
        $compare = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/shopping-lists/{$listId}/compare-supermarkets")
            ->assertStatus(200)
            ->json('data');

        $branches = $compare['branches'];
        $this->assertGreaterThanOrEqual(3, count($branches));

        $totals = [];
        foreach ($branches as $branch) {
            $this->assertSame(0, $branch['missing_count'], "Sucursal {$branch['branch']['name']} con faltantes.");
            $this->assertEmpty($branch['missing_items']);
            $this->assertGreaterThan(0, $branch['total']);
            $this->assertSame('ARS', $branch['currency']);
            $this->assertSame($items->count(), $branch['found_count']);
            $totals[$branch['branch']['name']] = $branch['total'];
        }
        $this->assertGreaterThan(1, count(array_unique($totals)), 'Los totales por sucursal deben diferir.');

        // ── 8. Optimizar: ganador + combinacion + ahorro ────────────────────
        $optimize = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/shopping-lists/{$listId}/optimize")
            ->assertStatus(200)
            ->json('data');

        $this->assertNotNull($optimize['cheapest_complete'], 'Deberia existir un supermercado ganador.');
        $this->assertGreaterThan(0, $optimize['cheapest_complete']['total']);
        $this->assertNotNull($optimize['combined']['total']);
        $this->assertLessThanOrEqual(
            $optimize['cheapest_complete']['total'],
            $optimize['combined']['total'],
            'La compra combinada nunca puede costar mas que el mejor supermercado unico.'
        );
        $this->assertGreaterThanOrEqual(0, $optimize['estimated_savings']);

        // El ganador debe ser uno de los totales comparados.
        $this->assertContains($optimize['cheapest_complete']['total'], array_values($totals));

        // ── 9. (bonus HP17) "Que cocinar hoy": Panqueques cocinable con el stock ─
        $available = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/recipes/available")
            ->assertStatus(200)
            ->json('data');

        $names = array_map(function ($r) {
            return strtolower($r['name'] ?? '');
        }, $available);
        $this->assertContains('panqueques caseros', $names, 'Panqueques deberia poder cocinarse con el stock inicial.');

        if (getenv('E2E_DUMP')) {
            fwrite(STDERR, "\n=== LISTA GENERADA (list #{$listId}) ===\n");
            foreach ($items as $it) {
                $pname = DB::table('products')->where('id', $it->product_id)->value('name');
                fwrite(STDERR, sprintf("  %-40s x%s  est.$%s\n", $pname, rtrim(rtrim((string) $it->quantity, '0'), '.'), $it->estimated_price ?? '-'));
            }
            fwrite(STDERR, "\n=== COMPARE ===\n");
            foreach ($branches as $b) {
                fwrite(STDERR, sprintf("  %-28s total \$%s  (%d/%d items)\n", $b['branch']['name'], number_format($b['total'], 2), $b['found_count'], $b['found_count'] + $b['missing_count']));
            }
            fwrite(STDERR, "\n=== OPTIMIZE ===\n");
            fwrite(STDERR, "  ganador: {$optimize['cheapest_complete']['branch']['name']} \$" . number_format($optimize['cheapest_complete']['total'], 2) . "\n");
            fwrite(STDERR, "  combinada: \$" . number_format($optimize['combined']['total'], 2) . "\n");
            fwrite(STDERR, "  ahorro combinada vs ganador: \$" . number_format($optimize['estimated_savings'], 2) . "\n");
            fwrite(STDERR, "  ahorro ganador vs mas caro: \$" . number_format(max($totals) - min($totals), 2) . "\n");
        }
    }
}
