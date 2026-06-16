<?php

namespace Tests\Feature\Api\V1\Professional;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\ProfessionalUserLink;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfessionalTest extends TestCase
{
    use RefreshDatabase;

    private function createProfessional(): User
    {
        $professional = factory(User::class)->create(['status' => 'active']);
        $role = Role::where('code', 'dietologist')->first();
        DB::table('user_roles')->insert([
            'user_id'    => $professional->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);
        return $professional;
    }

    private function createMealPlanForUser(User $user): MealPlan
    {
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id'         => $user->id,
            'role_in_group'   => 'owner',
            'status'          => 'active',
            'joined_at'       => now(),
        ]);
        return MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'weekly',
            'start_date'      => now()->format('Y-m-d'),
            'end_date'        => now()->addDays(7)->format('Y-m-d'),
            'status'          => 'active',
            'mode'            => 'manual',
        ]);
    }

    public function test_acceso_sin_autenticacion()
    {
        $response = $this->getJson('/api/v1/professional-links');
        $response->assertStatus(401);
    }

    public function test_crear_vinculo()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();

        $response = $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $professional->id,
            'can_view_profile'     => true,
            'can_view_meal_plans'  => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.professional_user_id', $professional->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.can_view_profile', true);
    }

    public function test_profesional_invalido()
    {
        $user           = factory(User::class)->create();
        $nonProfessional = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $nonProfessional->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PROFESSIONAL_INVALID_ROLE');
    }

    public function test_vinculo_duplicado()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();

        $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $professional->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $professional->id,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PROFESSIONAL_DUPLICATE_LINK');
    }

    public function test_auto_vinculo_rechazado()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PROFESSIONAL_SELF_LINK');
    }

    public function test_listar_vinculos_propios()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $user->id,
            'professional_user_id' => $professional->id,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/professional-links');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professional_user_id', $professional->id);
    }

    public function test_actualizar_permisos()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();
        $link = factory(ProfessionalUserLink::class)->create([
            'user_id'              => $user->id,
            'professional_user_id' => $professional->id,
            'can_view_profile'     => false,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/professional-links/{$link->id}", [
            'can_view_profile' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.can_view_profile', true);
    }

    public function test_revocar_vinculo()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();
        $link = factory(ProfessionalUserLink::class)->create([
            'user_id'              => $user->id,
            'professional_user_id' => $professional->id,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/professional-links/{$link->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_profesional_lista_usuarios_vinculados()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($professional)->getJson('/api/v1/professional/linked-users');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_perfil_permitido()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_view_profile'     => true,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($professional)->getJson("/api/v1/professional/users/{$patient->id}/profile");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email']]);
    }

    public function test_perfil_sin_permiso_rechazado()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_view_profile'     => false,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($professional)->getJson("/api/v1/professional/users/{$patient->id}/profile");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PROFESSIONAL_ACCESS_DENIED');
    }

    public function test_perfil_usuario_no_vinculado()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();

        $response = $this->actingAs($professional)->getJson("/api/v1/professional/users/{$patient->id}/profile");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'PROFESSIONAL_USER_NOT_FOUND');
    }

    public function test_listar_planes_con_permiso()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_view_meal_plans'  => true,
            'status'               => 'active',
        ]);
        $this->createMealPlanForUser($patient);

        $response = $this->actingAs($professional)->getJson("/api/v1/professional/users/{$patient->id}/meal-plans");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_listar_planes_sin_permiso()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_view_meal_plans'  => false,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($professional)->getJson("/api/v1/professional/users/{$patient->id}/meal-plans");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PROFESSIONAL_ACCESS_DENIED');
    }

    public function test_editar_plan_con_permiso()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_edit_meal_plans'  => true,
            'status'               => 'active',
        ]);
        $plan = $this->createMealPlanForUser($patient);

        $response = $this->actingAs($professional)->patchJson(
            "/api/v1/professional/users/{$patient->id}/meal-plans/{$plan->id}",
            ['status' => 'inactive']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_editar_plan_sin_permiso()
    {
        $patient      = factory(User::class)->create();
        $professional = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_edit_meal_plans'  => false,
            'status'               => 'active',
        ]);
        $plan = $this->createMealPlanForUser($patient);

        $response = $this->actingAs($professional)->patchJson(
            "/api/v1/professional/users/{$patient->id}/meal-plans/{$plan->id}",
            ['status' => 'inactive']
        );

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PROFESSIONAL_ACCESS_DENIED');
    }

    public function test_plan_de_otro_usuario_rechazado()
    {
        $patient        = factory(User::class)->create();
        $anotherPatient = factory(User::class)->create();
        $professional   = $this->createProfessional();
        factory(ProfessionalUserLink::class)->create([
            'user_id'              => $patient->id,
            'professional_user_id' => $professional->id,
            'can_edit_meal_plans'  => true,
            'status'               => 'active',
        ]);
        $anotherPlan = $this->createMealPlanForUser($anotherPatient);

        $response = $this->actingAs($professional)->patchJson(
            "/api/v1/professional/users/{$patient->id}/meal-plans/{$anotherPlan->id}",
            ['status' => 'inactive']
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'PROFESSIONAL_PLAN_NOT_FOUND');
    }

    public function test_auditoria()
    {
        $user         = factory(User::class)->create();
        $professional = $this->createProfessional();

        $this->actingAs($user)->postJson('/api/v1/professional-links', [
            'professional_user_id' => $professional->id,
            'can_view_profile'     => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'professional-link.created',
            'entity_name' => 'professional_user_links',
        ]);
    }
}
