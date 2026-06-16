<?php

namespace Tests\Feature\Api\V1\Ingredients;

use App\AuditLog;
use App\Ingredient;
use App\IngredientCategory;
use App\IngredientEquivalence;
use App\IngredientNutrient;
use App\Nutrient;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngredientsTest extends TestCase
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

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code' => 'unit_'.uniqid(),
            'name' => 'Gram',
            'type' => 'mass',
            'symbol' => 'g',
            'status' => 'active',
        ], $data));
    }

    private function category(array $data = [])
    {
        return IngredientCategory::create(array_merge([
            'code' => 'cat_'.uniqid(),
            'name' => 'Category',
            'status' => 'active',
            'is_active' => true,
        ], $data));
    }

    private function ingredient(array $data = [])
    {
        $name = $data['name'] ?? 'Tomate '.uniqid();

        return Ingredient::create(array_merge([
            'name' => $name,
            'normalized_name' => $data['normalized_name'] ?? strtolower(str_replace(' ', '_', $name)),
            'category_id' => null,
            'base_unit_id' => null,
            'description' => null,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ], $data));
    }

    public function test_admin_autorizacion()
    {
        $this->getJson('/api/v1/admin/ingredients')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/ingredients')
            ->assertStatus(403);
    }

    public function test_admin_crud_basico()
    {
        $admin = $this->admin();
        $category = $this->category();
        $unit = $this->unit();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/ingredients', [
            'name' => ' Tomate Redondo ',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'description' => 'Fresco',
            'is_generic' => true,
            'status' => 'active',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.name', 'Tomate Redondo')
            ->assertJsonPath('data.normalized_name', 'tomate_redondo');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/admin/ingredients/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredients/'.$id, [
            'name' => 'Tomate Cherry',
            'is_preparation' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.normalized_name', 'tomate_cherry')
            ->assertJsonPath('data.is_preparation', true);
    }

    public function test_validaciones_y_relaciones()
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredients', [
            'name' => '',
            'category_id' => 999999,
            'base_unit_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_duplicado_activo_rechazado()
    {
        $this->ingredient(['name' => 'Arroz', 'normalized_name' => 'arroz']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/ingredients', [
            'name' => ' ARROZ ',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'INGREDIENT_NAME_ALREADY_EXISTS');
    }

    public function test_filtros_y_busqueda()
    {
        $category = $this->category();
        $unit = $this->unit();
        $this->ingredient(['name' => 'Harina Integral', 'normalized_name' => 'harina_integral', 'category_id' => $category->id, 'base_unit_id' => $unit->id, 'is_preparation' => false]);
        $this->ingredient(['name' => 'Masa Lista', 'normalized_name' => 'masa_lista', 'category_id' => null, 'base_unit_id' => null, 'is_preparation' => true]);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/ingredients?search=harina&category_id='.$category->id.'&base_unit_id='.$unit->id.'&is_preparation=0&sort=name&order=asc');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Harina Integral', $response->json('data.0.name'));
    }

    public function test_baja_logica_y_restore()
    {
        $admin = $this->admin();
        $ingredient = $this->ingredient(['name' => 'Lenteja', 'normalized_name' => 'lenteja']);

        $delete = $this->actingAs($admin)->deleteJson('/api/v1/admin/ingredients/'.$ingredient->id);
        $delete->assertStatus(200);
        $this->assertNotNull($delete->json('data.deleted_at'));

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredients/'.$ingredient->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_at', null)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_auditoria_create_update_delete_y_audit_endpoint()
    {
        $admin = $this->admin();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/ingredients', [
            'name' => 'Zanahoria',
        ])->assertStatus(201);
        $id = $create->json('data.id');

        $this->actingAs($admin)->patchJson('/api/v1/admin/ingredients/'.$id, [
            'description' => 'Naranja',
        ])->assertStatus(200);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/ingredients/'.$id)->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'ingredients')->where('action', 'ingredient.created')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'ingredients')->where('action', 'ingredient.updated')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'ingredients')->where('action', 'ingredient.deleted')->exists());

        $this->actingAs($admin)->getJson('/api/v1/admin/ingredients/'.$id.'/audit')
            ->assertStatus(200)
            ->assertJsonPath('data.0.resource', 'ingredients');
    }

    public function test_catalogo_publico_excluye_inactivos_y_eliminados()
    {
        $active = $this->ingredient(['name' => 'Manzana', 'normalized_name' => 'manzana', 'status' => 'active']);
        $this->ingredient(['name' => 'Pera', 'normalized_name' => 'pera', 'status' => 'inactive']);
        $deleted = $this->ingredient(['name' => 'Banana', 'normalized_name' => 'banana']);
        $deleted->delete();

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
        $this->assertNotContains('password', array_keys($response->json('data.0') ?: []));
    }

    public function test_catalogo_detalle_solo_activo()
    {
        $active = $this->ingredient(['name' => 'Avena', 'normalized_name' => 'avena', 'status' => 'active']);
        $inactive = $this->ingredient(['name' => 'Cebada', 'normalized_name' => 'cebada', 'status' => 'inactive']);

        $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$active->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $active->id);

        $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$inactive->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'INGREDIENT_NOT_FOUND');
    }

    public function test_nutrition_con_y_sin_datos()
    {
        $unit = $this->unit(['code' => 'mg', 'symbol' => 'mg']);
        $ingredient = $this->ingredient();
        $nutrient = Nutrient::create(['code' => 'fiber', 'name' => 'Fibra', 'unit_id' => $unit->id, 'status' => 'active']);
        IngredientNutrient::create(['ingredient_id' => $ingredient->id, 'nutrient_id' => $nutrient->id, 'amount_per_100g' => 12.5, 'source' => 'test', 'status' => 'active']);

        $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$ingredient->id.'/nutrition')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nutrient.code', 'fiber');

        $empty = $this->ingredient();
        $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$empty->id.'/nutrition')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_equivalences_con_y_sin_datos()
    {
        $source = $this->ingredient(['name' => 'Azucar', 'normalized_name' => 'azucar']);
        $target = $this->ingredient(['name' => 'Miel', 'normalized_name' => 'miel']);
        IngredientEquivalence::create([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type' => 'replacement',
            'conversion_factor' => 0.8,
            'status' => 'active',
        ]);
        IngredientEquivalence::create([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $this->ingredient()->id,
            'equivalence_type' => 'inactive',
            'conversion_factor' => 1,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$source->id.'/equivalences');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.target_ingredient.id'));

        $empty = $this->ingredient();
        $this->actingAs(factory(User::class)->create())->getJson('/api/v1/ingredients/'.$empty->id.'/equivalences')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
