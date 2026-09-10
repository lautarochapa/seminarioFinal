<?php

namespace Tests\Feature\Api\V1\RecipeFavoritesCooked;

use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\StockItem;
use App\StockMovement;
use App\UnitMeasure;
use App\UnitConversion;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeFavoritesCookedTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name' => $code, 'type' => 'weight', 'symbol' => $code, 'status' => 'active',
        ]);
    }

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Receta ' . uniqid());
        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name),
            'descripcion'     => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => true,
            'is_official'     => false,
            'is_verified'     => false,
            'servings'        => 2,
        ], $overrides));
    }

    private function ingredient(UnitMeasure $unit): Ingredient
    {
        $name = 'Ing ' . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $unit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function product(Ingredient $ing, UnitMeasure $unit): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'ingredient_id'   => $ing->id,
            'package_unit_id' => $unit->id,
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

    private function addIngredient(Recipe $recipe, Ingredient $ing, UnitMeasure $unit, float $qty, bool $optional = false): void
    {
        RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => $qty,
            'is_optional'   => $optional,
            'sort_order'    => 0,
        ]);
    }

    private function stockItem(FamilyGroup $group, Product $prod, UnitMeasure $unit, float $qty, array $overrides = []): StockItem
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id'      => $prod->id,
            'unit_id'         => $unit->id,
            'quantity'        => $qty,
            'is_open'         => false,
            'status'          => 'active',
        ], $overrides));
    }

    private function familyGroup(User $user): FamilyGroup
    {
        $g = FamilyGroup::create([
            'name' => 'Grupo ' . uniqid(), 'owner_user_id' => $user->id, 'status' => 'active',
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

    public function test_agregar_favorito_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->postJson('/api/v1/recipes/' . $recipe->id . '/favorite')->assertStatus(401);
    }

    public function test_agregar_favorito_exitosamente()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/favorite')
            ->assertStatus(201)
            ->assertJsonPath('data.favorited', true);

        $this->assertDatabaseHas('recipe_favorites', ['user_id' => $user->id, 'recipe_id' => $recipe->id]);
    }

    public function test_favorito_duplicado_retorna_409()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/favorite');
        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/favorite')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_ALREADY_FAVORITED');
    }

    public function test_eliminar_favorito_exitosamente()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/favorite');
        $this->actingAs($user)->deleteJson('/api/v1/recipes/' . $recipe->id . '/favorite')
            ->assertStatus(200)
            ->assertJsonPath('data.favorited', false);

        $this->assertDatabaseMissing('recipe_favorites', ['user_id' => $user->id, 'recipe_id' => $recipe->id]);
    }

    public function test_listado_favoritos_paginado_del_usuario()
    {
        $user   = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $r1     = $this->recipe();
        $r2     = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $r1->id . '/favorite');
        $this->actingAs($user)->postJson('/api/v1/recipes/' . $r2->id . '/favorite');
        $this->actingAs($other)->postJson('/api/v1/recipes/' . $r1->id . '/favorite');

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/favorite-recipes');
        $response->assertStatus(200)->assertJsonPath('meta.total', 2);
        $ids = array_column($response->json('data'), 'recipe_id');
        $this->assertContains($r1->id, $ids);
        $this->assertNotContains($r2->id . '999', $ids);
    }

    public function test_registrar_coccion_sin_descuento()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['servings' => 2]);

        $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/cook', ['servings' => 3])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['cook_log_id']]);

        $this->assertDatabaseHas('recipe_cook_logs', [
            'user_id'          => $user->id,
            'recipe_id'        => $recipe->id,
            'servings'         => 3,
            'stock_discounted' => false,
        ]);
    }

    public function test_validacion_especifica_de_porciones_y_grupo_requerido()
    {
        $user = factory(User::class)->create();
        $recipe = $this->recipe();
        $this->actingAs($user)->postJson('/api/v1/recipes/'.$recipe->id.'/cook', ['servings' => 0])
            ->assertStatus(422)->assertJsonPath('error.field_errors.servings.0', 'Indicá una cantidad válida de porciones.');
        $this->actingAs($user)->postJson('/api/v1/recipes/'.$recipe->id.'/cook', ['servings' => 1, 'deduct_stock' => '1'])
            ->assertStatus(422)->assertJsonPath('error.field_errors.family_group_id.0', 'Seleccioná el grupo familiar del que querés descontar los ingredientes.');
    }

    public function test_checkbox_html_on_se_normaliza_y_descuenta_stock()
    {
        $user = factory(User::class)->create(); $group = $this->familyGroup($user); $grams = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]); $ing = $this->ingredient($grams); $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100); $stock = $this->stockItem($group, $prod, $grams, 200);
        $this->actingAs($user)->postJson('/api/v1/recipes/'.$recipe->id.'/cook', ['servings' => '1', 'family_group_id' => (string) $group->id, 'deduct_stock' => 'on'])->assertStatus(201);
        $this->assertEquals(100.0, (float) $stock->fresh()->quantity);
    }

    public function test_disponibilidad_positiva_implica_descuento_exitoso_para_mismas_porciones()
    {
        $user = factory(User::class)->create(); $group = $this->familyGroup($user); $grams = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]); $ing = $this->ingredient($grams); $prod = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 200); $this->stockItem($group, $prod, $grams, 400);
        $this->actingAs($user)->getJson('/api/v1/recipes/'.$recipe->id.'/availability?family_group_id='.$group->id.'&servings=4')
            ->assertStatus(200)->assertJsonPath('data.can_cook', true);
        $this->actingAs($user)->postJson('/api/v1/recipes/'.$recipe->id.'/cook', ['servings' => 4, 'family_group_id' => $group->id, 'deduct_stock' => true])->assertStatus(201);
        $this->assertDatabaseHas('stock_items', ['family_group_id' => $group->id, 'product_id' => $prod->id, 'quantity' => 0]);
    }

    public function test_registrar_coccion_con_grupo_ajeno_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($owner);
        $recipe = $this->recipe();

        $this->actingAs($other)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
                'servings'        => 1,
                'family_group_id' => $group->id,
                'deduct_stock'    => false,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_descuento_de_stock_exitoso()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 2]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 200.0);
        $stock  = $this->stockItem($group, $prod, $grams, 500.0);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 2,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(201);

        $stock->refresh();
        $this->assertEquals(300.0, (float) $stock->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'family_group_id'   => $group->id,
            'movement_type'     => 'consumption',
            'related_recipe_id' => $recipe->id,
        ]);
        $this->assertDatabaseHas('recipe_cook_logs', [
            'recipe_id'        => $recipe->id,
            'stock_discounted' => true,
        ]);
    }

    public function test_stock_insuficiente_retorna_422()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 500.0);
        $this->stockItem($group, $prod, $grams, 100.0);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    }

    public function test_descuento_convierte_unidades()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $kg     = $this->unit('kg');
        UnitConversion::create(['from_unit_id' => $kg->id, 'to_unit_id' => $grams->id, 'factor' => 1000, 'status' => 'active']);
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $kg);
        $this->addIngredient($recipe, $ing, $grams, 500.0);
        $stock  = $this->stockItem($group, $prod, $kg, 1.0);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(201);

        $stock->refresh();
        $this->assertEquals(0.5, (float) $stock->quantity);
    }

    public function test_ingrediente_faltante_hace_rollback_completo()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ingOk  = $this->ingredient($grams);
        $ingMissing = $this->ingredient($grams);
        $prod   = $this->product($ingOk, $grams);
        $this->addIngredient($recipe, $ingOk, $grams, 100.0);
        $this->addIngredient($recipe, $ingMissing, $grams, 50.0);
        $stock  = $this->stockItem($group, $prod, $grams, 200.0);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'RECIPE_INGREDIENT_MISSING_STOCK');

        $stock->refresh();
        $this->assertEquals(200.0, (float) $stock->quantity);
        $this->assertSame(0, StockMovement::where('related_recipe_id', $recipe->id)->count());
        $this->assertDatabaseMissing('recipe_cook_logs', ['recipe_id' => $recipe->id]);
    }

    public function test_multiples_stock_items_y_prioridad_por_vencimiento()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 250.0);
        $later = $this->stockItem($group, $prod, $grams, 200.0, ['expiration_date' => now()->addDays(60)->toDateString()]);
        $first = $this->stockItem($group, $prod, $grams, 100.0, ['expiration_date' => now()->addDays(10)->toDateString()]);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(201);

        $first->refresh();
        $later->refresh();
        $this->assertEquals(0.0, (float) $first->quantity);
        $this->assertEquals(50.0, (float) $later->quantity);
    }

    public function test_doble_ejecucion_con_idempotency_key_no_duplica_consumo()
    {
        $user   = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);
        $stock  = $this->stockItem($group, $prod, $grams, 300.0);
        $payload = [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
            'idempotency_key' => 'cook-panqueques-demo',
        ];

        $first = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', $payload)->assertStatus(201);
        $second = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.cook_log_id'), $second->json('data.cook_log_id'));
        $stock->refresh();
        $this->assertEquals(200.0, (float) $stock->quantity);
        $this->assertSame(1, StockMovement::where('related_recipe_id', $recipe->id)->count());
    }

    public function test_descuento_aislado_por_grupo()
    {
        $user   = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($user);
        $otherGroup = $this->familyGroup($other);
        $grams  = $this->unit('g');
        $recipe = $this->recipe(['servings' => 1]);
        $ing    = $this->ingredient($grams);
        $prod   = $this->product($ing, $grams);
        $this->addIngredient($recipe, $ing, $grams, 100.0);
        $stock  = $this->stockItem($group, $prod, $grams, 200.0);
        $otherStock = $this->stockItem($otherGroup, $prod, $grams, 200.0);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', [
            'servings'        => 1,
            'family_group_id' => $group->id,
            'deduct_stock'    => true,
        ])->assertStatus(201);

        $stock->refresh();
        $otherStock->refresh();
        $this->assertEquals(100.0, (float) $stock->quantity);
        $this->assertEquals(200.0, (float) $otherStock->quantity);
    }

    public function test_historial_coccion_del_usuario()
    {
        $user   = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', ['servings' => 1]);
        $this->actingAs($other)->postJson('/api/v1/recipes/' . $recipe->id . '/cook', ['servings' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/cooked-recipes');
        $response->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_auditoria_al_agregar_favorito()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/favorite')->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'recipe_favorite_added',
            'entity_name' => 'recipe_favorites',
        ]);
    }
}
