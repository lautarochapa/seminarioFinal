<?php

namespace Tests\Feature\Api\V1\RecipeSubstitutions;

use App\FamilyGroup;
use App\Ingredient;
use App\IngredientEquivalence;
use App\Recipe;
use App\RecipeIngredient;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeSubstitutionsTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name' => $code, 'type' => 'weight', 'symbol' => $code, 'status' => 'active',
        ]);
    }

    private function ingredient(UnitMeasure $unit, string $suffix = ''): Ingredient
    {
        $name = 'Ing' . $suffix . uniqid();
        return Ingredient::create([
            'name'            => $name,
            'normalized_name' => mb_strtolower($name),
            'base_unit_id'    => $unit->id,
            'is_generic'      => true,
            'is_preparation'  => false,
            'is_supplement'   => false,
            'status'          => 'active',
        ]);
    }

    private function recipe(array $overrides = []): Recipe
    {
        $name = $overrides['name'] ?? ('Receta ' . uniqid());
        return Recipe::create(array_merge([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name),
            'descripcion'     => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '',
            'calorias'        => 0,
            'source_type'     => 'user',
            'status'          => 'active',
            'is_public'       => true,
            'is_official'     => false,
            'is_verified'     => false,
            'servings'        => 2,
        ], $overrides));
    }

    private function addIngredient(Recipe $recipe, Ingredient $ing, UnitMeasure $unit, float $qty): void
    {
        RecipeIngredient::create([
            'recipe_id'    => $recipe->id,
            'ingredient_id'=> $ing->id,
            'unit_id'      => $unit->id,
            'quantity'     => $qty,
            'is_optional'  => false,
            'sort_order'   => 0,
        ]);
    }

    private function equivalence(Ingredient $source, Ingredient $target, float $factor = 1.0, string $status = 'active'): IngredientEquivalence
    {
        return IngredientEquivalence::create([
            'source_ingredient_id' => $source->id,
            'target_ingredient_id' => $target->id,
            'equivalence_type'     => 'substitution',
            'conversion_factor'    => $factor,
            'reason'               => 'Test reason',
            'status'               => $status,
        ]);
    }

    private function familyGroup(User $user): FamilyGroup
    {
        $g = FamilyGroup::create([
            'name' => 'Grupo ' . uniqid(), 'owner_user_id' => $user->id, 'status' => 'active',
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $g->id, 'user_id' => $user->id,
            'role_in_group'   => 'owner', 'status'  => 'active',
            'joined_at'       => now(),   'created_at' => now(), 'updated_at' => now(),
        ]);
        return $g;
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $recipe = $this->recipe();
        $this->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions')->assertStatus(401);
    }

    public function test_receta_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)
            ->getJson('/api/v1/recipes/99999/substitutions')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RECIPE_NOT_FOUND');
    }

    public function test_equivalencias_activas_se_incluyen()
    {
        $user   = factory(User::class)->create();
        $unit   = $this->unit();
        $recipe = $this->recipe();
        $source = $this->ingredient($unit, 'src');
        $target = $this->ingredient($unit, 'tgt');
        $this->addIngredient($recipe, $source, $unit, 200.0);
        $this->equivalence($source, $target, 0.8);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions');
        $response->assertStatus(200);

        $ingRow = collect($response->json('data.ingredients'))
            ->firstWhere('ingredient_id', $source->id);
        $this->assertTrue($ingRow['has_alternatives']);
        $this->assertCount(1, $ingRow['alternatives']);
        $this->assertEquals($target->id, $ingRow['alternatives'][0]['ingredient_id']);
    }

    public function test_equivalencias_inactivas_se_excluyen()
    {
        $user   = factory(User::class)->create();
        $unit   = $this->unit();
        $recipe = $this->recipe();
        $source = $this->ingredient($unit, 'src');
        $target = $this->ingredient($unit, 'tgt');
        $this->addIngredient($recipe, $source, $unit, 100.0);
        $this->equivalence($source, $target, 1.0, 'inactive');

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions');
        $ingRow = collect($response->json('data.ingredients'))->firstWhere('ingredient_id', $source->id);
        $this->assertFalse($ingRow['has_alternatives']);
    }

    public function test_conversion_factor_se_aplica_a_cantidad()
    {
        $user   = factory(User::class)->create();
        $unit   = $this->unit();
        $recipe = $this->recipe();
        $source = $this->ingredient($unit);
        $target = $this->ingredient($unit);
        $this->addIngredient($recipe, $source, $unit, 100.0);
        $this->equivalence($source, $target, 1.5);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions');
        $alt = collect($response->json('data.ingredients'))
            ->firstWhere('ingredient_id', $source->id)['alternatives'][0];

        $this->assertEquals(1.5, $alt['conversion_factor']);
        $this->assertEquals(150.0, $alt['converted_quantity']);
    }

    public function test_exclude_ingredient_ids_omite_sustitutos()
    {
        $user   = factory(User::class)->create();
        $unit   = $this->unit();
        $recipe = $this->recipe();
        $source = $this->ingredient($unit);
        $target = $this->ingredient($unit);
        $this->addIngredient($recipe, $source, $unit, 100.0);
        $this->equivalence($source, $target, 1.0);

        $response = $this->actingAs($user)->getJson(
            '/api/v1/recipes/' . $recipe->id . '/substitutions?exclude_ingredient_ids[]=' . $target->id
        );
        $ingRow = collect($response->json('data.ingredients'))->firstWhere('ingredient_id', $source->id);
        $this->assertFalse($ingRow['has_alternatives']);
    }

    public function test_ingrediente_sin_equivalencias_tiene_has_alternatives_false()
    {
        $user   = factory(User::class)->create();
        $unit   = $this->unit();
        $recipe = $this->recipe();
        $source = $this->ingredient($unit);
        $this->addIngredient($recipe, $source, $unit, 50.0);

        $response = $this->actingAs($user)->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions');
        $ingRow   = collect($response->json('data.ingredients'))->firstWhere('ingredient_id', $source->id);
        $this->assertFalse($ingRow['has_alternatives']);
        $this->assertEmpty($ingRow['alternatives']);
    }

    public function test_grupo_ajeno_retorna_403()
    {
        $owner  = factory(User::class)->create();
        $other  = factory(User::class)->create();
        $group  = $this->familyGroup($owner);
        $recipe = $this->recipe();

        $this->actingAs($other)
            ->getJson('/api/v1/recipes/' . $recipe->id . '/substitutions?family_group_id=' . $group->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }
}
