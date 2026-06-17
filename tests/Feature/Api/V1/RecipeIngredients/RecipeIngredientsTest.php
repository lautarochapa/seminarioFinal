<?php

namespace Tests\Feature\Api\V1\RecipeIngredients;

use App\AuditLog;
use App\Ingredient;
use App\Recipe;
use App\RecipeIngredient;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeIngredientsTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();

        DB::table('user_roles')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function recipe(array $data = [])
    {
        $name = $data['name'] ?? ('Receta ' . uniqid());

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
            'is_public'       => false,
            'is_official'     => false,
            'is_verified'     => false,
        ], $data));
    }

    private function ingredient(array $data = [])
    {
        $name = $data['name'] ?? ('Ingrediente ' . uniqid());

        return Ingredient::create(array_merge([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'status'          => 'active',
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
        ], $data));
    }

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code'   => 'u' . uniqid(),
            'name'   => 'Unidad ' . uniqid(),
            'status' => 'active',
        ], $data));
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();

        $this->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [])
            ->assertStatus(401);
    }

    public function test_acceso_a_receta_ajena_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $owner->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $this->actingAs($other)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'RECIPE_EDIT_FORBIDDEN');
    }

    public function test_alta_ingrediente_exitosa()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $response = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 2.5,
            'notes'         => 'En juliana',
            'is_optional'   => false,
            'sort_order'    => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ingredient_id', $ing->id)
            ->assertJsonPath('data.quantity', '2.5000');

        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
        ]);
    }

    public function test_ingrediente_duplicado_retorna_409()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 2,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_INGREDIENT_DUPLICATE');
    }

    public function test_ingrediente_inactivo_rechazado()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient(['status' => 'inactive']);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_NOT_FOUND');
    }

    public function test_cantidad_cero_rechazada()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 0,
        ])->assertStatus(422);
    }

    public function test_alta_con_producto_especifico_opcional()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $productId = DB::table('products')->insertGetId([
            'name'            => 'Prod ' . uniqid(),
            'nombre'          => 'prod',
            'normalized_name' => 'prod',
            'brand_id'        => 0,
            'codigo'          => 'PRD' . uniqid(),
            'img'             => '',
            'habilitado'      => true,
            'supply_id'       => 0,
            'is_verified'     => false,
            'is_active'       => true,
            'status'          => 'active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id'       => $ing->id,
            'unit_id'             => $unit->id,
            'quantity'            => 1,
            'specific_product_id' => $productId,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.specific_product_id', $productId);
    }

    public function test_actualizacion_parcial_ingrediente()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $row = RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);

        $this->actingAs($user)->patchJson('/api/v1/recipes/' . $recipe->id . '/ingredients/' . $row->id, [
            'quantity'    => 3,
            'is_optional' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '3.0000')
            ->assertJsonPath('data.is_optional', true);
    }

    public function test_eliminar_ingrediente_de_receta()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $row = RecipeIngredient::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
            'is_optional'   => false,
            'sort_order'    => 0,
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/recipes/' . $recipe->id . '/ingredients/' . $row->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('recipe_ingredients', ['id' => $row->id]);
    }

    public function test_auditoria_registra_adicion()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $unit   = $this->unit();
        $ing    = $this->ingredient();

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/ingredients', [
            'ingredient_id' => $ing->id,
            'unit_id'       => $unit->id,
            'quantity'      => 1,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('action', 'recipe-ingredient.added')
            ->where('entity_name', 'recipe_ingredients')
            ->exists());
    }
}
