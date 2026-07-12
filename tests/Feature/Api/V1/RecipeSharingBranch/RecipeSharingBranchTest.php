<?php

namespace Tests\Feature\Api\V1\RecipeSharingBranch;

use App\Ingredient;
use App\Recipe;
use App\RecipeIngredient;
use App\RecipeStep;
use App\RecipeTag;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeSharingBranchTest extends TestCase
{
    use RefreshDatabase;

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Recipe ' . uniqid());
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
            'servings'        => 2,
        ], $overrides));
    }

    private function unit(): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => 'g'], [
            'name' => 'gramo', 'type' => 'weight', 'symbol' => 'g', 'status' => 'active',
        ]);
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

    public function test_share_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->postJson('/api/v1/recipes/' . $recipe->id . '/share')->assertStatus(401);
    }

    public function test_compartir_receta_propia_la_hace_publica()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/share')
            ->assertStatus(200)
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.is_verified', false);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'is_public' => true]);
    }

    public function test_compartir_receta_ajena_retorna_403()
    {
        $owner = factory(User::class)->create();
        $other = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/share')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'RECIPE_FORBIDDEN');
    }

    public function test_doble_share_retorna_409()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id, 'is_public' => true]);

        $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/share')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_ALREADY_PUBLIC');
    }

    public function test_unshare_vuelve_la_receta_privada()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id, 'is_public' => true]);

        $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/unshare')
            ->assertStatus(200)
            ->assertJsonPath('data.is_public', false);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'is_public' => false]);
    }

    public function test_branch_crea_receta_nueva_con_referencia_a_origen()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $owner->id, 'is_public' => true]);

        $response = $this->actingAs($other)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/branch')
            ->assertStatus(201);

        $branchId = $response->json('data.id');
        $this->assertNotEquals($recipe->id, $branchId);
        $this->assertDatabaseHas('recipes', [
            'id'                      => $branchId,
            'branched_from_recipe_id' => $recipe->id,
            'owner_user_id'           => $other->id,
        ]);
    }

    public function test_branch_copia_ingredientes_y_pasos()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id, 'is_public' => true]);
        $unit   = $this->unit();
        $ing    = $this->ingredient($unit);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'unit_id' => $unit->id,
            'quantity' => 100, 'is_optional' => false, 'sort_order' => 1,
        ]);
        RecipeStep::create([
            'recipe_id' => $recipe->id, 'step_number' => 1, 'description' => 'Step 1',
        ]);

        $branchId = $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/branch')
            ->json('data.id');

        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $branchId, 'ingredient_id' => $ing->id]);
        $this->assertDatabaseHas('recipe_steps', ['recipe_id' => $branchId, 'step_number' => 1]);
    }

    public function test_branch_queda_privado_y_no_verificado()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id, 'is_public' => true, 'is_verified' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/recipes/' . $recipe->id . '/branch')
            ->assertStatus(201);

        $this->assertFalse((bool) $response->json('data.is_public'));
        $this->assertFalse((bool) $response->json('data.is_official'));
        $this->assertFalse((bool) $response->json('data.is_verified'));

        $this->assertEquals($user->id, $response->json('data.owner_user_id'));
    }

    public function test_auditoria_al_compartir()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/share')->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'recipe_shared',
            'entity_name' => 'recipes',
            'entity_id'   => $recipe->id,
        ]);
    }
}
