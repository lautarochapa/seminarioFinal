<?php

namespace Tests\Feature\Api\V1\Units;

use App\AuditLog;
use App\Ingredient;
use App\Role;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnitsAndConversionsTest extends TestCase
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
            'code' => 'u_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'count',
            'symbol' => 'u',
            'status' => 'active',
        ], $data));
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

    public function test_admin_units_autorizacion()
    {
        $this->getJson('/api/v1/admin/units')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/units')
            ->assertStatus(403);
    }

    public function test_crud_unidades()
    {
        $admin = $this->admin();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/units', [
            'code' => ' KG ',
            'name' => 'Kilogramo',
            'type' => 'mass',
            'symbol' => 'kg',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'kg')
            ->assertJsonPath('data.type', 'mass');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/admin/units/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/units/'.$id, [
            'name' => 'Kilo',
            'symbol' => 'kg',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Kilo');
    }

    public function test_unidad_duplicada_y_validacion()
    {
        $this->unit(['code' => 'g', 'type' => 'mass']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/units', [
            'code' => ' G ',
            'name' => 'Gramo',
            'type' => 'mass',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'UNIT_CODE_ALREADY_EXISTS');

        $this->actingAs($this->admin())->postJson('/api/v1/admin/units', [
            'code' => 'bad',
            'name' => 'Bad',
            'type' => 'unknown',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_listado_unidades_filtros_busqueda_paginacion()
    {
        $this->unit(['code' => 'g', 'name' => 'Gramo', 'type' => 'mass', 'symbol' => 'g']);
        $this->unit(['code' => 'ml', 'name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml']);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/units?search=gram&type=mass&per_page=1&sort=code&order=asc');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1);
        $this->assertEquals('g', $response->json('data.0.code'));
    }

    public function test_baja_restore_auditoria_y_catalogo_publico()
    {
        $admin = $this->admin();
        $unit = $this->unit(['code' => 'cup', 'name' => 'Taza', 'type' => 'household']);
        $inactive = $this->unit(['code' => 'old', 'name' => 'Vieja', 'status' => 'inactive']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/units/'.$unit->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin)->patchJson('/api/v1/admin/units/'.$unit->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($admin)->getJson('/api/v1/admin/units/'.$unit->id.'/audit')
            ->assertStatus(200);

        $response = $this->actingAs(factory(User::class)->create())->getJson('/api/v1/units');
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('cup', $codes);
        $this->assertNotContains($inactive->code, $codes);

        $this->assertTrue(AuditLog::where('entity_name', 'unit_measures')->where('action', 'unit.deleted')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'unit_measures')->where('action', 'unit.restored')->exists());
    }

    public function test_admin_conversions_autorizacion()
    {
        $this->getJson('/api/v1/admin/unit-conversions')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/unit-conversions')
            ->assertStatus(403);
    }

    public function test_crud_conversion_general_y_especifica()
    {
        $admin = $this->admin();
        $from = $this->unit(['code' => 'kg', 'type' => 'mass']);
        $to = $this->unit(['code' => 'g', 'type' => 'mass']);
        $ingredient = $this->ingredient();

        $general = $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'factor' => 1000,
            'notes' => 'general',
        ]);

        $general->assertStatus(201)
            ->assertJsonPath('data.factor', '1000.00000000')
            ->assertJsonPath('data.ingredient_id', null);

        $specific = $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'ingredient_id' => $ingredient->id,
            'factor' => 900,
        ]);
        $specific->assertStatus(201)
            ->assertJsonPath('data.ingredient_id', $ingredient->id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/unit-conversions/'.$general->json('data.id'), [
            'factor' => 1001,
        ])->assertStatus(200)
            ->assertJsonPath('data.factor', '1001.00000000');
    }

    public function test_conversion_validaciones_dominio()
    {
        $admin = $this->admin();
        $unit = $this->unit();
        $other = $this->unit();
        $inactiveIngredient = $this->ingredient(['status' => 'inactive']);

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $unit->id,
            'to_unit_id' => $unit->id,
            'factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'UNIT_CONVERSION_SAME_UNIT');

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $unit->id,
            'to_unit_id' => $other->id,
            'factor' => -1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $unit->id,
            'to_unit_id' => $other->id,
            'ingredient_id' => $inactiveIngredient->id,
            'factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_INVALID');
    }

    public function test_conversion_duplicada_filtros_baja_restore_y_auditoria()
    {
        $admin = $this->admin();
        $from = $this->unit(['code' => 'l', 'type' => 'volume']);
        $to = $this->unit(['code' => 'ml', 'type' => 'volume']);
        $conversion = UnitConversion::create([
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'factor' => 1000,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'factor' => 1000,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'UNIT_CONVERSION_ALREADY_EXISTS');

        $this->actingAs($admin)->getJson('/api/v1/admin/unit-conversions?from_unit_id='.$from->id.'&to_unit_id='.$to->id.'&per_page=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $conversion->id);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/unit-conversions/'.$conversion->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin)->patchJson('/api/v1/admin/unit-conversions/'.$conversion->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($admin)->getJson('/api/v1/admin/unit-conversions/'.$conversion->id.'/audit')
            ->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'unit_conversions')->where('action', 'unit-conversion.deleted')->exists());
    }

    public function test_unidad_o_ingrediente_inexistente_o_eliminado()
    {
        $admin = $this->admin();
        $from = $this->unit();
        $to = $this->unit();
        $deletedIngredient = $this->ingredient();
        $deletedIngredient->delete();

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => 999999,
            'to_unit_id' => $to->id,
            'factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->postJson('/api/v1/admin/unit-conversions', [
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'ingredient_id' => $deletedIngredient->id,
            'factor' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INGREDIENT_INVALID');
    }
}
