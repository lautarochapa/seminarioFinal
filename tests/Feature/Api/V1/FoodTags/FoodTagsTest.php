<?php

namespace Tests\Feature\Api\V1\FoodTags;

use App\AuditLog;
use App\FoodTag;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FoodTagsTest extends TestCase
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

    private function tag(array $data = [])
    {
        return FoodTag::create(array_merge([
            'code' => 'tag_'.uniqid(),
            'name' => 'Tag '.uniqid(),
            'description' => null,
            'type' => 'classification',
            'status' => 'active',
        ], $data));
    }

    public function test_admin_autorizacion()
    {
        $this->getJson('/api/v1/admin/food-tags')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/food-tags')
            ->assertStatus(403);
    }

    public function test_alta_detalle_y_actualizacion()
    {
        $admin = $this->admin();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/food-tags', [
            'code' => ' GLUTEN_FREE ',
            'name' => 'Sin gluten',
            'description' => 'Apto sin gluten',
            'type' => 'dietary',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'gluten_free')
            ->assertJsonPath('data.name', 'Sin gluten');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/admin/food-tags/'.$id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->actingAs($admin)->patchJson('/api/v1/admin/food-tags/'.$id, [
            'name' => 'Libre de gluten',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Libre de gluten');
    }

    public function test_codigo_duplicado_campos_protegidos_y_detalle_inexistente()
    {
        $this->tag(['code' => 'vegan']);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/admin/food-tags', [
            'code' => ' VEGAN ',
            'name' => 'Vegano',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'FOOD_TAG_CODE_ALREADY_EXISTS');

        $this->actingAs($admin)->postJson('/api/v1/admin/food-tags', [
            'code' => 'new_tag',
            'name' => 'Nuevo',
            'category' => 'no-existe',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->getJson('/api/v1/admin/food-tags/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'FOOD_TAG_NOT_FOUND');
    }

    public function test_baja_restore_y_auditoria()
    {
        $admin = $this->admin();
        $tag = $this->tag(['code' => 'low_sodium']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/food-tags/'.$tag->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('food_tags', ['id' => $tag->id]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/food-tags/'.$tag->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertTrue(AuditLog::where('entity_name', 'food_tags')->where('action', 'food-tag.deleted')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'food_tags')->where('action', 'food-tag.restored')->exists());
    }

    public function test_catalogo_solo_activos_y_busqueda()
    {
        $active = $this->tag(['code' => 'high_sodium', 'name' => 'Alto sodio', 'status' => 'active']);
        $this->tag(['code' => 'inactive_tag', 'name' => 'Oculto', 'status' => 'inactive']);
        $deleted = $this->tag(['code' => 'deleted_tag', 'name' => 'Eliminado']);
        $deleted->delete();

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/food-tags?search=sodio');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['code' => 'inactive_tag'])
            ->assertJsonMissing(['code' => 'deleted_tag']);
    }

    public function test_rutas_registradas_en_api()
    {
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/food-tags', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/food-tags', 'GET')));
    }
}
