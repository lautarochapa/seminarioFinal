<?php

namespace Tests\Feature\Api\V1\RecipeShoppingList;

use App\City;
use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Purchase;
use App\PurchaseItem;
use App\Recipe;
use App\RecipeIngredient;
use App\ShoppingList;
use App\StockItem;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecipeShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code): UnitMeasure
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

    private function product(Ingredient $ingredient, UnitMeasure $pkgUnit): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'ingredient_id'   => $ingredient->id,
            'package_unit_id' => $pkgUnit->id,
            'net_quantity'    => 100,
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

    private function addIngredient(Recipe $recipe, Ingredient $ingredient, UnitMeasure $unit, float $qty, bool $optional = false): RecipeIngredient
    {
        return RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id'       => $unit->id,
            'quantity'      => $qty,
            'is_optional'   => $optional,
            'sort_order'    => 0,
        ]);
    }

    private function stockItem(FamilyGroup $group, Product $product, UnitMeasure $unit, float $qty): StockItem
    {
        return StockItem::create([
            'family_group_id' => $group->id,
            'product_id'      => $product->id,
            'unit_id'         => $unit->id,
            'quantity'        => $qty,
            'is_open'         => false,
            'status'          => 'active',
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

    private function endpoint(int $groupId, int $recipeId): string
    {
        return "/api/v1/family-groups/{$groupId}/recipes/{$recipeId}/shopping-list";
    }

    private function chain(): SupermarketChain
    {
        $name = 'Chain ' . uniqid();
        return SupermarketChain::create(['name' => $name, 'code' => Str::slug($name, '_') . '_' . Str::random(4), 'status' => 'active']);
    }

    private function branch(SupermarketChain $chain): SupermarketBranch
    {
        $city = City::create(['name' => 'City ' . uniqid(), 'province' => 'Prov', 'country' => 'Argentina', 'status' => 'active']);
        return SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal ' . uniqid(),
            'address'              => 'Calle 1',
            'status'               => 'active',
        ]);
    }

    private function priceAt(Product $product, SupermarketBranch $branch, float $price): SupermarketProductPrice
    {
        $sp = SupermarketProduct::create([
            'product_id'            => $product->id,
            'supermarket_chain_id'  => $branch->supermarket_chain_id,
            'supermarket_branch_id' => $branch->id,
            'external_sku'          => 'SKU-' . uniqid(),
            'status'                => 'active',
        ]);

        return SupermarketProductPrice::create([
            'supermarket_product_id' => $sp->id,
            'price'                  => $price,
            'currency'               => 'ARS',
            'scraped_at'             => now(),
            'valid_from'             => now(),
            'valid_to'               => null,
            'status'                 => 'active',
        ]);
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->postJson($this->endpoint(1, $recipe->id))->assertStatus(401);
    }

    public function test_grupo_ajeno_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($owner);
        $recipe = $this->recipe();

        $this->actingAs($other)->postJson($this->endpoint($group->id, $recipe->id))
            ->assertStatus(403);
    }

    public function test_receta_inexistente_retorna_404()
    {
        $user  = factory(User::class)->create();
        $group = $this->familyGroup($user);

        $this->actingAs($user)->postJson($this->endpoint($group->id, 999999))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RECIPE_NOT_FOUND');
    }

    public function test_stock_suficiente_no_agrega_items()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 200.0);
        $this->stockItem($group, $prod, $grams, 300.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.items_added', 0);

        $this->assertDatabaseHas('shopping_lists', [
            'family_group_id' => $group->id,
            'source_type'     => 'recipe',
        ]);
    }

    public function test_stock_insuficiente_agrega_faltante()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 200.0);
        $this->stockItem($group, $prod, $grams, 50.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.items_added', 1);

        // prod tiene net_quantity=100g y package_unit_id=g, faltan 150g -> 2 paquetes.
        $listId = $response->json('data.shopping_list.id');
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $listId,
            'product_id'       => $prod->id,
            'quantity'         => 2.0000,
        ]);
    }

    public function test_ingrediente_sin_producto_no_bloquea_generacion()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);

        $ing = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0); // sin stock ni producto mapeado

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.items_added', 1);
    }

    public function test_unidad_no_convertible_genera_warning()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $liters = $this->unit('l');
        $recipe = $this->recipe(['servings' => 1]);

        $ing  = $this->ingredient($grams);
        $prod = $this->product($ing, $liters);
        $this->addIngredient($recipe, $ing, $grams, 100.0);
        $this->stockItem($group, $prod, $liters, 5.0); // sin conversion l -> g

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.items_added', 1);

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
    }

    public function test_lista_cerrada_retorna_409()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'source_type'     => 'manual',
            'status'          => 'completed',
        ]);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'shopping_list_id' => $list->id,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SHOPPING_LIST_CLOSED');
    }

    public function test_resultado_parcial_con_items_agregados_y_no_mapeados()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);

        $ingCovered = $this->ingredient($grams);
        $prodCovered = $this->product($ingCovered, $grams);
        $this->addIngredient($recipe, $ingCovered, $grams, 100.0);
        $this->stockItem($group, $prodCovered, $grams, 200.0); // cubierto, no se agrega

        $ingMissing = $this->ingredient($grams);
        $this->addIngredient($recipe, $ingMissing, $grams, 50.0); // sin stock, se agrega

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.items_added', 1);

        $listId = $response->json('data.shopping_list.id');
        $this->assertDatabaseMissing('shopping_list_items', [
            'shopping_list_id' => $listId,
            'ingredient_id'    => $ingCovered->id,
        ]);
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $listId,
            'ingredient_id'    => $ingMissing->id,
        ]);
    }

    public function test_reutiliza_lista_existente_y_evita_duplicados()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);

        $ing = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'source_type'     => 'manual',
            'status'          => 'draft',
        ]);

        \App\ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'ingredient_id'    => $ing->id,
            'quantity'         => 100.0,
            'unit_id'          => $grams->id,
            'status'           => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'shopping_list_id' => $list->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.items_added', 0)
            ->assertJsonPath('data.items_skipped_duplicate', 1);

        $this->assertEquals(1, \App\ShoppingListItem::where('shopping_list_id', $list->id)->count());
    }

    public function test_precio_de_sucursal_seleccionada_tiene_prioridad()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $chainA = $this->chain();
        $branchA = $this->branch($chainA);
        $chainB = $this->chain();
        $branchB = $this->branch($chainB);
        $this->priceAt($prod, $branchA, 1000.0);
        $this->priceAt($prod, $branchB, 500.0); // mas barato pero no seleccionado

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'supermarket_branch_id' => $branchA->id,
        ]);

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertEquals('branch', $priced['price_source']);
        $this->assertEquals(1000.0, $priced['estimated_unit_price']);
    }

    public function test_mejor_precio_de_cadena_seleccionada()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $chain = $this->chain();
        $branch1 = $this->branch($chain);
        $branch2 = $this->branch($chain);
        $this->priceAt($prod, $branch1, 800.0);
        $this->priceAt($prod, $branch2, 600.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'supermarket_chain_id' => $chain->id,
        ]);

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertEquals('chain', $priced['price_source']);
        $this->assertEquals(600.0, $priced['estimated_unit_price']);
    }

    public function test_ultimo_precio_pagado_por_el_grupo_sin_supermercado_seleccionado()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $purchase = Purchase::create([
            'family_group_id' => $group->id,
            'purchase_date'   => now()->subDays(2)->toDateString(),
            'status'          => 'confirmed',
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id'  => $prod->id,
            'quantity'    => 1,
            'unit_id'     => $grams->id,
            'unit_price'  => 777.0,
        ]);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertEquals('group_history', $priced['price_source']);
        $this->assertEquals(777.0, $priced['estimated_unit_price']);
    }

    public function test_mejor_precio_global_como_ultimo_fallback()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $chain = $this->chain();
        $branch = $this->branch($chain);
        $this->priceAt($prod, $branch, 333.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertEquals('best_available', $priced['price_source']);
        $this->assertEquals(333.0, $priced['estimated_unit_price']);
        $this->assertEquals(round(333.0 * $priced['purchase_quantity'], 2), $response->json('data.estimated_total'));
    }

    public function test_sin_precio_disponible_devuelve_null()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $this->product($ing, $grams); // producto existe pero sin ningun precio cargado
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id));

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertNull($priced['price_source']);
        $this->assertNull($priced['estimated_unit_price']);
        $this->assertNull($priced['estimated_subtotal']);
        $this->assertEquals(1, $response->json('data.items_without_price'));
        $this->assertEquals(0.0, $response->json('data.estimated_total'));
    }

    public function test_calcula_paquetes_necesarios_con_redondeo_hacia_arriba()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        // paquete de 500g (net_quantity en el helper product() es 100, override abajo)
        $prod = Product::create([
            'name' => 'Prod', 'normalized_name' => 'prod', 'ingredient_id' => $ing->id,
            'package_unit_id' => $grams->id, 'net_quantity' => 500,
            'status' => 'active', 'is_active' => true, 'is_verified' => false,
            'nombre' => 'Prod', 'brand_id' => 0, 'codigo' => uniqid(), 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
        ]);
        $this->addIngredient($recipe, $ing, $grams, 1200.0); // requiere 1200g -> 3 paquetes de 500g

        $chain = $this->chain();
        $branch = $this->branch($chain);
        $this->priceAt($prod, $branch, 2000.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'supermarket_branch_id' => $branch->id,
        ]);

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertEquals(3, $priced['purchase_quantity']);
        $this->assertEquals(6000.0, $priced['estimated_subtotal']);
    }

    public function test_paquetes_no_calculables_por_unidad_no_convertible_no_estima_precio()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $liters = $this->unit('l');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $liters); // presentacion en litros, receta pide gramos, sin conversion
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $chain = $this->chain();
        $branch = $this->branch($chain);
        $this->priceAt($prod, $branch, 999.0);

        $response = $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'supermarket_branch_id' => $branch->id,
        ]);

        $response->assertStatus(201);
        $priced = $response->json('data.priced_items.0');
        $this->assertNull($priced['estimated_unit_price']);
        $this->assertNotEmpty($response->json('data.warnings'));
    }

    public function test_sucursal_invalida_retorna_422()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);

        $this->actingAs($user)->postJson($this->endpoint($group->id, $recipe->id), [
            'supermarket_branch_id' => 999999,
        ])->assertStatus(422);
    }
}
