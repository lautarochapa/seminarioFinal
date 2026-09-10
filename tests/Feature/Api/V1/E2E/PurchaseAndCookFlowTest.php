<?php

namespace Tests\Feature\Api\V1\E2E;

use App\Purchase;
use App\PurchaseItem;
use App\StockMovement;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recorrido principal de usuario — compra, stock y uso cotidiano.
 *
 * Verifica el tramo:
 *   lista existente -> elegir supermercado -> sesion de compra -> escanear
 *   -> finalizar compra -> Purchase + stock_items + stock_movements + presupuesto
 *   -> bajo stock -> proximos vencimientos -> "que cocinar hoy" (incluye por vencer)
 *   -> cocinar receta -> descuento de stock -> historial de coccion.
 *
 * Deterministico: se apoya exclusivamente en DemoScenarioSeeder.
 */
class PurchaseAndCookFlowTest extends TestCase
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

    private function productId(string $normalizedNameLike): int
    {
        return (int) DB::table('products')->where('normalized_name', 'like', "%{$normalizedNameLike}%")->value('id');
    }

    private function stockQtyFor(string $ingredientNormalized, string $unitCode): float
    {
        return (float) DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->join('ingredients as i', 'i.id', '=', 'p.ingredient_id')
            ->join('unit_measures as u', 'u.id', '=', 'si.unit_id')
            ->where('i.normalized_name', $ingredientNormalized)
            ->where('u.code', $unitCode)
            ->where('si.status', 'active')
            ->sum('si.quantity');
    }

    public function test_purchase_session_updates_stock_budget_and_cooking_flow(): void
    {
        $g = $this->groupId;

        // ── Precondicion: plan aprobado + lista generada ─────────────────────
        $planId = (int) $this->actingAs($this->laura)->postJson(
            "/api/v1/family-groups/{$g}/meal-plans/generate",
            ['period_type' => 'weekly', 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(6)->toDateString()]
        )->assertStatus(201)->json('data.id');

        $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/meal-plans/{$planId}/approve")
            ->assertStatus(200);

        $listId = (int) $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/meal-plans/{$planId}/generate-shopping-list")
            ->assertStatus(201)->json('data.id');

        $this->assertDatabaseHas('shopping_lists', ['id' => $listId, 'status' => 'active']);

        // ── 1. Elegir supermercado + iniciar sesion de compra ───────────────
        $branchId = (int) DB::table('supermarket_branches')->where('name', 'Carrefour Bariloche Centro')->value('id');
        $alacenaId = (int) DB::table('stock_locations')->where('family_group_id', $g)->where('name', 'Alacena')->value('id');

        $sessionId = (int) $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/shopping-lists/{$listId}/start-session")
            ->assertStatus(201)->json('data.id');

        $this->assertDatabaseHas('shopping_lists', ['id' => $listId, 'status' => 'in_progress']);

        $this->actingAs($this->laura)
            ->patchJson("/api/v1/family-groups/{$g}/shopping-sessions/{$sessionId}", ['supermarket_branch_id' => $branchId])
            ->assertStatus(200);

        // ── 2. Escanear productos de la lista (barcodes conocidos + precio real) ─
        // Barcode -> precio Carrefour por paquete (matriz determinista del seeder).
        $scans = [
            '7791111000070' => 4200.0, // pollo
            '7791111000117' => 950.0,  // fideos
            '7791111000124' => 1500.0, // queso
            '7791111000063' => 1800.0, // arroz
        ];

        $items = DB::table('shopping_list_items')->where('shopping_list_id', $listId)->get()->keyBy('product_id');
        $expectedActualTotal = 0.0;

        foreach ($scans as $barcode => $unitPrice) {
            $barcode = (string) $barcode;
            $pid = (int) DB::table('product_barcodes')->where('barcode', $barcode)->value('product_id');
            $this->assertArrayHasKey($pid, $items->toArray(), "El producto {$barcode} deberia estar en la lista.");
            $qty = (float) $items[$pid]->quantity;

            $this->actingAs($this->laura)->postJson(
                "/api/v1/family-groups/{$g}/shopping-sessions/{$sessionId}/scan",
                ['barcode' => $barcode, 'quantity' => $qty, 'price' => $unitPrice]
            )->assertStatus(201);

            $expectedActualTotal += $unitPrice * $qty;

            $this->assertDatabaseHas('shopping_list_items', ['id' => $items[$pid]->id, 'status' => 'purchased']);
        }

        // Escanear dos veces el mismo producto -> 409 (no duplica)
        $this->actingAs($this->laura)->postJson(
            "/api/v1/family-groups/{$g}/shopping-sessions/{$sessionId}/scan",
            ['barcode' => '7791111000070', 'quantity' => 1]
        )->assertStatus(409);

        // ── 3. Finalizar compra ────────────────────────────────────────────
        $finish = $this->actingAs($this->laura)->postJson(
            "/api/v1/family-groups/{$g}/shopping-sessions/{$sessionId}/finish",
            ['stock_location_id' => $alacenaId]
        )->assertStatus(200)->json();

        $purchaseId = (int) $finish['summary']['purchase_id'];
        $this->assertGreaterThan(0, $purchaseId);

        // Purchase con total real y estado consistente
        $purchase = Purchase::findOrFail($purchaseId);
        $this->assertSame('confirmed', $purchase->status);
        $this->assertSame($g, (int) $purchase->family_group_id);
        $this->assertSame($branchId, (int) $purchase->supermarket_branch_id);
        $this->assertEqualsWithDelta($expectedActualTotal, (float) $purchase->actual_total, 0.01);

        // Un PurchaseItem y un movimiento de entrada por cada escaneo, sin duplicados
        $this->assertSame(count($scans), PurchaseItem::where('purchase_id', $purchaseId)->count());
        $this->assertSame(
            count($scans),
            StockMovement::where('related_purchase_id', $purchaseId)->where('movement_type', 'entry')->count()
        );
        $this->assertSame(
            count($scans),
            PurchaseItem::where('purchase_id', $purchaseId)->whereNotNull('created_stock_item_id')->count()
        );

        // El pollo no tenia stock: ahora existe exactamente 1 stock_item para ese producto
        $polloProductId = (int) DB::table('product_barcodes')->where('barcode', '7791111000070')->value('product_id');
        $this->assertSame(1, DB::table('stock_items')->where('family_group_id', $g)->where('product_id', $polloProductId)->count());

        // Sesion finalizada; re-finalizar -> 409
        $this->assertDatabaseHas('shopping_sessions', ['id' => $sessionId, 'status' => 'finished']);
        $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/shopping-sessions/{$sessionId}/finish", ['stock_location_id' => $alacenaId])
            ->assertStatus(409);

        // ── 4. El presupuesto refleja la compra ────────────────────────────
        $budgetId = (int) DB::table('budgets')->where('family_group_id', $g)->value('id');
        $summary = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/budgets/{$budgetId}/summary")
            ->assertStatus(200)->json('data');

        $this->assertSame(1, (int) $summary['purchase_count']);
        $this->assertEqualsWithDelta($expectedActualTotal, (float) $summary['spent_amount'], 0.01);
        $this->assertEqualsWithDelta(90000 - $expectedActualTotal, (float) $summary['available_amount'], 0.01);

        // ── 5. Alertas visibles: bajo stock y proximos vencimientos ─────────
        $low = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/stock/low-stock")
            ->assertStatus(200)->json('data');
        $this->assertNotEmpty($low, 'Deberia haber productos por debajo del minimo (arroz/tomate/leche).');

        $expiring = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/stock/expiring")
            ->assertStatus(200)->json('data');
        $this->assertNotEmpty($expiring, 'Deberia haber productos proximos a vencer (huevo/leche).');

        // ── 6. "Que cocinar hoy" — incluye la categoria "conviene cocinar pronto" ─
        $byExpiring = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/recipes/by-expiring-stock")
            ->assertStatus(200)->json('data');
        $names = array_map(function ($r) { return strtolower($r['name'] ?? ''); }, $byExpiring);
        $this->assertContains('panqueques caseros', $names, 'Panqueques usa huevo y leche (por vencer).');

        $available = $this->actingAs($this->laura)
            ->getJson("/api/v1/family-groups/{$g}/recipes/available")
            ->assertStatus(200)->json('data');
        $this->assertContains('panqueques caseros', array_map(function ($r) { return strtolower($r['name'] ?? ''); }, $available));

        // ── 7. Cocinar Panqueques con descuento de stock ───────────────────
        $harinaBefore = $this->stockQtyFor('harina 000', 'g');
        $lecheBefore  = $this->stockQtyFor('leche entera', 'ml');
        $huevoBefore  = $this->stockQtyFor('huevo', 'unit');
        $aceiteBefore = $this->stockQtyFor('aceite girasol', 'ml');
        $this->assertEqualsWithDelta(1000, $harinaBefore, 0.01);

        $panquequesId = (int) DB::table('recipes')->where('normalized_name', 'panqueques')->value('id');

        $cook = $this->actingAs($this->laura)->postJson(
            "/api/v1/recipes/{$panquequesId}/cook",
            ['servings' => 4, 'family_group_id' => $g, 'deduct_stock' => true]
        )->assertStatus(201)->json();

        // Cook log + movimientos de consumo
        $this->assertDatabaseHas('recipe_cook_logs', ['recipe_id' => $panquequesId, 'family_group_id' => $g]);
        $this->assertGreaterThan(
            0,
            StockMovement::where('related_recipe_id', $panquequesId)
                ->where('movement_type', 'consumption')->where('reason', 'recipe_cook')->count()
        );

        // Stock descontado exactamente
        $this->assertEqualsWithDelta($harinaBefore - 200, $this->stockQtyFor('harina 000', 'g'), 0.01);
        $this->assertEqualsWithDelta($lecheBefore - 400, $this->stockQtyFor('leche entera', 'ml'), 0.01);
        $this->assertEqualsWithDelta($huevoBefore - 2, $this->stockQtyFor('huevo', 'unit'), 0.01);
        $this->assertEqualsWithDelta($aceiteBefore - 20, $this->stockQtyFor('aceite girasol', 'ml'), 0.01);

        // ── 8. Historial de coccion ───────────────────────────────────────
        $cooked = $this->actingAs($this->laura)
            ->getJson('/api/v1/users/me/cooked-recipes')
            ->assertStatus(200)->json('data');
        $this->assertNotEmpty($cooked);
    }

    public function test_unknown_barcode_creates_pending_review_product_and_stock_without_duplicating(): void
    {
        $g = $this->groupId;
        $gramId = (int) DB::table('unit_measures')->where('code', 'g')->value('id');
        $alacenaId = (int) DB::table('stock_locations')->where('family_group_id', $g)->where('name', 'Alacena')->value('id');

        $this->assertNull(
            DB::table('product_barcodes')->where('barcode', \DemoScenarioSeeder::UNKNOWN_BARCODE)->value('id'),
            'El barcode reservado no debe existir en el catalogo.'
        );

        $payload = [
            'product' => [
                'name'     => 'Yerba mate demo',
                'barcode'  => \DemoScenarioSeeder::UNKNOWN_BARCODE,
                'unit_id'  => $gramId,
            ],
            'stock' => [
                'quantity'          => 500,
                'unit_id'           => $gramId,
                'stock_location_id' => $alacenaId,
            ],
        ];

        // ── 1er alta: crea producto pending_review + solicitud + stock + movimiento ─
        $first = $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/stock/manual-product", $payload)
            ->assertStatus(201)->json();

        $this->assertSame('pending_review', $first['data']['review_status']);
        $this->assertFalse($first['data']['matched_existing_product']);

        $productId = (int) $first['data']['product']['id'];
        $this->assertDatabaseHas('products', [
            'id'              => $productId,
            'status'          => 'pending_review',
            'origin'          => 'user_created',
            'family_group_id' => $g,
        ]);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => \DemoScenarioSeeder::UNKNOWN_BARCODE, 'product_id' => $productId]);
        $this->assertDatabaseHas('product_requests', ['product_id' => $productId, 'family_group_id' => $g, 'status' => 'pending']);
        $this->assertDatabaseHas('stock_items', ['product_id' => $productId, 'family_group_id' => $g]);
        $this->assertSame(1, DB::table('stock_movements')->where('product_id', $productId)->count());

        $stockAfterFirst = (float) DB::table('stock_items')->where('product_id', $productId)->sum('quantity');
        $this->assertEqualsWithDelta(500, $stockAfterFirst, 0.01);

        // ── 2do alta con el MISMO barcode: no duplica producto ni solicitud ─
        $this->actingAs($this->laura)
            ->postJson("/api/v1/family-groups/{$g}/stock/manual-product", $payload)
            ->assertStatus(200);

        $this->assertSame(1, DB::table('products')->where('family_group_id', $g)->where('status', 'pending_review')->count());
        $this->assertSame(1, DB::table('product_requests')->where('product_id', $productId)->count());
        $stockAfterSecond = (float) DB::table('stock_items')->where('product_id', $productId)->sum('quantity');
        $this->assertEqualsWithDelta(1000, $stockAfterSecond, 0.01);

        // El producto pending queda visible para su grupo en el catalogo por barcode
        $lookup = $this->actingAs($this->laura)
            ->getJson('/api/v1/products/barcode/' . \DemoScenarioSeeder::UNKNOWN_BARCODE . "?family_group_id={$g}")
            ->assertStatus(200)->json('data');
        $this->assertSame($productId, (int) $lookup['id']);
    }
}
