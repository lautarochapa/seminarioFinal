<?php

namespace Tests\Feature\Api\V1\StockScan;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\ProductBarcode;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockScanTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember($role = 'member')
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'scan_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function productWithBarcode($barcode = '7791234567890', array $data = [])
    {
        $unit = $this->unit();
        $ingredient = Ingredient::create([
            'name' => 'Ingrediente '.uniqid(),
            'normalized_name' => 'ingrediente_'.uniqid(),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);
        $name = 'Producto '.uniqid();
        $product = Product::create(array_merge([
            'nombre' => $name,
            'brand_id' => $brand->id,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'codigo' => 'BC'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'is_active' => true,
            'status' => 'active',
        ], $data));

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $barcode,
            'type' => 'ean13',
            'status' => 'active',
        ]);

        return [$product, $ingredient, $unit];
    }

    private function location(FamilyGroup $group, array $data = [])
    {
        return StockLocation::create(array_merge([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->postJson('/api/v1/family-groups/1/stock/scan')->assertStatus(401);
    }

    public function test_user_outside_group_rejected()
    {
        [$member, $group] = $this->groupWithMember();
        $outsider = factory(User::class)->create();

        $this->actingAs($outsider)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 1,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_invalid_barcode_rejected()
    {
        [$member, $group] = $this->groupWithMember();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => 'bad-code',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_product_not_found()
    {
        [$member, $group] = $this->groupWithMember();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7799999999999',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 1,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_invalid_location_rejected()
    {
        [$member, $group] = $this->groupWithMember();
        [$otherMember, $otherGroup] = $this->groupWithMember();
        $this->productWithBarcode();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($otherGroup)->id,
            'quantity' => 1,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'STOCK_LOCATION_NOT_FOUND');
    }

    public function test_scan_creates_stock_item()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient] = $this->productWithBarcode();

        $response = $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.ingredient.id', $ingredient->id)
            ->assertJsonPath('data.quantity', '2.0000');

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_scan_accumulates_existing_stock_item()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $unit] = $this->productWithBarcode();
        $location = $this->location($group);
        $item = StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.quantity', '5.0000');
    }

    public function test_scan_persiste_expiration_date_del_payload()
    {
        [$member, $group] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();
        $location = $this->location($group);
        $vto = now()->addDays(45)->toDateString();

        $response = $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 2,
            'expiration_date' => $vto,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expiration_date', $vto);

        $this->assertDatabaseHas('stock_items', [
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'expiration_date' => $vto,
        ]);
    }

    public function test_scan_mismo_lote_mismo_vencimiento_acumula()
    {
        [$member, $group] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();
        $location = $this->location($group);
        $vto = now()->addDays(30)->toDateString();

        $payload = [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 2,
            'expiration_date' => $vto,
        ];

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', $payload)->assertStatus(201);
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan',
            array_merge($payload, ['quantity' => 3]))->assertStatus(200)
            ->assertJsonPath('data.quantity', '5.0000');

        $this->assertEquals(1, StockItem::where('product_id', $product->id)->count());
    }

    public function test_scan_distinto_vencimiento_crea_lote_separado()
    {
        [$member, $group] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();
        $location = $this->location($group);

        $base = [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 2,
        ];

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan',
            array_merge($base, ['expiration_date' => now()->addDays(10)->toDateString()]))->assertStatus(201);
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan',
            array_merge($base, ['expiration_date' => now()->addDays(60)->toDateString()]))->assertStatus(201);

        $lots = StockItem::where('product_id', $product->id)->get();
        $this->assertCount(2, $lots);
        $this->assertEqualsCanonicalizing(['2.0000', '2.0000'], $lots->pluck('quantity')->map(fn ($q) => (string) $q)->all());
    }

    public function test_scan_distinta_unidad_no_mezcla_stock()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $grams] = $this->productWithBarcode();
        $cups = $this->unit();
        $location = $this->location($group);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 520,
            'unit_id' => $grams->id,
        ])->assertStatus(201);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 1,
            'unit_id' => $cups->id,
        ])->assertStatus(201);

        $this->assertSame(2, StockItem::where('product_id', $product->id)->count());
        $this->assertDatabaseHas('stock_items', ['product_id' => $product->id, 'unit_id' => $grams->id, 'quantity' => 520]);
        $this->assertDatabaseHas('stock_items', ['product_id' => $product->id, 'unit_id' => $cups->id, 'quantity' => 1]);
    }

    public function test_barcode_lookup_preselecciona_unidad_existente_inequivoca()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $grams] = $this->productWithBarcode();
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 520,
            'unit_id' => $grams->id,
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/products/barcode/7791234567890?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.stock_entry_suggestion.unit_id', $grams->id)
            ->assertJsonPath('data.stock_entry_suggestion.source', 'existing_stock')
            // Sin net_quantity, la cantidad NO debe "inventarse" como 1: debe
            // quedar null para que el usuario la complete explicitamente.
            ->assertJsonPath('data.stock_entry_suggestion.quantity', null)
            ->assertJsonPath('data.stock_entry_suggestion.existing_units.0.name', 'Unidad');
    }

    public function test_barcode_lookup_producto_normalizado_propone_cantidad_y_unidad_del_paquete()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $grams] = $this->productWithBarcode('7790580146115', [
            'name' => 'Pure de tomate 520 g',
            'net_quantity' => 520,
            'package_unit_id' => null,
        ]);
        $product->package_unit_id = $grams->id;
        $product->save();

        $this->actingAs($member)
            ->getJson('/api/v1/products/barcode/7790580146115?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.stock_entry_suggestion.quantity', 520)
            ->assertJsonPath('data.stock_entry_suggestion.unit_id', $grams->id)
            ->assertJsonPath('data.stock_entry_suggestion.source', 'package');
    }

    public function test_barcode_lookup_producto_25_gramos_propone_25_g_no_1()
    {
        // Caso real reportado: "Canela molida Alicante sobre 25 g." (product_id=50
        // en el ambiente de desarrollo) tenia net_quantity=25/package_unit_id=Gramo
        // correctamente normalizados, pero el mobile mostraba cantidad=1 en el modal.
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $grams] = $this->productWithBarcode('7790150435380', [
            'name' => 'Canela molida Alicante sobre 25 g.',
            'net_quantity' => 25,
            'package_unit_id' => null,
        ]);
        $product->package_unit_id = $grams->id;
        $product->save();

        $this->actingAs($member)
            ->getJson('/api/v1/products/barcode/7790150435380?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.stock_entry_suggestion.quantity', 25)
            ->assertJsonPath('data.stock_entry_suggestion.unit_id', $grams->id)
            ->assertJsonPath('data.stock_entry_suggestion.source', 'package');
    }

    public function test_barcode_lookup_producto_900_mililitros_propone_900_ml()
    {
        [$member, $group] = $this->groupWithMember();
        $ml = UnitMeasure::create(['code' => 'ml', 'name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml', 'status' => 'active']);
        [$product] = $this->productWithBarcode('7790000000900', [
            'name' => 'Aceite de girasol 900 ml',
            'net_quantity' => 900,
            'package_unit_id' => $ml->id,
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/products/barcode/7790000000900?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.stock_entry_suggestion.quantity', 900)
            ->assertJsonPath('data.stock_entry_suggestion.unit_id', $ml->id)
            ->assertJsonPath('data.stock_entry_suggestion.source', 'package');
    }

    public function test_barcode_lookup_producto_sin_datos_requiere_seleccion_explicita()
    {
        [$member, $group] = $this->groupWithMember();
        $this->productWithBarcode('7790000000000', [
            'default_unit_id' => null,
            'package_unit_id' => null,
            'ingredient_id' => null,
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/products/barcode/7790000000000?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.stock_entry_suggestion.quantity', null)
            ->assertJsonPath('data.stock_entry_suggestion.unit_id', null)
            ->assertJsonPath('data.stock_entry_suggestion.requires_unit_selection', true);
    }

    public function test_scan_sin_unidad_conocida_requiere_seleccion_explicita()
    {
        [$member, $group] = $this->groupWithMember();
        $this->productWithBarcode('7791234567890', [
            'default_unit_id' => null,
            'package_unit_id' => null,
            'ingredient_id' => null,
        ]);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'STOCK_UNIT_REQUIRED');

        $this->assertDatabaseCount('stock_items', 0);
    }

    public function test_scan_sin_unit_id_reutiliza_unidad_existente_del_producto()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $grams] = $this->productWithBarcode('7791234567890', ['default_unit_id' => null]);
        $location = $this->location($group);
        $item = StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 520,
            'unit_id' => $grams->id,
            'status' => 'active',
        ]);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 20,
        ])->assertStatus(200)
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.quantity', '540.0000')
            ->assertJsonPath('data.unit_id', $grams->id);

        $this->assertSame(1, StockItem::where('product_id', $product->id)->count());
    }

    public function test_scan_distinta_ubicacion_crea_lote_separado()
    {
        [$member, $group] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();
        $locA = $this->location($group);
        $locB = $this->location($group);
        $vto = now()->addDays(20)->toDateString();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890', 'stock_location_id' => $locA->id, 'quantity' => 1, 'expiration_date' => $vto,
        ])->assertStatus(201);
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890', 'stock_location_id' => $locB->id, 'quantity' => 1, 'expiration_date' => $vto,
        ])->assertStatus(201);

        $this->assertEquals(2, StockItem::where('product_id', $product->id)->count());
    }

    public function test_scan_no_crea_producto_nuevo()
    {
        [$member, $group] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();
        $location = $this->location($group);
        $before = Product::count();

        $payload = ['barcode' => '7791234567890', 'stock_location_id' => $location->id, 'quantity' => 1];
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.product.id', $product->id);
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', $payload)->assertStatus(200);

        $this->assertEquals($before, Product::count());
    }

    public function test_scan_carga_stock_solo_en_el_grupo_del_usuario()
    {
        [$memberA, $groupA] = $this->groupWithMember();
        [$memberB, $groupB] = $this->groupWithMember();
        [$product] = $this->productWithBarcode();

        $this->actingAs($memberA)->postJson('/api/v1/family-groups/'.$groupA->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($groupA)->id,
            'quantity' => 4,
        ])->assertStatus(201);

        $this->assertEquals(1, StockItem::where('family_group_id', $groupA->id)->count());
        $this->assertEquals(0, StockItem::where('family_group_id', $groupB->id)->count());
    }

    public function test_scan_quantity_cero_rechazado()
    {
        [$member, $group] = $this->groupWithMember();
        $this->productWithBarcode();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 0,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_scan_unit_id_inexistente_rechazado()
    {
        [$member, $group] = $this->groupWithMember();
        $this->productWithBarcode();

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', [
            'barcode' => '7791234567890',
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 1,
            'unit_id' => 987654,
        ])->assertStatus(422)->assertJsonPath('error.code', 'STOCK_UNIT_INVALID');
    }

    public function test_stock_vencido_no_cuenta_como_disponible()
    {
        [$member, $group] = $this->groupWithMember();
        [$product, $ingredient, $unit] = $this->productWithBarcode();

        StockItem::create([
            'family_group_id' => $group->id, 'product_id' => $product->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 10, 'unit_id' => $unit->id, 'status' => 'active',
            'expiration_date' => now()->subDay()->toDateString(),
        ]);
        StockItem::create([
            'family_group_id' => $group->id, 'product_id' => $product->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 3, 'unit_id' => $unit->id, 'status' => 'active',
            'expiration_date' => now()->addDays(30)->toDateString(),
        ]);

        $repo = app(\App\Repositories\RecipeAvailability\RecipeAvailabilityRepository::class);

        $byProduct = $repo->stockByProduct($group->id, $product->id);
        $this->assertSame(3.0, $byProduct[$unit->id] ?? null);

        $byIngredient = $repo->stockByIngredient($group->id);
        $this->assertSame(3.0, $byIngredient[$ingredient->id][$unit->id] ?? null);
    }

    public function test_scan_audits_create_and_update()
    {
        [$member, $group] = $this->groupWithMember();
        $this->productWithBarcode();
        $location = $this->location($group);

        $payload = [
            'barcode' => '7791234567890',
            'stock_location_id' => $location->id,
            'quantity' => 1,
        ];

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', $payload)->assertStatus(201);
        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock/scan', $payload)->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'stock_items')->where('action', 'stock-item.created')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'stock_items')->where('action', 'stock-item.updated')->exists());
    }
}
