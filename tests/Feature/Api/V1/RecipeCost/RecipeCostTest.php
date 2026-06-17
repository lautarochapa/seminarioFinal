<?php

namespace Tests\Feature\Api\V1\RecipeCost;

use App\AuditLog;
use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\Role;
use App\StockItem;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeCostTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function unit(string $code = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name'   => $code,
            'type'   => 'weight',
            'symbol' => $code,
            'status' => 'active',
        ]);
    }

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Receta ' . uniqid());
        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name),
            'descripcion'     => '',
            'tiempo'          => '',
            'img'             => '',
            'video'           => '',
            'porcion'         => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => true,
            'is_official'     => false,
            'is_verified'     => false,
            'servings'        => 4,
        ], $overrides));
    }

    private function ingredient(UnitMeasure $baseUnit): Ingredient
    {
        $name = 'Ing ' . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $baseUnit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function product(Ingredient $ingredient, UnitMeasure $pkgUnit, float $netQty): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'ingredient_id'   => $ingredient->id,
            'package_unit_id' => $pkgUnit->id,
            'net_quantity'    => $netQty,
            'status'          => 'active',
            'is_active'       => true,
            'is_verified'     => false,
            'nombre'          => $name,
            'brand_id'        => 0,
            'codigo'          => uniqid(),
            'img'             => '',
            'habilitado'      => 1,
            'supply_id'       => 0,
        ]);
    }

    private function supermarketChainId(): int
    {
        return \App\SupermarketChain::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Test Chain', 'status' => 'active']
        )->id;
    }

    private function supermarketPrice(Product $product, float $price, string $currency = 'ARS'): SupermarketProductPrice
    {
        $sp = SupermarketProduct::create([
            'product_id'           => $product->id,
            'supermarket_chain_id' => $this->supermarketChainId(),
            'status'               => 'active',
        ]);

        return SupermarketProductPrice::create([
            'supermarket_product_id' => $sp->id,
            'price'                  => $price,
            'currency'               => $currency,
            'status'                 => 'active',
        ]);
    }

    private function addIngredient(Recipe $recipe, Ingredient $ingredient, UnitMeasure $unit, float $qty): RecipeIngredient
    {
        return RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => $qty,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);
    }

    private function familyGroup(User $user): FamilyGroup
    {
        $g = FamilyGroup::create([
            'name'          => 'Grupo ' . uniqid(),
            'owner_user_id' => $user->id,
            'status'        => 'active',
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $g->id,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return $g;
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->getJson('/api/v1/recipes/' . $recipe->id . '/cost')
            ->assertStatus(401);
    }

    public function test_receta_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/recipes/99999/cost')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RECIPE_NOT_FOUND');
    }

    public function test_calcula_costo_total_con_precios_de_supermercado()
    {
        $user   = factory(User::class)->create();
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams, 500.0); // 500g per package
        $this->supermarketPrice($prod, 100.0, 'ARS'); // $100 for 500g => $0.20/g

        $this->addIngredient($recipe, $ing, $grams, 250.0); // 250g => $50

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/cost');

        $response->assertStatus(200)
            ->assertJsonPath('data.currency', 'ARS')
            ->assertJsonPath('data.calculation_status', 'complete')
            ->assertJson(['data' => ['total_cost' => 50]]);
    }

    public function test_calcula_costo_por_porcion()
    {
        $user   = factory(User::class)->create();
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams, 100.0); // 100g per package
        $this->supermarketPrice($prod, 200.0, 'ARS'); // $200 for 100g => $2/g

        $this->addIngredient($recipe, $ing, $grams, 100.0); // 100g => $200

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/cost');

        $response->assertStatus(200)
            ->assertJson(['data' => ['total_cost' => 200, 'cost_per_serving' => 100]]);
    }

    public function test_conversion_de_unidades_en_calculo()
    {
        $user  = factory(User::class)->create();
        $grams = $this->unit('g');
        $kg    = $this->unit('kg');

        // Conversion: 1 kg = 1000 g
        \App\UnitConversion::create([
            'from_unit_id' => $kg->id,
            'to_unit_id'   => $grams->id,
            'factor'       => 1000,
            'status'       => 'active',
        ]);

        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams, 1000.0); // 1000g package
        $this->supermarketPrice($prod, 500.0, 'ARS');   // $0.50/g

        $this->addIngredient($recipe, $ing, $kg, 0.5); // 0.5 kg = 500g => $250

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/cost');

        $response->assertStatus(200)
            ->assertJson(['data' => ['total_cost' => 250]]);
    }

    public function test_ingrediente_sin_precio_marca_partial()
    {
        $user  = factory(User::class)->create();
        $grams = $this->unit('g');

        $recipe = $this->recipe(['servings' => 1]);

        // Ingredient with price
        $ing1  = $this->ingredient($grams);
        $prod1 = $this->product($ing1, $grams, 100.0);
        $this->supermarketPrice($prod1, 50.0, 'ARS');
        $this->addIngredient($recipe, $ing1, $grams, 100.0);

        // Ingredient without any price
        $ing2 = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing2, $grams, 50.0);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/cost');

        $response->assertStatus(200)
            ->assertJsonPath('data.calculation_status', 'partial');
    }

    public function test_grupo_ajeno_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($owner);
        $recipe = $this->recipe();

        $this->actingAs($other)->getJson('/api/v1/recipes/' . $recipe->id . '/cost?family_group_id=' . $group->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_grupo_usa_precios_de_stock()
    {
        $user  = factory(User::class)->create();
        $grams = $this->unit('g');
        $group = $this->familyGroup($user);

        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams, 100.0);

        // No supermarket price, only stock price
        StockItem::create([
            'family_group_id'         => $group->id,
            'product_id'              => $prod->id,
            'quantity'                => 500.0,
            'unit_id'                 => $grams->id,
            'estimated_purchase_price'=> 100.0, // $100 for 500g => $0.20/g
            'is_open'                 => false,
            'status'                  => 'active',
        ]);

        $this->addIngredient($recipe, $ing, $grams, 250.0); // 250g => $50

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/cost?family_group_id=' . $group->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.calculation_status', 'complete')
            ->assertJson(['data' => ['total_cost' => 50]]);
    }

    public function test_recalculo_requiere_permiso()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-cost')
            ->assertStatus(403);
    }

    public function test_recalculo_persiste_snapshot()
    {
        $admin  = $this->admin();
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams, 100.0);
        $this->supermarketPrice($prod, 100.0, 'ARS');
        $this->addIngredient($recipe, $ing, $grams, 100.0); // $100 total, $50/serving

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-cost');

        $response->assertStatus(200)
            ->assertJson(['data' => ['total_cost' => 100, 'cost_per_serving' => 50]]);

        $this->assertDatabaseHas('recipe_cost_snapshots', ['recipe_id' => $recipe->id]);
    }

    public function test_auditoria_registra_recalculo()
    {
        $admin  = $this->admin();
        $recipe = $this->recipe();

        $this->actingAs($admin)->postJson('/api/v1/admin/recipes/' . $recipe->id . '/recalculate-cost')
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'recipe-cost.recalculated')
            ->where('entity_name', 'recipe_cost_snapshots')
            ->exists());
    }
}
