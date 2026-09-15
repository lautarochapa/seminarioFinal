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

    private function addIngredient(Recipe $r, Ingredient $ing, UnitMeasure $u, float $qty, ?int $specificProductId = null): void
    {
        RecipeIngredient::create([
            'recipe_id' => $r->id, 'ingredient_id' => $ing->id, 'unit_id' => $u->id,
            'quantity' => $qty, 'is_optional' => false, 'sort_order' => 0,
            'specific_product_id' => $specificProductId,
        ]);
    }

    private function conversion(UnitMeasure $from, UnitMeasure $to, float $factor, ?int $ingredientId = null): void
    {
        DB::table('unit_conversions')->insert([
            'from_unit_id' => $from->id, 'to_unit_id' => $to->id, 'ingredient_id' => $ingredientId,
            'factor' => $factor, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function suggestionRows(User $user, FamilyGroup $g): array
    {
        $res = $this->actingAs($user)->getJson("/api/v1/recipes/suggestions?family_group_id={$g->id}&per_page=100")->assertStatus(200);
        $out = [];
        foreach ($res->json('data') as $r) {
            $out[(int) $r['id']] = $r['availability'] ?? null;
        }
        return $out;
    }

    private function expiringRows(User $user, FamilyGroup $g): array
    {
        $res = $this->actingAs($user)->getJson("/api/v1/family-groups/{$g->id}/recipes/by-expiring-stock?per_page=100")->assertStatus(200);
        $out = [];
        foreach ($res->json('data') as $r) {
            $out[(int) $r['id']] = $r['availability'] ?? null;
        }
        return $out;
    }

    private function detailStatus(User $user, int $recipeId, FamilyGroup $g, ?int $servings = null): string
    {
        $qs = "family_group_id={$g->id}" . ($servings ? "&servings={$servings}" : '');
        return $this->actingAs($user)->getJson("/api/v1/recipes/{$recipeId}/availability?{$qs}")
            ->assertStatus(200)->json('data.status');
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

    private function availability(User $user, int $recipeId, FamilyGroup $g, ?int $servings = null): array
    {
        $qs = "family_group_id={$g->id}" . ($servings ? "&servings={$servings}" : '');
        return $this->actingAs($user)
            ->getJson("/api/v1/recipes/{$recipeId}/availability?{$qs}")
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

    public function test_bug005_regresion_suggestions_no_marca_disponible_cuando_falta_cantidad(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(2, 'Tortilla');
        $cebolla = $this->ingredient($u);
        $this->addIngredient($recipe, $cebolla, $u, 100.0);
        $this->stock($g, $this->product($cebolla, $u), $u, 20.0);

        $suggestions = $this->suggestionRows($user, $g);
        $this->assertArrayHasKey($recipe->id, $suggestions);
        $this->assertNotSame('possible', $suggestions[$recipe->id],
            'BUG-005: la receta no puede figurar como completamente disponible en /recipes/suggestions.');
        $this->assertSame($this->detailStatus($user, $recipe->id, $g), $suggestions[$recipe->id]);

        $detail = $this->availability($user, $recipe->id, $g);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $cebolla->id);
        $this->assertEqualsWithDelta(100.0, (float) $row['required_quantity'], 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $row['available_quantity'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $row['missing_quantity'], 0.001);
    }

    public function test_suggestions_y_detalle_coinciden_para_cada_receta_del_grupo(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');

        $cubierta = $this->recipe(2, 'Cubierta');
        $ia = $this->ingredient($u);
        $this->addIngredient($cubierta, $ia, $u, 100.0);
        $this->stock($g, $this->product($ia, $u), $u, 500.0);

        $parcial = $this->recipe(2, 'Parcial');
        $ib = $this->ingredient($u);
        $this->addIngredient($parcial, $ib, $u, 100.0);
        $this->stock($g, $this->product($ib, $u), $u, 60.0);

        $ausente = $this->recipe(2, 'Ausente');
        $ic = $this->ingredient($u);
        $this->addIngredient($ausente, $ic, $u, 50.0);
        $this->product($ic, $u);

        $suggestions = $this->suggestionRows($user, $g);

        foreach ([$cubierta, $parcial, $ausente] as $r) {
            $this->assertSame(
                $this->detailStatus($user, $r->id, $g),
                $suggestions[$r->id] ?? null,
                "Receta #{$r->id} ({$r->name}): suggestions y detalle deben coincidir."
            );
        }

        $this->assertSame('possible', $suggestions[$cubierta->id]);
        $this->assertSame('almost_possible', $suggestions[$parcial->id]);
        $this->assertSame('not_possible', $suggestions[$ausente->id]);
    }

    public function test_by_expiring_stock_usa_la_misma_semantica_que_el_detalle(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');

        $recipe = $this->recipe(2, 'PorVencer');
        $ing = $this->ingredient($u);
        $this->addIngredient($recipe, $ing, $u, 100.0);
        // Stock por vencer pero insuficiente (30 de 100).
        $this->stock($g, $this->product($ing, $u), $u, 30.0, now()->addDays(3)->toDateString());

        $rows = $this->expiringRows($user, $g);
        $this->assertArrayHasKey($recipe->id, $rows);
        $this->assertNotSame('possible', $rows[$recipe->id]);
        $this->assertSame($this->detailStatus($user, $recipe->id, $g), $rows[$recipe->id]);
    }

    public function test_specific_product_id_solo_cuenta_el_producto_indicado(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $ing = $this->ingredient($u);
        $especifico = $this->product($ing, $u);
        $otro       = $this->product($ing, $u);

        $recipe = $this->recipe(2, 'RequiereEspecifico');
        $this->addIngredient($recipe, $ing, $u, 100.0, $especifico->id);

        // Stock abundante del OTRO producto del mismo ingrediente, nada del especifico.
        $this->stock($g, $otro, $u, 999.0);

        $detail = $this->availability($user, $recipe->id, $g);
        $this->assertFalse($detail['can_cook']);
        $this->assertSame('not_possible', $detail['status']);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $ing->id);
        $this->assertSame($especifico->id, $row['specific_product_id']);
        $this->assertEqualsWithDelta(0.0, (float) $row['available_quantity'], 0.001);
        $this->assertSame('not_possible', $this->suggestionRows($user, $g)[$recipe->id] ?? null);

        // Ahora agregamos stock del producto especifico.
        $this->stock($g, $especifico, $u, 300.0);
        $detail2 = $this->availability($user, $recipe->id, $g);
        $this->assertTrue($detail2['can_cook']);
        $this->assertContains($recipe->id, $this->availableIds($user, $g));
        $this->assertSame('possible', $this->suggestionRows($user, $g)[$recipe->id] ?? null);
    }

    public function test_conversion_g_kg_directa_e_inversa(): void
    {
        [$user, $g] = $this->groupWithMember();
        $gr = $this->unit('g');
        $kg = $this->unit('kg');
        $this->conversion($kg, $gr, 1000.0);

        $ing = $this->ingredient($gr);
        $recipe = $this->recipe(2, 'ConversionKg');
        $this->addIngredient($recipe, $ing, $gr, 500.0);
        // Stock cargado en kg: 1 kg = 1000 g >= 500 g.
        $this->stock($g, $this->product($ing, $kg), $kg, 1.0);

        $detail = $this->availability($user, $recipe->id, $g);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $ing->id);
        $this->assertEqualsWithDelta(1000.0, (float) $row['available_quantity'], 0.001);
        $this->assertTrue($detail['can_cook']);
        $this->assertSame('possible', $this->suggestionRows($user, $g)[$recipe->id] ?? null);
    }

    public function test_servings_escala_la_cantidad_requerida(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(4, 'Base4');
        $ing = $this->ingredient($u);
        $this->addIngredient($recipe, $ing, $u, 400.0);
        $this->stock($g, $this->product($ing, $u), $u, 400.0);

        $base = $this->availability($user, $recipe->id, $g);
        $this->assertEqualsWithDelta(400.0, (float) collect($base['ingredients'])->firstWhere('ingredient_id', $ing->id)['required_quantity'], 0.001);
        $this->assertTrue($base['can_cook']);

        $half = $this->availability($user, $recipe->id, $g, 2);
        $this->assertEqualsWithDelta(200.0, (float) collect($half['ingredients'])->firstWhere('ingredient_id', $ing->id)['required_quantity'], 0.001);
        $this->assertTrue($half['can_cook']);

        $double = $this->availability($user, $recipe->id, $g, 8);
        $this->assertEqualsWithDelta(800.0, (float) collect($double['ingredients'])->firstWhere('ingredient_id', $ing->id)['required_quantity'], 0.001);
        $this->assertFalse($double['can_cook']);
        $this->assertSame('almost_possible', $double['status']);
    }

    public function test_grupo_a_no_usa_stock_del_grupo_b(): void
    {
        [$userA, $gA] = $this->groupWithMember();
        [$userB, $gB] = $this->groupWithMember();
        $u = $this->unit('g');

        $recipe = $this->recipe(2, 'AisladaGrupo');
        $ing = $this->ingredient($u);
        $this->addIngredient($recipe, $ing, $u, 100.0);
        $product = $this->product($ing, $u);
        $this->stock($gB, $product, $u, 999.0);

        $this->assertNotContains($recipe->id, $this->availableIds($userA, $gA));
        $this->assertFalse($this->availability($userA, $recipe->id, $gA)['can_cook']);
        $this->assertSame('not_possible', $this->suggestionRows($userA, $gA)[$recipe->id] ?? null);

        $this->assertContains($recipe->id, $this->availableIds($userB, $gB));
    }

    public function test_receta_sin_ingredientes_no_rompe(): void
    {
        [$user, $g] = $this->groupWithMember();
        $recipe = $this->recipe(2, 'SinIngredientes');

        $detail = $this->availability($user, $recipe->id, $g);
        $this->assertSame(0, $detail['required_ingredients_count']);
        $this->assertIsBool($detail['can_cook']);

        $this->suggestionRows($user, $g);
        $this->availableIds($user, $g);
    }

    public function test_mezcla_lote_vencido_y_valido_suma_solo_el_valido_en_ambas_vias(): void
    {
        [$user, $g] = $this->groupWithMember();
        $u = $this->unit('g');
        $recipe = $this->recipe(2, 'MezclaLotes');
        $ing = $this->ingredient($u);
        $this->addIngredient($recipe, $ing, $u, 100.0);
        $product = $this->product($ing, $u);
        $this->stock($g, $product, $u, 70.0, now()->addDays(20)->toDateString());
        $this->stock($g, $product, $u, 90.0, now()->subDays(1)->toDateString());

        $detail = $this->availability($user, $recipe->id, $g);
        $row = collect($detail['ingredients'])->firstWhere('ingredient_id', $ing->id);
        $this->assertEqualsWithDelta(70.0, (float) $row['available_quantity'], 0.001);
        $this->assertFalse($detail['can_cook']);
        $this->assertSame($detail['status'], $this->suggestionRows($user, $g)[$recipe->id] ?? null);
    }
}
