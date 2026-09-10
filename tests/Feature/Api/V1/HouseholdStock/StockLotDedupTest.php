<?php

namespace Tests\Feature\Api\V1\HouseholdStock;

use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\ProductBarcode;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BUG-003: el alta de stock (scanner, "Mi cocina" y producto manual) debe
 * ACUMULAR cuando corresponde al mismo lote (grupo + producto + unidad +
 * vencimiento + ubicacion, o sin ubicacion si hay un unico lote), y mantener
 * separados los lotes reales distintos (otra ubicacion / otro vencimiento).
 * No deduplica por nombre.
 */
class StockLotDedupTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $group;
    private $unit;
    private $product;
    private $location;
    private const BARCODE = '7790000012345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->group = FamilyGroup::create(['name' => 'Casa', 'owner_user_id' => $this->user->id, 'status' => 'active']);
        DB::table('family_group_members')->insert([
            'family_group_id' => $this->group->id, 'user_id' => $this->user->id, 'role_in_group' => 'owner',
            'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->unit = UnitMeasure::firstOrCreate(['code' => 'unit'], ['name' => 'Unidad', 'type' => 'count', 'symbol' => 'u', 'status' => 'active']);
        $ingredient = Ingredient::create([
            'name' => 'Huevo', 'normalized_name' => 'huevo', 'base_unit_id' => $this->unit->id,
            'is_generic' => true, 'is_preparation' => false, 'is_supplement' => false, 'status' => 'active',
        ]);
        $this->product = Product::create([
            'name' => 'Huevos Granja Feliz x6', 'normalized_name' => 'huevos granja feliz x6',
            'ingredient_id' => $ingredient->id, 'default_unit_id' => $this->unit->id, 'package_unit_id' => $this->unit->id,
            'net_quantity' => 6, 'status' => 'active', 'is_active' => true, 'is_verified' => true,
            'nombre' => 'Huevos Granja Feliz x6', 'brand_id' => 0, 'codigo' => self::BARCODE, 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
        ]);
        ProductBarcode::create(['product_id' => $this->product->id, 'barcode' => self::BARCODE, 'type' => 'EAN13', 'status' => 'active']);
        $this->location = StockLocation::create(['family_group_id' => $this->group->id, 'name' => 'Heladera', 'type' => 'fridge', 'status' => 'active']);
    }

    private function lotCount(): int
    {
        return StockItem::where('family_group_id', $this->group->id)->where('product_id', $this->product->id)->count();
    }

    private function totalQty(): float
    {
        return (float) StockItem::where('family_group_id', $this->group->id)->where('product_id', $this->product->id)->sum('quantity');
    }

    public function test_manual_and_household_adds_accumulate_into_the_existing_lot(): void
    {
        // 1. Scanner -> crea el lote en Heladera.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock/scan", [
            'barcode' => self::BARCODE, 'stock_location_id' => $this->location->id, 'quantity' => 6, 'unit_id' => $this->unit->id,
        ])->assertSuccessful();
        $this->assertSame(1, $this->lotCount());
        $this->assertEqualsWithDelta(6, $this->totalQty(), 0.001);

        // 2. Producto manual (barcode CONOCIDO) SIN ubicacion -> acumula en el lote existente.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock/manual-product", [
            'product' => ['name' => 'Huevos Granja Feliz x6', 'barcode' => self::BARCODE, 'unit_id' => $this->unit->id],
            'stock'   => ['quantity' => 6, 'unit_id' => $this->unit->id],
        ])->assertStatus(200);
        $this->assertSame(1, $this->lotCount(), 'El alta manual sin ubicacion no debe crear una fila huerfana.');
        $this->assertEqualsWithDelta(12, $this->totalQty(), 0.001);

        // 3. "Mi cocina" (POST /stock) SIN ubicacion -> tambien acumula.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 3, 'unit_id' => $this->unit->id,
        ])->assertStatus(200);
        $this->assertSame(1, $this->lotCount());
        $this->assertEqualsWithDelta(15, $this->totalQty(), 0.001);
    }

    public function test_different_expiration_or_location_stays_a_separate_lot(): void
    {
        // Lote base (sin vencimiento) en Heladera.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock/scan", [
            'barcode' => self::BARCODE, 'stock_location_id' => $this->location->id, 'quantity' => 6, 'unit_id' => $this->unit->id,
        ])->assertSuccessful();

        // Distinto vencimiento -> lote nuevo.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 6, 'unit_id' => $this->unit->id,
            'expiration_date' => now()->addDays(10)->toDateString(),
        ])->assertStatus(201);
        $this->assertSame(2, $this->lotCount());

        // Distinta ubicacion -> lote nuevo.
        $alacena = StockLocation::create(['family_group_id' => $this->group->id, 'name' => 'Alacena', 'type' => 'pantry', 'status' => 'active']);
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 6, 'unit_id' => $this->unit->id, 'stock_location_id' => $alacena->id,
        ])->assertStatus(201);
        $this->assertSame(3, $this->lotCount());
        $this->assertEqualsWithDelta(18, $this->totalQty(), 0.001);
    }

    public function test_same_lot_with_explicit_location_accumulates(): void
    {
        foreach ([6, 6, 6] as $qty) {
            $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
                'product_id' => $this->product->id, 'quantity' => $qty, 'unit_id' => $this->unit->id, 'stock_location_id' => $this->location->id,
            ])->assertSuccessful();
        }
        $this->assertSame(1, $this->lotCount());
        $this->assertEqualsWithDelta(18, $this->totalQty(), 0.001);
    }

    public function test_add_without_location_when_two_lots_exist_creates_new_row_instead_of_guessing(): void
    {
        $alacena = StockLocation::create(['family_group_id' => $this->group->id, 'name' => 'Alacena', 'type' => 'pantry', 'status' => 'active']);
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 6, 'unit_id' => $this->unit->id, 'stock_location_id' => $this->location->id,
        ])->assertStatus(201);
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 6, 'unit_id' => $this->unit->id, 'stock_location_id' => $alacena->id,
        ])->assertStatus(201);
        $this->assertSame(2, $this->lotCount());

        // Sin ubicacion y con 2 lotes candidatos: no adivina, crea fila sin ubicacion.
        $this->actingAs($this->user)->postJson("/api/v1/family-groups/{$this->group->id}/stock", [
            'product_id' => $this->product->id, 'quantity' => 6, 'unit_id' => $this->unit->id,
        ])->assertStatus(201);
        $this->assertSame(3, $this->lotCount());
    }
}
