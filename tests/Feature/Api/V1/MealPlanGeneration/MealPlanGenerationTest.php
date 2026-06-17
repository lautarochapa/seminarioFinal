<?php

namespace Tests\Feature\Api\V1\MealPlanGeneration;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function memberUser(): array
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id]);
        factory(FamilyGroupMember::class)->create([
            'user_id'         => $user->id,
            'family_group_id' => $group->id,
            'status'          => 'active',
        ]);
        return [$user, $group];
    }

    private function mealType(): MealType
    {
        return MealType::create([
            'code'       => 'mt_' . uniqid(),
            'name'       => 'Almuerzo',
            'sort_order' => 1,
            'status'     => 'active',
        ]);
    }

    private function recipe(array $overrides = [])
    {
        return \App\Recipe::create(array_merge([
            'nombre'      => 'Receta Test',
            'descripcion' => 'Descripcion test',
            'tiempo'      => '30',
            'img'         => '',
            'video'       => '',
            'porcion'     => '2',
            'calorias'    => 200,
            'is_public'   => true,
            'status'      => 'active',
        ], $overrides));
    }

    private function pendingPlan(int $groupId, int $userId): MealPlan
    {
        return MealPlan::create([
            'family_group_id' => $groupId,
            'created_by'      => $userId,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'mode'            => 'auto',
            'status'          => 'pending',
        ]);
    }

    private function generatePayload(): array
    {
        return [
            'period_type' => 'daily',
            'start_date'  => '2026-06-16',
            'end_date'    => '2026-06-16',
        ];
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $group = factory(FamilyGroup::class)->create();
        $this->postJson("/api/v1/family-groups/{$group->id}/meal-plans/generate", $this->generatePayload())
            ->assertStatus(401);
    }

    public function test_no_miembro_retorna_403()
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create();

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/generate", $this->generatePayload())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GENERATION_GROUP_NOT_FOUND');
    }

    public function test_generate_crea_plan_pendiente_con_items()
    {
        [$user, $group] = $this->memberUser();
        $this->mealType();
        $this->recipe();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/generate", $this->generatePayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.family_group_id', $group->id);

        $this->assertDatabaseHas('meal_plans', [
            'family_group_id' => $group->id,
            'status'          => 'pending',
            'mode'            => 'auto',
        ]);

        $planId = $response->json('data.id');
        $this->assertDatabaseHas('meal_plan_items', ['meal_plan_id' => $planId]);
    }

    public function test_generate_sin_recetas_activas_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $this->mealType();

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/generate", $this->generatePayload())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GENERATION_NO_RECIPES_AVAILABLE');
    }

    public function test_approve_plan_pendiente_cambia_estado()
    {
        [$user, $group] = $this->memberUser();
        $plan = $this->pendingPlan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('meal_plans', ['id' => $plan->id, 'status' => 'approved']);
    }

    public function test_approve_doble_retorna_409()
    {
        [$user, $group] = $this->memberUser();
        $plan = $this->pendingPlan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/approve")
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GENERATION_ALREADY_APPROVED');
    }

    public function test_approve_plan_de_otro_grupo_retorna_403()
    {
        $intruder              = factory(User::class)->create();
        [$owner, $otherGroup] = $this->memberUser();
        $plan = $this->pendingPlan($otherGroup->id, $owner->id);

        $this->actingAs($intruder)
            ->postJson("/api/v1/family-groups/{$otherGroup->id}/meal-plans/{$plan->id}/approve")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GENERATION_GROUP_NOT_FOUND');
    }

    public function test_regenerate_reemplaza_items_del_plan()
    {
        [$user, $group] = $this->memberUser();
        $mt     = $this->mealType();
        $recipe = $this->recipe();
        $plan   = $this->pendingPlan($group->id, $user->id);

        MealPlanItem::create([
            'meal_plan_id'          => $plan->id,
            'date'                  => '2026-06-16',
            'meal_type_id'          => $mt->id,
            'free_meal_description' => 'Original',
            'is_eating_out'         => false,
            'status'                => 'planned',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/regenerate");

        $response->assertStatus(200);

        $this->assertSoftDeleted('meal_plan_items', ['free_meal_description' => 'Original']);

        $activeCount = MealPlanItem::where('meal_plan_id', $plan->id)->whereNull('deleted_at')->count();
        $this->assertGreaterThan(0, $activeCount);
    }

    public function test_regenerate_plan_aprobado_retorna_409()
    {
        [$user, $group] = $this->memberUser();
        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'status'          => 'approved',
            'approved_at'     => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/regenerate")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GENERATION_CANNOT_REGENERATE');
    }

    public function test_auditoria_al_generar_y_aprobar()
    {
        [$user, $group] = $this->memberUser();
        $this->mealType();
        $this->recipe();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/generate", $this->generatePayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_generated',
            'entity_name' => 'meal_plans',
        ]);

        $planId = $response->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$planId}/approve")
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_approved',
            'entity_name' => 'meal_plans',
            'entity_id'   => $planId,
        ]);
    }
}
