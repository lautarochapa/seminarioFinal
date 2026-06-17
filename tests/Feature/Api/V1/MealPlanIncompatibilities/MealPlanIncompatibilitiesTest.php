<?php

namespace Tests\Feature\Api\V1\MealPlanIncompatibilities;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanIncompatibility;
use App\MealPlanItem;
use App\MealType;
use App\Recipe;
use App\RecipeNutrition;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanIncompatibilitiesTest extends TestCase
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
            'period_type'     => 'daily',
            'start_date'      => '2026-06-17',
            'end_date'        => '2026-06-17',
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

    private function recipe(array $overrides = []): Recipe
    {
        return Recipe::create(array_merge([
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

    private function itemWithRecipe(int $planId, int $mealTypeId, int $recipeId): MealPlanItem
    {
        return MealPlanItem::create([
            'meal_plan_id' => $planId,
            'date'         => '2026-06-17',
            'meal_type_id' => $mealTypeId,
            'recipe_id'    => $recipeId,
            'status'       => 'planned',
        ]);
    }

    private function baseUrl(int $groupId, int $planId): string
    {
        return "/api/v1/family-groups/{$groupId}/meal-plans/{$planId}";
    }

    // --- Tests ---

    public function test_sin_autenticacion_retorna_401()
    {
        $group = factory(FamilyGroup::class)->create();
        $this->getJson("/api/v1/family-groups/{$group->id}/meal-plans/1/incompatibilities")
            ->assertStatus(401);
    }

    public function test_no_miembro_retorna_403()
    {
        $user            = factory(User::class)->create();
        [$owner, $group] = $this->memberUser();
        $plan            = $this->plan($group->id, $owner->id);

        $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, $plan->id) . '/incompatibilities')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_INCOMPATIBILITY_GROUP_NOT_FOUND');
    }

    public function test_plan_inexistente_retorna_404()
    {
        [$user, $group] = $this->memberUser();

        $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, 99999) . '/incompatibilities')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_INCOMPATIBILITY_PLAN_NOT_FOUND');
    }

    public function test_get_devuelve_incompatibilidades_guardadas()
    {
        [$user, $group] = $this->memberUser();
        $mt             = $this->mealType();
        $plan           = $this->plan($group->id, $user->id);
        $rec            = $this->recipe();
        $item           = $this->itemWithRecipe($plan->id, $mt->id, $rec->id);

        MealPlanIncompatibility::create([
            'meal_plan_id'         => $plan->id,
            'meal_plan_item_id'    => $item->id,
            'user_id'              => null,
            'incompatibility_type' => 'allergy',
            'message'              => 'Contiene mani.',
            'severity'             => 'high',
            'status'               => 'open',
        ]);
        MealPlanIncompatibility::create([
            'meal_plan_id'         => $plan->id,
            'meal_plan_item_id'    => $item->id,
            'user_id'              => $user->id,
            'incompatibility_type' => 'restriction',
            'message'              => 'Dieta vegana.',
            'severity'             => 'medium',
            'status'               => 'open',
        ]);

        $response = $this->actingAs($user)
            ->getJson($this->baseUrl($group->id, $plan->id) . '/incompatibilities');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.incompatibility_type', 'allergy')
            ->assertJsonPath('data.1.incompatibility_type', 'restriction');
    }

    public function test_check_detecta_alto_sodio()
    {
        [$user, $group] = $this->memberUser();
        $mt             = $this->mealType();
        $plan           = $this->plan($group->id, $user->id);
        $rec            = $this->recipe();

        RecipeNutrition::create([
            'recipe_id'          => $rec->id,
            'sodium_per_serving' => 750.0,
        ]);

        $item = $this->itemWithRecipe($plan->id, $mt->id, $rec->id);

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id) . '/check-incompatibilities');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.incompatibility_type', 'high_sodium')
            ->assertJsonPath('data.0.severity', 'warning');

        $this->assertDatabaseHas('meal_plan_incompatibilities', [
            'meal_plan_id'         => $plan->id,
            'incompatibility_type' => 'high_sodium',
        ]);
    }

    public function test_check_detecta_alto_azucar()
    {
        [$user, $group] = $this->memberUser();
        $mt             = $this->mealType();
        $plan           = $this->plan($group->id, $user->id);
        $rec            = $this->recipe();

        RecipeNutrition::create([
            'recipe_id'         => $rec->id,
            'sugar_per_serving' => 25.0,
        ]);

        $item = $this->itemWithRecipe($plan->id, $mt->id, $rec->id);

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id) . '/check-incompatibilities');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.incompatibility_type', 'high_sugar');
    }

    public function test_sin_incompatibilidades_con_nutricion_normal()
    {
        [$user, $group] = $this->memberUser();
        $mt             = $this->mealType();
        $plan           = $this->plan($group->id, $user->id);
        $rec            = $this->recipe();

        RecipeNutrition::create([
            'recipe_id'          => $rec->id,
            'sodium_per_serving' => 200.0,
            'sugar_per_serving'  => 5.0,
        ]);

        $this->itemWithRecipe($plan->id, $mt->id, $rec->id);

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id) . '/check-incompatibilities');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_recalculo_reemplaza_resultados_anteriores()
    {
        [$user, $group] = $this->memberUser();
        $mt             = $this->mealType();
        $plan           = $this->plan($group->id, $user->id);
        $rec            = $this->recipe();
        $item           = $this->itemWithRecipe($plan->id, $mt->id, $rec->id);

        MealPlanIncompatibility::create([
            'meal_plan_id'         => $plan->id,
            'meal_plan_item_id'    => $item->id,
            'user_id'              => null,
            'incompatibility_type' => 'old_type',
            'message'              => 'Viejo.',
            'severity'             => 'low',
            'status'               => 'open',
        ]);

        RecipeNutrition::create([
            'recipe_id'          => $rec->id,
            'sodium_per_serving' => 800.0,
        ]);

        $response = $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id) . '/check-incompatibilities');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertDatabaseMissing('meal_plan_incompatibilities', ['incompatibility_type' => 'old_type']);
        $this->assertDatabaseHas('meal_plan_incompatibilities', ['incompatibility_type' => 'high_sodium']);
    }

    public function test_auditoria_al_ejecutar_check()
    {
        [$user, $group] = $this->memberUser();
        $plan           = $this->plan($group->id, $user->id);

        $this->actingAs($user)
            ->postJson($this->baseUrl($group->id, $plan->id) . '/check-incompatibilities')
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_incompatibilities_checked',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
        ]);
    }
}
