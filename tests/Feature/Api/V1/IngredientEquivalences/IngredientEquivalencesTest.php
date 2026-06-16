<?php

namespace Tests\Feature\Api\V1\IngredientEquivalences;

use App\AuditLog;
use App\Ingredient;
use App\IngredientEquivalence;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngredientEquivalencesTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function ingredient(array $data = [])
    {
        $name = $data['name'] ?? 'Ingrediente '.uniqid();

        return Ingredient::create(array_merge([
            'name' => $name,
            'normalized_name' => $data['normalized_name'] ?? strtolower(str_replace(' ', '_', $name)),
            'status' => 'active',
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
        ], $data));
    }

    private function equivalence(array $data = [])
    {
        $source = $data['source_ingredient_id'] ?? $this->ingredient()->id;
        $target = $data['target_ingredient_id'] ?? $this->ingredient()->id;

        return IngredientEquivalence::create(array_merge([
            'source_ingredient_id' => $source,
            'target_ingredient_id' => $target,
            'equivalence_type' => 'replacement_'.uniqid(),
            'conversion_factor' => 1,
            'reason' => 'test',
            'status' => 'active',
        ], $data));
    }

    public function test_admin_autorizacion()
    {
        $this->getJson('/api/v1/admin/ingredient-equivalences')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/ingredient-equivalences')
            ->assertStatus(403);
    }

    public function test_crud_admin()
    {
        $admin = $this->admin();
        $source = $this->ingredient(['name' => 'Azucar']);
        $target = $this->ingredient(['name' => 'Edulcorante']);

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 0.25,
            'reason' => 'Menor cantidad requerida',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.source_ingredient.id', $source->id)
            ->assertJsonPath('data.target_ingredient.id', $target->id)
            ->assertJsonPath('data.conversion_factor', '0.2500');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/admin/ingredient-equivalences/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredient-equivalences/'.$id, [
            'reason' => 'Actualizado',
        ])->assertStatus(200)
            ->assertJsonPath('data.reason', 'Actualizado');
    }

    public function test_validaciones_ingredientes_factor_e_iguales()
    {
        $admin = $this->admin();
        $source = $this->ingredient();
        $target = $this->ingredient();
        $inactive = $this->ingredient(['status' => 'inactive']);
        $deleted = $this->ingredient();
        $deleted->delete();

        $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $source->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_EQUIVALENCE_SAME_INGREDIENT');

        $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $inactive->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_INVALID');

        $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $deleted->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_INVALID');
    }

    public function test_duplicado_filtros_busqueda_y_paginacion()
    {
        $admin = $this->admin();
        $source = $this->ingredient(['name' => 'Harina comun']);
        $target = $this->ingredient(['name' => 'Harina integral']);
        $equivalence = $this->equivalence([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'reason' => 'fibra',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/admin/ingredient-equivalences', [
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 1,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'INGREDIENT_EQUIVALENCE_ALREADY_EXISTS');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/ingredient-equivalences?source_ingredient_id='.$source->id.'&target_ingredient_id='.$target->id.'&equivalence_type=replacement&search=fibra&per_page=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $equivalence->id);
    }

    public function test_baja_restore_y_auditoria()
    {
        $admin = $this->admin();
        $equivalence = $this->equivalence(['equivalence_type' => 'audit']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/ingredient-equivalences/'.$equivalence->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredient-equivalences/'.$equivalence->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($admin)->getJson('/api/v1/admin/ingredient-equivalences/'.$equivalence->id.'/audit')
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'ingredient_equivalences')->where('action', 'ingredient-equivalence.deleted')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'ingredient_equivalences')->where('action', 'ingredient-equivalence.restored')->exists());
    }

    public function test_publico_con_y_sin_equivalencias_y_exclusion_inactivos()
    {
        $source = $this->ingredient(['name' => 'Leche comun']);
        $target = $this->ingredient(['name' => 'Leche deslactosada']);
        $empty = $this->ingredient();
        $inactiveTarget = $this->ingredient(['name' => 'Inactivo target']);

        $this->equivalence([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'substitution',
            'conversion_factor' => 1,
            'reason' => 'Sin lactosa',
            'status' => 'active',
        ]);
        $this->equivalence([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $inactiveTarget->id,
            'equivalence_type' => 'inactive',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/ingredients/'.$source->id.'/equivalences');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_ingredient.id', $target->id)
            ->assertJsonMissing(['source_ingredient_id' => $source->id]);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/ingredients/'.$empty->id.'/equivalences')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
