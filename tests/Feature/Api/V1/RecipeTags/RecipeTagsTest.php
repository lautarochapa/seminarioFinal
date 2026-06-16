<?php

namespace Tests\Feature\Api\V1\RecipeTags;

use App\AuditLog;
use App\RecipeTag;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeTagsTest extends TestCase
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

    private function tag(array $data = [])
    {
        return RecipeTag::create(array_merge([
            'code'        => 'tag_' . uniqid(),
            'name'        => 'Tag ' . uniqid(),
            'description' => null,
            'type'        => null,
            'status'      => 'active',
        ], $data));
    }

    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/recipe-tags')->assertStatus(401);
    }

    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/recipe-tags')
            ->assertStatus(403);
    }

    public function test_alta_tag_con_code_normalizado()
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/recipe-tags', [
            'code' => ' Sin Gluten ',
            'name' => 'Sin Gluten',
            'type' => 'dieta',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'sin_gluten')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('recipe_tags', ['code' => 'sin_gluten']);
    }

    public function test_code_duplicado_retorna_409()
    {
        $this->tag(['code' => 'vegano']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/recipe-tags', [
            'code' => 'VEGANO',
            'name' => 'Vegano',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_TAG_CODE_ALREADY_EXISTS');
    }

    public function test_actualizacion_parcial()
    {
        $tag = $this->tag(['name' => 'Rapido', 'type' => null]);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/recipe-tags/' . $tag->id, [
            'name' => 'Rapido y Facil',
            'type' => 'tiempo',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Rapido y Facil')
            ->assertJsonPath('data.type', 'tiempo');
    }

    public function test_baja_logica_cambia_status_a_inactive()
    {
        $admin = $this->admin();
        $tag   = $this->tag(['name' => 'Economico']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/recipe-tags/' . $tag->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('recipe_tags', ['id' => $tag->id, 'status' => 'inactive']);
    }

    public function test_delete_de_tag_ya_inactivo_retorna_409()
    {
        $tag = $this->tag(['status' => 'inactive']);

        $this->actingAs($this->admin())->deleteJson('/api/v1/admin/recipe-tags/' . $tag->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_TAG_ALREADY_INACTIVE');
    }

    public function test_restore_cambia_status_a_active()
    {
        $admin = $this->admin();
        $tag   = $this->tag(['status' => 'inactive']);

        $this->actingAs($admin)->patchJson('/api/v1/admin/recipe-tags/' . $tag->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_restore_de_tag_ya_activo_retorna_409()
    {
        $tag = $this->tag(['status' => 'active']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/recipe-tags/' . $tag->id . '/restore')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RECIPE_TAG_ALREADY_ACTIVE');
    }

    public function test_catalogo_solo_tags_activos()
    {
        $active   = $this->tag(['code' => 'activo', 'name' => 'Activo']);
        $inactive = $this->tag(['code' => 'inactivo', 'name' => 'Inactivo', 'status' => 'inactive']);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/recipe-tags');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains($active->code, $codes);
        $this->assertNotContains($inactive->code, $codes);
    }

    public function test_filtro_por_tipo_en_admin()
    {
        $this->tag(['code' => 'vegano', 'name' => 'Vegano', 'type' => 'dieta']);
        $this->tag(['code' => 'rapido', 'name' => 'Rapido', 'type' => 'tiempo']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recipe-tags?type=dieta');

        $response->assertStatus(200);
        $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();
        $this->assertEquals(['dieta'], $types);
    }

    public function test_auditoria_registra_escritura()
    {
        $admin = $this->admin();
        $tag   = $this->tag(['name' => 'Alto en Proteina']);

        $this->actingAs($admin)->patchJson('/api/v1/admin/recipe-tags/' . $tag->id, [
            'name' => 'Alto en Proteinas',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('action', 'recipe-tag.updated')
            ->where('entity_name', 'recipe_tags')
            ->exists());
    }
}
