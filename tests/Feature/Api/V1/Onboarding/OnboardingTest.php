<?php

namespace Tests\Feature\Api\V1\Onboarding;

use App\Allergy;
use App\DietaryRestriction;
use App\FamilyGroup;
use App\HealthCondition;
use App\Objective;
use App\User;
use App\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return factory(User::class)->create();
    }

    private function objective(): Objective
    {
        return Objective::create([
            'code'   => 'obj_' . Str::random(6),
            'name'   => 'Bajar de peso',
            'status' => 'active',
        ]);
    }

    private function attachObjective(User $user, Objective $objective): void
    {
        DB::table('user_objectives')->insert([
            'user_id'      => $user->id,
            'objective_id' => $objective->id,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function group(User $owner): FamilyGroup
    {
        $group = FamilyGroup::create([
            'name'          => 'Hogar ' . Str::random(4),
            'owner_user_id' => $owner->id,
            'status'        => 'active',
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $group->id,
            'user_id'         => $owner->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return $group;
    }

    private function status(User $user): array
    {
        return $this->actingAs($user)->getJson('/api/v1/users/me/onboarding')
            ->assertStatus(200)
            ->json('data');
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/users/me/onboarding')->assertStatus(401);
    }

    public function test_usuario_nuevo_esta_incompleto()
    {
        $data = $this->status($this->user());

        $this->assertFalse($data['complete']);
        $this->assertSame('basic_profile', $data['next_step']);
        $this->assertSame(0, $data['completed_count']);
        $this->assertFalse($data['steps']['basic_profile']['complete']);
        $this->assertFalse($data['steps']['objective']['complete']);
        $this->assertFalse($data['steps']['meals_per_day']['complete']);
        $this->assertFalse($data['steps']['family_group']['complete']);
        $this->assertTrue($data['steps']['food_preferences']['complete']);
        $this->assertTrue($data['steps']['food_preferences']['optional']);
        $this->assertContains('height_cm', $data['steps']['basic_profile']['missing']);
        $this->assertContains('current_weight_kg', $data['steps']['basic_profile']['missing']);
    }

    public function test_guardado_de_perfil_completa_datos_basicos_y_comidas()
    {
        $user = $this->user();

        $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'height_cm'         => 170,
            'current_weight_kg' => 80.5,
            'meals_per_day'     => 4,
        ])->assertStatus(200);

        $data = $this->status($user);
        $this->assertTrue($data['steps']['basic_profile']['complete']);
        $this->assertSame([], $data['steps']['basic_profile']['missing']);
        $this->assertTrue($data['steps']['meals_per_day']['complete']);
        $this->assertSame(4, $data['steps']['meals_per_day']['value']);
        $this->assertSame('objective', $data['next_step']);
        $this->assertFalse($data['complete']);
    }

    public function test_campos_opcionales_no_bloquean_datos_basicos()
    {
        $user = $this->user();
        UserProfile::create([
            'user_id'           => $user->id,
            'height_cm'         => 165,
            'current_weight_kg' => 60,
        ]);

        $data = $this->status($user);
        $this->assertTrue($data['steps']['basic_profile']['complete']);
        $this->assertFalse($data['steps']['basic_profile']['has_target_weight']);
    }

    public function test_objetivo_completa_su_paso()
    {
        $user = $this->user();
        $this->attachObjective($user, $this->objective());

        $data = $this->status($user);
        $this->assertTrue($data['steps']['objective']['complete']);
        $this->assertSame(1, $data['steps']['objective']['objectives_count']);
    }

    public function test_comidas_por_dia_menor_a_uno_no_completa()
    {
        $user = $this->user();
        UserProfile::create(['user_id' => $user->id, 'meals_per_day' => 0]);

        $data = $this->status($user);
        $this->assertFalse($data['steps']['meals_per_day']['complete']);
    }

    public function test_preferencias_alimentarias_reflejan_conteos_y_siguen_opcionales()
    {
        $user = $this->user();
        $r = DietaryRestriction::create(['code' => 'r_' . Str::random(5), 'name' => 'Sin gluten', 'status' => 'active']);
        $a = Allergy::create(['code' => 'a_' . Str::random(5), 'name' => 'Maní', 'status' => 'active']);
        $c = HealthCondition::create(['code' => 'c_' . Str::random(5), 'name' => 'Hipertensión', 'status' => 'active']);
        DB::table('user_dietary_restrictions')->insert(['user_id' => $user->id, 'dietary_restriction_id' => $r->id, 'created_at' => now()]);
        DB::table('user_allergies')->insert(['user_id' => $user->id, 'allergy_id' => $a->id, 'created_at' => now()]);
        DB::table('user_health_conditions')->insert(['user_id' => $user->id, 'health_condition_id' => $c->id, 'created_at' => now()]);

        $data = $this->status($user);
        $fp = $data['steps']['food_preferences'];
        $this->assertTrue($fp['complete']);
        $this->assertTrue($fp['optional']);
        $this->assertSame(1, $fp['restrictions_count']);
        $this->assertSame(1, $fp['allergies_count']);
        $this->assertSame(1, $fp['health_conditions_count']);
        $this->assertFalse($data['complete']);
    }

    public function test_creacion_de_grupo_familiar_completa_su_paso()
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/v1/family-groups', ['name' => 'Casa Núñez'])
            ->assertStatus(201);

        $data = $this->status($user);
        $this->assertTrue($data['steps']['family_group']['complete']);
        $this->assertGreaterThanOrEqual(1, $data['steps']['family_group']['groups_count']);
    }

    public function test_onboarding_completo_cuando_todos_los_pasos_requeridos_estan()
    {
        $user = $this->user();
        $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'height_cm'         => 172,
            'current_weight_kg' => 75,
            'meals_per_day'     => 3,
        ])->assertStatus(200);
        $this->attachObjective($user, $this->objective());
        $this->group($user);

        $data = $this->status($user);
        $this->assertTrue($data['complete']);
        $this->assertNull($data['next_step']);
        $this->assertSame(4, $data['completed_count']);
    }

    public function test_grupo_ajeno_no_cuenta_como_membresia()
    {
        $user  = $this->user();
        $other = $this->user();
        $this->group($other);

        $data = $this->status($user);
        $this->assertFalse($data['steps']['family_group']['complete']);
        $this->assertSame(0, $data['steps']['family_group']['groups_count']);
    }

    public function test_nombre_utf8_en_perfil_no_rompe_el_status()
    {
        $user = $this->user();
        $this->actingAs($user)->patchJson('/api/v1/users/me/profile', [
            'name'              => 'José Ángel Piñón',
            'height_cm'         => 168,
            'current_weight_kg' => 62,
            'meals_per_day'     => 5,
        ])->assertStatus(200);

        $data = $this->status($user);
        $this->assertTrue($data['steps']['basic_profile']['complete']);
        $this->assertTrue(mb_check_encoding(json_encode($data), 'UTF-8'));
    }

    public function test_web_redirige_al_onboarding_cuando_esta_incompleto()
    {
        $user = $this->user();
        $user->assignDefaultRole();

        $this->actingAs($user)->get('/web')
            ->assertStatus(302)
            ->assertRedirect('/web/onboarding');

        $this->actingAs($user)->get('/web/onboarding')->assertStatus(200);
        $this->actingAs($user)->get('/web/profile-objectives')->assertStatus(200);
        $this->actingAs($user)->get('/web/family-group')->assertStatus(200);
    }

    public function test_web_no_redirige_cuando_el_onboarding_esta_completo()
    {
        $user = $this->user();
        $user->assignDefaultRole();

        UserProfile::create([
            'user_id' => $user->id, 'height_cm' => 170, 'current_weight_kg' => 70, 'meals_per_day' => 3,
        ]);
        $this->attachObjective($user, $this->objective());
        $this->group($user);

        $this->actingAs($user)->get('/web')->assertStatus(200);
    }

    public function test_permiso_web_onboarding_existe_y_esta_asignado_a_user_y_super_admin()
    {
        $permId = DB::table('permissions')->where('code', 'web.user.onboarding')->value('id');
        $this->assertNotNull($permId);

        $roleIds = DB::table('roles')->whereIn('code', ['user', 'super_admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_id' => $permId]);
        }
    }
}
