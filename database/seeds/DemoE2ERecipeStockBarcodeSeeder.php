<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoE2ERecipeStockBarcodeSeeder extends Seeder
{
    private $now;

    public function run()
    {
        $this->now = now();

        $units = $this->seedUnits();
        $brandId = $this->upsertBrand('Demo E2E', 'demo e2e');
        $categoryId = $this->upsertProductCategory('Demo almacen');
        $recipeCategoryId = $this->upsertRecipeCategory('Demo recetas');
        $userId = $this->upsertUser();
        $groupId = $this->upsertFamilyGroup($userId);
        $locationId = $this->upsertStockLocation($groupId);

        $ingredients = $this->seedIngredients($units);
        $products = $this->seedProducts($ingredients, $units, $brandId, $categoryId);
        $this->seedStock($groupId, $locationId, $products, $units);
        $this->seedRecipes($userId, $recipeCategoryId, $ingredients, $units);
        $this->seedShoppingList($groupId, $userId, $ingredients, $products, $units);

        $this->command->info('Demo E2E recipe/stock/barcode scenario seeded.');
    }

    private function seedUnits()
    {
        $defs = [
            'g' => ['name' => 'Gramo', 'type' => 'weight', 'symbol' => 'g'],
            'kg' => ['name' => 'Kilogramo', 'type' => 'weight', 'symbol' => 'kg'],
            'ml' => ['name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml'],
            'l' => ['name' => 'Litro', 'type' => 'volume', 'symbol' => 'L'],
            'unit' => ['name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u'],
        ];

        $ids = [];
        foreach ($defs as $code => $def) {
            DB::table('unit_measures')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'symbol' => $def['symbol'],
                    'status' => 'active',
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );
            $ids[$code] = DB::table('unit_measures')->where('code', $code)->value('id');
        }

        $this->upsertConversion($ids['kg'], $ids['g'], null, 1000);
        $this->upsertConversion($ids['l'], $ids['ml'], null, 1000);

        return $ids;
    }

    private function upsertConversion($fromId, $toId, $ingredientId, $factor)
    {
        DB::table('unit_conversions')->updateOrInsert(
            ['from_unit_id' => $fromId, 'to_unit_id' => $toId, 'ingredient_id' => $ingredientId],
            [
                'factor' => $factor,
                'notes' => 'Demo E2E',
                'status' => 'active',
                'updated_at' => $this->now,
                'created_at' => $this->now,
            ]
        );
    }

    private function upsertUser()
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'demo.e2e@cccontrol.test'],
            [
                'name' => 'Demo',
                'lastname' => 'E2E',
                'username' => 'demo_e2e',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'nivel_acceso' => 3,
                'email_verified_at' => $this->now,
                'updated_at' => $this->now,
                'created_at' => $this->now,
            ]
        );

        $userId = DB::table('users')->where('email', 'demo.e2e@cccontrol.test')->value('id');
        $roleId = DB::table('roles')->where('code', 'user')->value('id');
        if ($roleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $roleId],
                ['created_at' => $this->now]
            );
        }

        return $userId;
    }

    private function upsertFamilyGroup($userId)
    {
        DB::table('family_groups')->updateOrInsert(
            ['name' => 'Familia Demo E2E', 'owner_user_id' => $userId],
            [
                'status' => 'active',
                'updated_at' => $this->now,
                'created_at' => $this->now,
            ]
        );

        $groupId = DB::table('family_groups')
            ->where('name', 'Familia Demo E2E')
            ->where('owner_user_id', $userId)
            ->value('id');

        DB::table('family_group_members')->updateOrInsert(
            ['family_group_id' => $groupId, 'user_id' => $userId],
            [
                'role_in_group' => 'owner',
                'status' => 'active',
                'joined_at' => $this->now,
                'updated_at' => $this->now,
                'created_at' => $this->now,
            ]
        );

        return $groupId;
    }

    private function upsertStockLocation($groupId)
    {
        DB::table('stock_locations')->updateOrInsert(
            ['family_group_id' => $groupId, 'name' => 'Alacena demo'],
            ['type' => 'pantry', 'status' => 'active', 'updated_at' => $this->now, 'created_at' => $this->now]
        );

        return DB::table('stock_locations')
            ->where('family_group_id', $groupId)
            ->where('name', 'Alacena demo')
            ->value('id');
    }

    private function seedIngredients(array $units)
    {
        $defs = [
            'harina comun' => ['Harina comun', $units['g']],
            'leche entera' => ['Leche entera', $units['ml']],
            'huevo' => ['Huevo', $units['unit']],
            'azucar' => ['Azucar', $units['g']],
            'aceite' => ['Aceite', $units['ml']],
            'pollo' => ['Pollo', $units['g']],
            'arroz' => ['Arroz', $units['g']],
            'tomate' => ['Tomate', $units['g']],
            'cebolla' => ['Cebolla', $units['g']],
            'cacao' => ['Cacao', $units['g']],
        ];

        $ids = [];
        foreach ($defs as $normalized => $def) {
            DB::table('ingredients')->updateOrInsert(
                ['normalized_name' => $normalized],
                [
                    'name' => $def[0],
                    'base_unit_id' => $def[1],
                    'is_generic' => true,
                    'is_preparation' => false,
                    'is_supplement' => false,
                    'status' => 'active',
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );
            $ids[$normalized] = DB::table('ingredients')->where('normalized_name', $normalized)->value('id');
        }

        return $ids;
    }

    private function seedProducts(array $ingredients, array $units, $brandId, $categoryId)
    {
        $defs = [
            'harina comun 1 kg demo' => ['Harina comun 1 kg', $ingredients['harina comun'], $units['g'], 1000, $units['kg'], '7790000000011'],
            'leche entera 1 l demo' => ['Leche entera 1 L', $ingredients['leche entera'], $units['ml'], 1000, $units['l'], '7790000000028'],
            'huevos por 6 demo' => ['Huevos por 6 unidades', $ingredients['huevo'], $units['unit'], 6, $units['unit'], '7790000000035'],
            'azucar 1 kg demo' => ['Azucar 1 kg', $ingredients['azucar'], $units['g'], 1000, $units['kg'], '7790000000042'],
            'aceite 900 ml demo' => ['Aceite 900 ml', $ingredients['aceite'], $units['ml'], 900, $units['ml'], '7790000000059'],
            'cacao conocido sin stock demo' => ['Cacao conocido sin stock', $ingredients['cacao'], $units['g'], 180, $units['g'], '7790000000066'],
        ];

        $ids = [];
        foreach ($defs as $normalized => $def) {
            DB::table('products')->updateOrInsert(
                ['normalized_name' => $normalized],
                [
                    'name' => $def[0],
                    'nombre' => $def[0],
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'ingredient_id' => $def[1],
                    'default_unit_id' => $def[2],
                    'net_quantity' => $def[3],
                    'package_unit_id' => $def[4],
                    'description' => 'Producto demo E2E',
                    'is_verified' => true,
                    'is_active' => true,
                    'status' => 'active',
                    'origin' => 'catalog',
                    'codigo' => $def[5],
                    'img' => '',
                    'habilitado' => 1,
                    'supply_id' => 0,
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );

            $productId = DB::table('products')->where('normalized_name', $normalized)->value('id');
            $ids[$normalized] = $productId;

            DB::table('product_barcodes')->updateOrInsert(
                ['barcode' => $def[5], 'type' => 'ean13'],
                ['product_id' => $productId, 'status' => 'active', 'updated_at' => $this->now, 'created_at' => $this->now]
            );
        }

        return $ids;
    }

    private function seedStock($groupId, $locationId, array $products, array $units)
    {
        $defs = [
            [$products['harina comun 1 kg demo'], 1000, $units['g'], '2026-12-31'],
            [$products['leche entera 1 l demo'], 1000, $units['ml'], '2026-08-15'],
            [$products['huevos por 6 demo'], 6, $units['unit'], '2026-08-01'],
            [$products['azucar 1 kg demo'], 500, $units['g'], '2027-01-31'],
            [$products['aceite 900 ml demo'], 500, $units['ml'], '2027-02-28'],
        ];

        foreach ($defs as $def) {
            DB::table('stock_items')->updateOrInsert(
                ['family_group_id' => $groupId, 'product_id' => $def[0], 'stock_location_id' => $locationId],
                [
                    'quantity' => $def[1],
                    'unit_id' => $def[2],
                    'expiration_date' => $def[3],
                    'is_open' => false,
                    'status' => 'active',
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );
        }
    }

    private function seedRecipes($userId, $categoryId, array $ingredients, array $units)
    {
        $recipes = [
            ['Panqueques', 'panqueques', [['harina comun', 200, 'g'], ['leche entera', 500, 'ml'], ['huevo', 2, 'unit']]],
            ['Pollo con arroz', 'pollo arroz', [['pollo', 400, 'g'], ['arroz', 250, 'g'], ['cebolla', 80, 'g']]],
            ['Tortilla de huevo', 'huevo tortilla', [['huevo', 4, 'unit'], ['cebolla', 100, 'g']]],
            ['Arroz con leche', 'arroz leche', [['arroz', 180, 'g'], ['leche entera', 600, 'ml'], ['azucar', 80, 'g']]],
            ['Bizcochuelo simple', 'bizcochuelo harina huevo', [['harina comun', 250, 'g'], ['huevo', 3, 'unit'], ['azucar', 180, 'g']]],
            ['Pollo al horno', 'pollo horno', [['pollo', 600, 'g'], ['aceite', 30, 'ml']]],
            ['Tomates rellenos', 'tomate arroz', [['tomate', 400, 'g'], ['arroz', 120, 'g']]],
            ['Cebolla salteada', 'cebolla aceite', [['cebolla', 250, 'g'], ['aceite', 20, 'ml']]],
            ['Crepes dulces', 'crepes panqueques', [['harina comun', 180, 'g'], ['leche entera', 400, 'ml'], ['huevo', 2, 'unit'], ['azucar', 40, 'g']]],
            ['Pollo rebozado', 'pollo huevo harina', [['pollo', 500, 'g'], ['huevo', 2, 'unit'], ['harina comun', 120, 'g']]],
        ];

        foreach ($recipes as $index => $recipeDef) {
            $name = $recipeDef[0];
            $normalized = $recipeDef[1];

            DB::table('recipes')->updateOrInsert(
                ['normalized_name' => $normalized],
                [
                    'name' => $name,
                    'nombre' => $name,
                    'description' => 'Receta demo E2E',
                    'descripcion' => 'Receta demo E2E',
                    'tiempo' => '',
                    'img' => '',
                    'video' => '',
                    'porcion' => '',
                    'calorias' => 0,
                    'source_type' => 'official',
                    'owner_user_id' => $userId,
                    'is_public' => true,
                    'is_official' => true,
                    'is_verified' => true,
                    'status' => 'active',
                    'servings' => 2,
                    'prep_time_minutes' => 10 + $index,
                    'cook_time_minutes' => 15 + $index,
                    'difficulty' => $index % 3 === 0 ? 'easy' : ($index % 3 === 1 ? 'medium' : 'hard'),
                    'category_id' => $categoryId,
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );

            $recipeId = DB::table('recipes')->where('normalized_name', $normalized)->value('id');
            DB::table('recipe_ingredients')->where('recipe_id', $recipeId)->delete();

            foreach ($recipeDef[2] as $sort => $ingredientDef) {
                DB::table('recipe_ingredients')->insert([
                    'recipe_id' => $recipeId,
                    'ingredient_id' => $ingredients[$ingredientDef[0]],
                    'quantity' => $ingredientDef[1],
                    'unit_id' => $units[$ingredientDef[2]],
                    'is_optional' => false,
                    'sort_order' => $sort,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }
    }

    private function seedShoppingList($groupId, $userId, array $ingredients, array $products, array $units)
    {
        DB::table('shopping_lists')->updateOrInsert(
            ['family_group_id' => $groupId, 'created_by' => $userId, 'source_type' => 'manual', 'status' => 'active'],
            ['optimization_mode' => 'simple', 'updated_at' => $this->now, 'created_at' => $this->now]
        );

        $listId = DB::table('shopping_lists')
            ->where('family_group_id', $groupId)
            ->where('created_by', $userId)
            ->where('source_type', 'manual')
            ->where('status', 'active')
            ->value('id');

        $items = [
            ['free_text_name' => 'detergente', 'notes' => 'Articulo libre demo', 'status' => 'pending'],
            ['free_text_name' => 'papel higienico', 'notes' => null, 'status' => 'pending'],
            ['ingredient_id' => $ingredients['tomate'], 'quantity' => 500, 'unit_id' => $units['g'], 'status' => 'pending'],
            ['product_id' => $products['cacao conocido sin stock demo'], 'quantity' => 1, 'unit_id' => $units['unit'], 'status' => 'purchased'],
        ];

        foreach ($items as $index => $item) {
            DB::table('shopping_list_items')->updateOrInsert(
                [
                    'shopping_list_id' => $listId,
                    'free_text_name' => $item['free_text_name'] ?? null,
                    'ingredient_id' => $item['ingredient_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                ],
                array_merge([
                    'quantity' => $item['quantity'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'sort_order' => $index,
                    'estimated_price' => null,
                    'actual_price' => null,
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ], $item)
            );
        }
    }

    private function upsertBrand($name, $normalized)
    {
        DB::table('brands')->updateOrInsert(
            ['normalized_name' => $normalized],
            ['name' => $name, 'nombre' => $name, 'status' => 'active', 'padre' => 0, 'updated_at' => $this->now, 'created_at' => $this->now]
        );

        return DB::table('brands')->where('normalized_name', $normalized)->value('id');
    }

    private function upsertProductCategory($name)
    {
        DB::table('product_categories')->updateOrInsert(
            ['name' => $name],
            ['description' => 'Demo E2E', 'status' => 'active', 'updated_at' => $this->now, 'created_at' => $this->now]
        );

        return DB::table('product_categories')->where('name', $name)->value('id');
    }

    private function upsertRecipeCategory($name)
    {
        DB::table('recipe_categories')->updateOrInsert(
            ['name' => $name],
            ['description' => 'Demo E2E', 'status' => 'active', 'updated_at' => $this->now, 'created_at' => $this->now]
        );

        return DB::table('recipe_categories')->where('name', $name)->value('id');
    }
}
