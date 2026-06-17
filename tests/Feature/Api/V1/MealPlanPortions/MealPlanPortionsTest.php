<?php

namespace Tests\Feature\Api\V1\MealPlanPortions;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanItem;
use App\MealPlanItemPortion;
use App\MealType;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanPortionsTest extends TestCase
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

    private function addMember(int $groupId): User
    {
        $user = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'user_id'         => $user->id,
            'family_group_id' => $groupId,
            'status'          => 'active',
        ]);
        return $user;
    }

    private function plan(int $groupId, int $userId): MealPlan
    {
        return MealPlan::create([
            'family_group_id' => $groupId,
            'created_by'      => $userId,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'status'          => 'draft',
        ]);
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

    private function item(int $planId, int $mealTypeId): MealPlanItem
    {
        return MealPlanItem::create([
            'meal_plan_id'          => $planId,
            'date'                  => '2026-06-16',
            'meal_type_id'          => $mealTypeId,
            'free_meal_description' => 'Ensalada',
            'is_eating_out'         => false,
            'status'                => 'planned',
        ]);
    }

    private function baseUrl(int $groupId, int $planId, int $itemId): string
    {
        return "/api/v1/family-groups/{$groupId}/meal-plans/{$planId}/items/{$itemId}/portions";
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $group = factory(FamilyGroup::class)->create();
        $this->getJson("/api/v1/family-groups/{$group->id}/meal-plans/1/items/1/portions")
            ->assertStatus(401);
    }

    public function test_no_miembro_retorna_403()
    {
        $user  = factory(User::class)->create();
        [$owner, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $owner->id);
        $item = $this->item($plan->id, $mt->id);

        $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, $plan->id, $item->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_PORTION_GROUP_NOT_FOUND');
    }

    public function test_listar_porciones_del_item()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        MealPlanItemPortion::create([
            'meal_plan_item_id' => $item->id,
            'user_id'           => $user->id,
            'portion_factor'    => 1.0,
        ]);

        $response = $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, $plan->id, $item->id));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $user->id);
    }

    public function test_crear_porcion_para_miembro()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id, $item->id), [
                'user_id'        => $user->id,
                'portion_factor' => 1.5,
                'servings'       => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.portion_factor', '1.5000');

        $this->assertDatabaseHas('meal_plan_item_portions', [
            'meal_plan_item_id' => $item->id,
            'user_id'           => $user->id,
        ]);
    }

    public function test_miembro_invalido_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt     = $this->mealType();
        $plan   = $this->plan($group->id, $user->id);
        $item   = $this->item($plan->id, $mt->id);
        $outsider = factory(User::class)->create();

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id, $item->id), [
                'user_id' => $outsider->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_PORTION_MEMBER_NOT_IN_GROUP');
    }

    public function test_duplicado_mismo_usuario_retorna_409()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        MealPlanItemPortion::create([
            'meal_plan_item_id' => $item->id,
            'user_id'           => $user->id,
            'portion_factor'    => 1.0,
        ]);

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id, $item->id), [
                'user_id' => $user->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_PORTION_DUPLICATE');
    }

    public function test_actualizar_porcion_parcialmente()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        $portion = MealPlanItemPortion::create([
            'meal_plan_item_id' => $item->id,
            'user_id'           => $user->id,
            'portion_factor'    => 1.0,
        ]);

        $response = $this->actingAs($user)
            ->patchJson($this->baseUrl($group->id, $plan->id, $item->id) . "/{$portion->id}", [
                'notes' => 'Sin sal',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'Sin sal')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('meal_plan_item_portions', ['id' => $portion->id, 'notes' => 'Sin sal']);
    }

    public function test_auditoria_al_crear_porcion()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id, $item->id), [
                'user_id' => $user->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_portion_created',
            'entity_name' => 'meal_plan_item_portions',
        ]);
    }
}
