<?php

namespace Tests\Feature\Api\V1\MealPlanItems;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanItemsTest extends TestCase
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

    private function plan(int $groupId, int $userId): MealPlan
    {
        return MealPlan::create([
            'family_group_id' => $groupId,
            'created_by'      => $userId,
            'period_type'     => 'weekly',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-22',
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

    private function recipe(array $overrides = [])
    {
        return \App\Recipe::create(array_merge([
            'nombre'      => 'Receta Test',
            'descripcion' => 'Descripcion',
            'tiempo'      => '30',
            'img'         => '',
            'video'       => '',
            'porcion'     => '2',
            'calorias'    => 200,
            'is_public'   => true,
            'status'      => 'active',
        ], $overrides));
    }

    private function item(int $planId, int $mealTypeId, array $overrides = []): MealPlanItem
    {
        return MealPlanItem::create(array_merge([
            'meal_plan_id'          => $planId,
            'date'                  => '2026-06-16',
            'meal_type_id'          => $mealTypeId,
            'free_meal_description' => 'Ensalada',
            'is_eating_out'         => false,
            'status'                => 'planned',
        ], $overrides));
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $group = factory(FamilyGroup::class)->create();
        $this->getJson("/api/v1/family-groups/{$group->id}/meal-plans/1/items")
            ->assertStatus(401);
    }

    public function test_no_miembro_retorna_403()
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create();
        $plan  = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => null,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'status'          => 'draft',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_GROUP_NOT_FOUND');
    }

    public function test_listar_items_del_plan()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $this->item($plan->id, $mt->id);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items");

        $response->assertStatus(200)
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    public function test_crear_item_con_receta()
    {
        [$user, $group] = $this->memberUser();
        $mt     = $this->mealType();
        $recipe = $this->recipe();
        $plan   = $this->plan($group->id, $user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'          => '2026-06-16',
                'meal_type_id'  => $mt->id,
                'recipe_id'     => $recipe->id,
                'servings_total'=> 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.recipe_id', $recipe->id)
            ->assertJsonPath('data.meal_type_id', $mt->id);

        $this->assertDatabaseHas('meal_plan_items', [
            'meal_plan_id' => $plan->id,
            'recipe_id'    => $recipe->id,
        ]);
    }

    public function test_crear_item_libre()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => '2026-06-16',
                'meal_type_id'         => $mt->id,
                'free_meal_description'=> 'Sandwich casero',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.free_meal_description', 'Sandwich casero');
    }

    public function test_crear_sin_contenido_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'         => '2026-06-16',
                'meal_type_id' => $mt->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_MISSING_CONTENT');
    }

    public function test_actualizar_item_parcialmente()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items/{$item->id}", [
                'notes' => 'Con limon',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'Con limon')
            ->assertJsonPath('data.free_meal_description', 'Ensalada');

        $this->assertDatabaseHas('meal_plan_items', ['id' => $item->id, 'notes' => 'Con limon']);
    }

    public function test_eliminar_item_soft_delete()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $item = $this->item($plan->id, $mt->id);

        $this->actingAs($user)
            ->deleteJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items/{$item->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('meal_plan_items', ['id' => $item->id]);
    }

    public function test_leer_plan_filtra_por_fecha()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $this->item($plan->id, $mt->id, ['date' => '2026-06-16']);
        $this->item($plan->id, $mt->id, ['date' => '2026-06-18']);

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items?date=2026-06-18")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-06-18');
    }

    public function test_servings_total_se_persiste_y_se_devuelve()
    {
        [$user, $group] = $this->memberUser();
        $mt     = $this->mealType();
        $recipe = $this->recipe();
        $plan   = $this->plan($group->id, $user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'           => '2026-06-16',
                'meal_type_id'   => $mt->id,
                'recipe_id'      => $recipe->id,
                'servings_total' => 3.5,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.servings_total', '3.50');
        $this->assertDatabaseHas('meal_plan_items', ['id' => $response->json('data.id'), 'servings_total' => 3.5]);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items/{$response->json('data.id')}", [
                'servings_total' => 6,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.servings_total', '6.00');
    }

    public function test_servings_total_negativo_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'           => '2026-06-16',
                'meal_type_id'   => $mt->id,
                'free_meal_description' => 'x',
                'servings_total' => -2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_fecha_invalida_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => 'no-es-fecha',
                'meal_type_id'         => $mt->id,
                'free_meal_description' => 'x',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_meal_type_inexistente_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => '2026-06-16',
                'meal_type_id'         => 999999,
                'free_meal_description' => 'x',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_MEAL_TYPE_NOT_FOUND');
    }

    public function test_meal_type_inactivo_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $mt->update(['status' => 'inactive']);
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => '2026-06-16',
                'meal_type_id'         => $mt->id,
                'free_meal_description' => 'x',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_MEAL_TYPE_NOT_FOUND');
    }

    public function test_receta_inactiva_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt     = $this->mealType();
        $recipe = $this->recipe(['status' => 'inactive']);
        $plan   = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'         => '2026-06-16',
                'meal_type_id' => $mt->id,
                'recipe_id'    => $recipe->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_RECIPE_NOT_FOUND');
    }

    public function test_duplicado_fecha_y_meal_type_retorna_409()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $this->item($plan->id, $mt->id, ['date' => '2026-06-17']);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => '2026-06-17',
                'meal_type_id'         => $mt->id,
                'free_meal_description' => 'otra comida',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_DUPLICATE');
    }

    public function test_update_a_fecha_meal_type_ocupado_retorna_409()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);
        $this->item($plan->id, $mt->id, ['date' => '2026-06-16']);
        $moving = $this->item($plan->id, $mt->id, ['date' => '2026-06-17']);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items/{$moving->id}", [
                'date' => '2026-06-16',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_DUPLICATE');
    }

    public function test_no_puede_agregar_item_a_plan_de_otro_grupo()
    {
        [$userA, $groupA] = $this->memberUser();
        [$userB, $groupB] = $this->memberUser();
        $mt    = $this->mealType();
        $planB = $this->plan($groupB->id, $userB->id);

        $this->actingAs($userA)
            ->postJson("/api/v1/family-groups/{$groupA->id}/meal-plans/{$planB->id}/items", [
                'date'                 => '2026-06-16',
                'meal_type_id'         => $mt->id,
                'free_meal_description' => 'x',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_PLAN_NOT_FOUND');
    }

    public function test_no_puede_editar_ni_borrar_item_de_plan_de_otro_grupo()
    {
        [$userA, $groupA] = $this->memberUser();
        [$userB, $groupB] = $this->memberUser();
        $mt    = $this->mealType();
        $planB = $this->plan($groupB->id, $userB->id);
        $itemB = $this->item($planB->id, $mt->id);

        $this->actingAs($userA)
            ->patchJson("/api/v1/family-groups/{$groupA->id}/meal-plans/{$planB->id}/items/{$itemB->id}", ['notes' => 'hack'])
            ->assertStatus(404);

        $this->actingAs($userA)
            ->deleteJson("/api/v1/family-groups/{$groupA->id}/meal-plans/{$planB->id}/items/{$itemB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('meal_plan_items', ['id' => $itemB->id, 'notes' => null]);
    }

    public function test_editar_item_inexistente_retorna_404()
    {
        [$user, $group] = $this->memberUser();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items/999999", ['notes' => 'x'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_NOT_FOUND');
    }

    public function test_auditoria_al_crear_item()
    {
        [$user, $group] = $this->memberUser();
        $mt   = $this->mealType();
        $plan = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}/items", [
                'date'                 => '2026-06-16',
                'meal_type_id'         => $mt->id,
                'free_meal_description'=> 'Almuerzo libre',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_item_created',
            'entity_name' => 'meal_plan_items',
        ]);
    }
}
