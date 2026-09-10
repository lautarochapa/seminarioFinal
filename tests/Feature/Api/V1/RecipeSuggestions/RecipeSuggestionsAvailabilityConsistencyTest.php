<?php

namespace Tests\Feature\Api\V1\RecipeSuggestions;

use App\FamilyGroup;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BUG-005: "Que puedo cocinar hoy" (available) y el detalle
 * (GET /recipes/{id}/availability) deben usar la MISMA semantica de
 * disponibilidad. Una receta no puede aparecer en "Para cocinar ahora" si el
 * detalle la bloquea, y viceversa.
 */
class RecipeSuggestionsAvailabilityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code = 'g'): UnitMeasure
    {
        return UnitMeasure::firstOrCreate(['code' => $code], [
            'name' => $code, 'type' => 'weight', 'symbol' => $code, 'status' => 'active',
        ]);
    }

    private function groupWithMember(): array
    {
        $user = factory(User::class)->create();
        $group = FamilyGroup::create(['name' => 'G ' . uniqid(), 'owner_user_id' => $user->id, 'status' => 'active']);
        DB::table('family_group_members')->insert([
            'family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner',
            'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $group];
    }

    private function recipe(int $servings = 2, string $name = null): Recipe
    {
        $name = $name ?: ('Receta ' . uniqid());
        return Recipe::create([
            'name' => $name, 'nombre' => $name, 'normalized_name' => mb_strtolower($name),
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => (string) $servings,
            'calorias' => 0, 'source_type' => 'user', 'status' => 'active',
            'is_public' => true, 'is_official' => false, 'is_verified' => false, 'servings' => $servings,
        ]);
    }

    private function ingredient(UnitMeasure $unit): Ingredient
    {
        $name = 'Ing ' . uniqid();
        return Ingredient::create([
            'name' => $name, 'normalized_name' => mb_strtolower($name), 'base_unit_id' => $unit->id,
            'is_generic' => true, 'is_preparation' => false, 'is_supplement' => false, 'status' => 'active',
        ]);
    }

    private function product(Ingredient $ing, UnitMeasure $unit): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'name' => $name, 'normalized_name' => mb_strtolower($name), 'ingredient_id' => $ing->id,
            'default_unit_id' => $unit->id, 'package_unit_id' => $unit->id, 'net_quantity' => 100,
            'status' => 'active', 'is_active' => true, 'is_verified' => false,
            'nombre' => $name, 'brand_id' => 0, 'codigo' => uniqid(), 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
        ]);
    }

    private function addIngredient(Recipe $r, Ingredient $ing, UnitMeasure $u, float $qty): void
    {
        RecipeIngredient::create([
            'recipe_id' => $r->id, 'ingredient_id' => $ing->id, 'unit_id' => $u->id,
            'quantity' => $qty, 'is_optional' => false, 'sort_order' => 0,
        ]);
    }

    private function stock(FamilyGroup $g, Product $p, UnitMeasure $u, float $qty, ?string $exp = null, ?int $locId = null): StockItem
    {
        return StockItem::create([
            'family_group_id' => $g->id, 'product_id' => $p->id, 'stock_location_id' => $locId,
            'unit_id' => $u->id, 'quantity' => $qty, 'is_open' => false, 'status' => 'active', 'expiration_date' => $exp,
        ]);
    }

    private function location(FamilyGroup $g, string $name): StockLocation
    {
        return StockLocation::create(['family_group_id' => $g->id, 'name' => $name, 'type' => 'pantry', 'status' => 'active']);
    }

    private function availableIds(User $user, FamilyGroup $g): array
    {
        $res = $this->actingAs($user)->getJson("/api/v1/family-groups/{$g->id}/recipes/available")->assertStatus(200);
        return array_map(fn ($r) => (int) $r['id'], $res->json('data'));
    }

    private function availability(User $user, int $recipeId, FamilyGroup $g): array
    {
        return $this->actingAs($user)
            ->getJson("/api/v1/recipes/{$recipeId}/availability?family_group_id={$g->id}")
            ->assertStatus(200)->json('data');
    }

    // ─────────────────────────────────────────────────────────────

    public function test_recipe_short_on_one_ingredient_is_excluded_from_available_and_detail_shows_missing(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(2, 'Tortilla');
        $cebolla = $this->ingredient($u);
        $this->addIngredient($recipe, $cebolla, $u, 100.0);
        $this->stock($g, $this->product($cebolla, $u), $u, 20.0);

        $this->assertNotContains($recipe->id, $this->availableIds($user, $g),
            'Con 20 g de 100 g requeridos, la receta NO debe estar en "Para cocinar ahora".');

        $detail = $this->availability($user, $recipe->id, $g);
        $this->assertFalse($detail['can_cook']);
        $this->assertSame('not_possible', $detail['status']);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $cebolla->id);
        $this->assertEqualsWithDelta(100.0, (float) $row['required_quantity'], 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $row['available_quantity'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $row['missing_quantity'], 0.001);
    }

    public function test_available_membership_matches_detail_can_cook_for_every_candidate(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');

        // Receta A: totalmente cubierta.
        $a = $this->recipe(2, 'Cubierta');
        $ia = $this->ingredient($u);
        $this->addIngredient($a, $ia, $u, 100.0);
        $this->stock($g, $this->product($ia, $u), $u, 500.0);

        // Receta B: un ingrediente corto.
        $b = $this->recipe(2, 'Corta');
        $ib1 = $this->ingredient($u);
        $ib2 = $this->ingredient($u);
        $this->addIngredient($b, $ib1, $u, 100.0);
        $this->addIngredient($b, $ib2, $u, 100.0);
        $this->stock($g, $this->product($ib1, $u), $u, 500.0);
        $this->stock($g, $this->product($ib2, $u), $u, 30.0);

        // Receta C: sin stock de su ingrediente.
        $c = $this->recipe(2, 'SinStock');
        $ic = $this->ingredient($u);
        $this->addIngredient($c, $ic, $u, 50.0);
        $this->product($ic, $u); // producto en catalogo pero sin stock

        $available = $this->availableIds($user, $g);

        foreach ([$a, $b, $c] as $recipe) {
            $canCook = (bool) $this->availability($user, $recipe->id, $g)['can_cook'];
            $inAvailable = in_array($recipe->id, $available, true);
            $this->assertSame(
                $canCook,
                $inAvailable,
                "Receta #{$recipe->id} ({$recipe->name}): available={$inAvailable} pero detalle can_cook=" . var_export($canCook, true)
            );
        }

        $this->assertContains($a->id, $available);
        $this->assertNotContains($b->id, $available);
        $this->assertNotContains($c->id, $available);
    }

    public function test_availability_sums_multiple_valid_lots_and_ignores_expired(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(2, 'MultiLote');
        $cebolla = $this->ingredient($u);
        $this->addIngredient($recipe, $cebolla, $u, 100.0);

        $product = $this->product($cebolla, $u);
        $alacena = $this->location($g, 'Alacena');
        $heladera = $this->location($g, 'Heladera');

        // Dos lotes validos del mismo producto en ubicaciones distintas: 60 + 50 = 110 >= 100.
        $this->stock($g, $product, $u, 60.0, null, $alacena->id);
        $this->stock($g, $product, $u, 50.0, now()->addDays(10)->toDateString(), $heladera->id);
        // Un lote vencido que NO debe contar.
        $this->stock($g, $product, $u, 40.0, now()->subDay()->toDateString(), $alacena->id);

        $detail = $this->availability($user, $recipe->id, $g);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $cebolla->id);
        $this->assertEqualsWithDelta(110.0, (float) $row['available_quantity'], 0.001,
            'Debe sumar los lotes validos (60 + 50) y excluir el vencido (40).');
        $this->assertTrue($detail['can_cook']);
        $this->assertContains($recipe->id, $this->availableIds($user, $g));
    }

    public function test_recipe_with_only_expired_stock_is_not_available(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(2, 'SoloVencido');
        $cebolla = $this->ingredient($u);
        $this->addIngredient($recipe, $cebolla, $u, 100.0);
        $this->stock($g, $this->product($cebolla, $u), $u, 300.0, now()->subDays(2)->toDateString());

        $this->assertNotContains($recipe->id, $this->availableIds($user, $g));
        $detail = $this->availability($user, $recipe->id, $g);
        $this->assertFalse($detail['can_cook']);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $cebolla->id);
        $this->assertEqualsWithDelta(0.0, (float) $row['available_quantity'], 0.001);
    }
}
