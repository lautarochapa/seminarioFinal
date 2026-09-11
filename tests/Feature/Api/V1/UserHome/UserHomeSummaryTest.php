<?php

namespace Tests\Feature\Api\V1\UserHome;

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Product;
use App\Recipe;
use App\RecipeIngredient;
use App\ShoppingList;
use App\ShoppingListItem;
use App\StockItem;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHomeSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function recipeStockScenario(float $requiredQuantity, float $stockQuantity): array
    {
        [$user, $group] = $this->groupWithMember();
        $unit = UnitMeasure::create([
            'code' => 'home_g_'.uniqid(),
            'name' => 'Gramo',
            'type' => 'weight',
            'symbol' => 'g',
            'status' => 'active',
        ]);
        $ingredientName = 'Ingrediente '.uniqid();
        $ingredient = Ingredient::create([
            'name' => $ingredientName,
            'normalized_name' => mb_strtolower($ingredientName),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);
        $productName = 'Producto '.uniqid();
        $product = Product::create([
            'name' => $productName,
            'normalized_name' => mb_strtolower($productName),
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'package_unit_id' => $unit->id,
            'net_quantity' => 100,
            'status' => 'active',
            'is_active' => true,
            'is_verified' => false,
            'nombre' => $productName,
            'brand_id' => 0,
            'codigo' => uniqid(),
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ]);
        $recipeName = 'Receta '.uniqid();
        $recipe = Recipe::create([
            'name' => $recipeName,
            'nombre' => $recipeName,
            'normalized_name' => mb_strtolower($recipeName),
            'descripcion' => '',
            'tiempo' => '',
            'img' => '',
            'video' => '',
            'porcion' => '2',
            'calorias' => 0,
            'source_type' => 'user',
            'status' => 'active',
            'is_public' => true,
            'is_official' => false,
            'is_verified' => false,
            'servings' => 2,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => $requiredQuantity,
            'is_optional' => false,
            'sort_order' => 0,
        ]);
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => $stockQuantity,
            'unit_id' => $unit->id,
            'is_open' => false,
            'status' => 'active',
            'expiration_date' => now()->addMonth()->toDateString(),
        ]);

        return [$user, $group];
    }

    public function test_home_summary_empty_state()
    {
        [$user, $group] = $this->groupWithMember();

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.family_group_id', $group->id)
            ->assertJsonPath('data.stock.products', 0)
            ->assertJsonPath('data.recipes.available', 0);
    }

    public function test_home_summary_includes_pending_shopping_work()
    {
        [$user, $group] = $this->groupWithMember();
        $list = ShoppingList::create([
            'family_group_id' => $group->id,
            'created_by' => $user->id,
            'source_type' => 'manual',
            'status' => 'active',
        ]);
        $unit = UnitMeasure::create([
            'code' => 'home_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
        ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'free_text_name' => 'papel higienico',
            'quantity' => 1,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.shopping.active_lists', 1)
            ->assertJsonPath('data.shopping.pending_items', 1)
            ->assertJsonPath('data.actions.0.type', 'empty_stock');
    }

    public function test_home_recipe_count_is_zero_when_remaining_stock_is_insufficient()
    {
        [$user, $group] = $this->recipeStockScenario(100.0, 20.0);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/recipes/available')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.recipes.available', 0);
    }

    public function test_home_recipe_count_is_one_when_recipe_is_really_available()
    {
        [$user, $group] = $this->recipeStockScenario(100.0, 100.0);

        $this->actingAs($user)
            ->getJson('/api/v1/family-groups/'.$group->id.'/recipes/available')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson('/api/v1/users/me/home-summary?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.recipes.available', 1);
    }
}
