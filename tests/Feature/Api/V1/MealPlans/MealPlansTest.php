<?php

namespace Tests\Feature\Api\V1\MealPlans;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlansTest extends TestCase
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

    private function mealType(array $overrides = []): MealType
    {
        return MealType::create(array_merge([
            'code'       => 'almuerzo_' . uniqid(),
            'name'       => 'Almuerzo',
            'sort_order' => 1,
            'status'     => 'active',
        ], $overrides));
    }

    private function validPlanPayload(int $mealTypeId): array
    {
        return [
            'period_type' => 'weekly',
            'start_date'  => '2026-06-16',
            'end_date'    => '2026-06-22',
            'items'       => [
                [
                    'date'                 => '2026-06-16',
                    'meal_type_id'         => $mealTypeId,
                    'free_meal_description'=> 'Ensalada mixta',
                ],
            ],
        ];
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $group = factory(FamilyGroup::class)->create();
        $this->getJson("/api/v1/family-groups/{$group->id}/meal-plans")
            ->assertStatus(401);
    }

    public function test_no_miembro_retorna_403()
    {
        $user  = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEAL_PLAN_GROUP_NOT_FOUND');
    }

    public function test_listar_planes_del_grupo()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'weekly',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-22',
            'status'          => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_crear_plan_con_item_libre()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans", $this->validPlanPayload($mt->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.period_type', 'weekly')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.family_group_id', $group->id);

        $this->assertDatabaseHas('meal_plans', [
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
        ]);
    }

    public function test_item_sin_contenido_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        $payload = [
            'period_type' => 'weekly',
            'start_date'  => '2026-06-16',
            'end_date'    => '2026-06-22',
            'items'       => [
                [
                    'date'         => '2026-06-16',
                    'meal_type_id' => $mt->id,
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_ITEM_MISSING_CONTENT');
    }

    public function test_tipo_comida_inactivo_retorna_422()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType(['status' => 'inactive', 'code' => 'inact_' . uniqid()]);

        $payload = $this->validPlanPayload($mt->id);

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEAL_PLAN_MEAL_TYPE_NOT_FOUND');
    }

    public function test_ver_plan_individual()
    {
        [$user, $group] = $this->memberUser();

        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'status'          => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_plan_inexistente_retorna_404()
    {
        [$user, $group] = $this->memberUser();

        $this->actingAs($user)
            ->getJson("/api/v1/family-groups/{$group->id}/meal-plans/99999")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEAL_PLAN_NOT_FOUND');
    }

    public function test_actualizar_plan_parcialmente()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'weekly',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-22',
            'status'          => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}", [
                'period_type' => 'monthly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.period_type', 'monthly');

        $this->assertDatabaseHas('meal_plans', ['id' => $plan->id, 'period_type' => 'monthly']);
    }

    public function test_eliminar_plan_aplica_soft_delete()
    {
        [$user, $group] = $this->memberUser();

        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'daily',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-16',
            'status'          => 'draft',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('meal_plans', ['id' => $plan->id]);
    }

    public function test_actualizar_items_reemplaza_los_existentes()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        $plan = MealPlan::create([
            'family_group_id' => $group->id,
            'created_by'      => $user->id,
            'period_type'     => 'weekly',
            'start_date'      => '2026-06-16',
            'end_date'        => '2026-06-22',
            'status'          => 'draft',
        ]);
        MealPlanItem::create([
            'meal_plan_id'          => $plan->id,
            'date'                  => '2026-06-16',
            'meal_type_id'          => $mt->id,
            'free_meal_description' => 'Original',
            'is_eating_out'         => false,
            'status'                => 'planned',
        ]);

        $newMt = $this->mealType(['code' => 'cena_' . uniqid(), 'name' => 'Cena']);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/family-groups/{$group->id}/meal-plans/{$plan->id}", [
                'items' => [
                    [
                        'date'                 => '2026-06-17',
                        'meal_type_id'         => $newMt->id,
                        'free_meal_description'=> 'Nuevo item',
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $activeItems = MealPlanItem::where('meal_plan_id', $plan->id)->whereNull('deleted_at')->get();
        $this->assertCount(1, $activeItems);
        $this->assertEquals('Nuevo item', $activeItems->first()->free_meal_description);
    }

    public function test_auditoria_al_crear_plan()
    {
        [$user, $group] = $this->memberUser();
        $mt = $this->mealType();

        $this->actingAs($user)
            ->postJson("/api/v1/family-groups/{$group->id}/meal-plans", $this->validPlanPayload($mt->id))
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'meal_plan_created',
            'entity_name' => 'meal_plans',
        ]);
    }
}
