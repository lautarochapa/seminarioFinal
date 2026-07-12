<?php

namespace Tests\Feature\Api\V1\RecipeSteps;

use App\AuditLog;
use App\Recipe;
use App\RecipeStep;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeStepsTest extends TestCase
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

    private function step(Recipe $recipe, array $data = [])
    {
        return RecipeStep::create(array_merge([
            'recipe_id'   => $recipe->id,
            'step_number' => $data['step_number'] ?? $this->nextStep($recipe->id),
            'description' => 'Paso de prueba',
        ], $data));
    }

    private function nextStep($recipeId)
    {
        return (RecipeStep::where('recipe_id', $recipeId)->max('step_number') ?? 0) + 1;
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();

        $this->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [])
            ->assertStatus(401);
    }

    public function test_acceso_a_receta_ajena_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $owner->id]);

        $this->actingAs($other)->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [
            'description' => 'Paso ajeno',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'RECIPE_EDIT_FORBIDDEN');
    }

    public function test_alta_paso_con_orden_explicito()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [
            'description'       => 'Saltear la cebolla',
            'step_number'       => 2,
            'estimated_minutes' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.step_number', 2)
            ->assertJsonPath('data.estimated_minutes', 5);

        $this->assertDatabaseHas('recipe_steps', [
            'recipe_id'   => $recipe->id,
            'step_number' => 2,
        ]);
    }

    public function test_alta_paso_sin_orden_asigna_siguiente_disponible()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $this->step($recipe, ['step_number' => 1]);
        $this->step($recipe, ['step_number' => 2]);

        $response = $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [
            'description' => 'Tercer paso automatico',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.step_number', 3);
    }

    public function test_orden_duplicado_retorna_409()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $this->step($recipe, ['step_number' => 1]);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [
            'description' => 'Otro paso',
            'step_number' => 1,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_STEP_NUMBER_DUPLICATE');
    }

    public function test_actualizacion_parcial_paso()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $step   = $this->step($recipe, ['step_number' => 1, 'description' => 'Original']);

        $this->actingAs($user)->patchJson('/api/v1/recipes/' . $recipe->id . '/steps/' . $step->id, [
            'description'       => 'Actualizado',
            'estimated_minutes' => 10,
        ])->assertStatus(200)
            ->assertJsonPath('data.description', 'Actualizado')
            ->assertJsonPath('data.estimated_minutes', 10);
    }

    public function test_eliminar_paso()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);
        $step   = $this->step($recipe, ['step_number' => 1]);

        $this->actingAs($user)->deleteJson('/api/v1/recipes/' . $recipe->id . '/steps/' . $step->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('recipe_steps', ['id' => $step->id]);
    }

    public function test_auditoria_registra_adicion()
    {
        $user   = factory(User::class)->create();
        $recipe = $this->recipe(['owner_user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/v1/recipes/' . $recipe->id . '/steps', [
            'description' => 'Paso auditado',
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('action', 'recipe-step.added')
            ->where('entity_name', 'recipe_steps')
            ->exists());
    }
}
