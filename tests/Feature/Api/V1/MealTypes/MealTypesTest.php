<?php

namespace Tests\Feature\Api\V1\MealTypes;

use App\MealType;
use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealTypesTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = factory(User::class)->create();
        $role = Role::firstOrCreate(['code' => 'catalog_admin'], ['name' => 'Catalog Admin', 'status' => 'active']);
        $perm = Permission::firstOrCreate(['code' => 'catalog.manage'], [
            'name' => 'Manage Catalog', 'module' => 'catalog', 'action' => 'manage', 'status' => 'active',
        ]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user;
    }

    private function mealType(array $overrides = []): MealType
    {
        return MealType::create(array_merge([
            'code'       => 'desayuno',
            'name'       => 'Desayuno',
            'sort_order' => 1,
            'status'     => 'active',
        ], $overrides));
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/meal-types')->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/admin/meal-types')->assertStatus(403);
    }

    public function test_crear_tipo_exitoso()
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/meal-types', [
                'code'       => 'almuerzo',
                'name'       => 'Almuerzo',
                'sort_order' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'almuerzo')
            ->assertJsonPath('data.name', 'Almuerzo')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('meal_types', ['code' => 'almuerzo']);
    }

    public function test_codigo_duplicado_retorna_409()
    {
        $user = $this->adminUser();
        $this->mealType();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/meal-types', [
                'code' => 'desayuno',
                'name' => 'Otro desayuno',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_TYPE_CODE_ALREADY_EXISTS');
    }

    public function test_actualizar_nombre_no_modifica_otros_campos()
    {
        $user = $this->adminUser();
        $mt   = $this->mealType();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/admin/meal-types/' . $mt->id, [
                'name' => 'Desayuno Completo',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Desayuno Completo')
            ->assertJsonPath('data.code', 'desayuno')
            ->assertJsonPath('data.sort_order', 1);
    }

    public function test_baja_logica_cambia_status_a_inactive()
    {
        $user = $this->adminUser();
        $mt   = $this->mealType();

        $this->actingAs($user)
            ->deleteJson('/api/v1/admin/meal-types/' . $mt->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('meal_types', ['id' => $mt->id, 'status' => 'inactive']);
    }

    public function test_restore_reactiva_tipo_inactivo()
    {
        $user = $this->adminUser();
        $mt   = $this->mealType(['status' => 'inactive']);

        $this->actingAs($user)
            ->patchJson('/api/v1/admin/meal-types/' . $mt->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_doble_baja_retorna_409()
    {
        $user = $this->adminUser();
        $mt   = $this->mealType(['status' => 'inactive']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/admin/meal-types/' . $mt->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_TYPE_ALREADY_INACTIVE');
    }

    public function test_catalogo_solo_activos()
    {
        $user = factory(User::class)->create();
        $this->mealType(['code' => 'desayuno', 'name' => 'Desayuno', 'status' => 'active']);
        $this->mealType(['code' => 'cena', 'name' => 'Cena', 'status' => 'inactive']);

        $response = $this->actingAs($user)->getJson('/api/v1/meal-types');
        $response->assertStatus(200);

        $statuses = array_column($response->json('data'), 'status');
        $this->assertNotEmpty($statuses);
        foreach ($statuses as $s) {
            $this->assertEquals('active', $s);
        }
        $codes = array_column($response->json('data'), 'code');
        $this->assertNotContains('cena', $codes);
    }

    public function test_catalogo_ordenado_por_sort_order()
    {
        $user = factory(User::class)->create();
        $this->mealType(['code' => 'cena', 'name' => 'Cena', 'sort_order' => 3]);
        $this->mealType(['code' => 'desayuno', 'name' => 'Desayuno', 'sort_order' => 1]);
        $this->mealType(['code' => 'almuerzo', 'name' => 'Almuerzo', 'sort_order' => 2]);

        $response = $this->actingAs($user)->getJson('/api/v1/meal-types');
        $response->assertStatus(200);

        $orders = array_column($response->json('data'), 'sort_order');
        $sorted = $orders;
        sort($sorted);
        $this->assertEquals($sorted, $orders);
    }

    public function test_auditoria_al_crear()
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/meal-types', [
                'code' => 'merienda',
                'name' => 'Merienda',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_type_created',
            'entity_name' => 'meal_types',
        ]);
    }
}
