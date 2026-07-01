<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        $userUser       = DB::table('users')->where('email', 'usuario@cccontrol.test')->first();
        $dietUser       = DB::table('users')->where('email', 'dietologo@cccontrol.test')->first();
        $recipeUser     = DB::table('users')->where('email', 'recetas@cccontrol.test')->first();
        $catalogUser    = DB::table('users')->where('email', 'catalogo@cccontrol.test')->first();
        $superadminUser = DB::table('users')->where('email', 'superadmin@cccontrol.test')->first();
        $adminUser      = DB::table('users')->where('email', 'admin@cccontrol.test')->first();

        if (!$userUser) {
            $this->command->warn('Demo users not found. Run migrations first (2026_06_15_000018).');
            return;
        }

        $this->command->info('Seeding demo family group...');
        $fgId = $this->seedFamilyGroup($userUser, $dietUser, $superadminUser, $adminUser, $now);

        $this->command->info('Seeding user profiles...');
        $this->seedUserProfiles([$userUser, $dietUser, $recipeUser, $catalogUser], $now);

        $this->command->info('Seeding professional link...');
        $this->seedProfessionalLink($userUser, $dietUser, $now);

        $this->command->info('Seeding demo ingredients...');
        $units       = $this->getUnits();
        $nutrients   = $this->getNutrients();
        $ingredients = $this->seedIngredients($units, $nutrients, $now);

        $this->command->info('Seeding demo products...');
        $products = $this->seedProducts($units, $ingredients, $now);

        $this->command->info('Seeding demo recipes...');
        $this->seedRecipes($ingredients, $units, $userUser, $recipeUser, $now);

        $this->command->info('Seeding demo stock...');
        $this->seedStock($fgId, $products, $units, $now);

        $this->command->info('Seeding demo shopping list...');
        $this->seedShoppingList($fgId, $ingredients, $products, $units, $userUser, $now);

        $this->command->info('Seeding demo budget...');
        $this->seedBudget($fgId, $now);

        $this->command->info('Seeding demo supermarket data...');
        $this->seedSupermarketData($products, $now);

        $this->command->info('Seeding demo promotions and payment methods...');
        $this->seedPromotionsAndPaymentMethods($userUser, $products, $now);

        $this->command->info('Seeding demo scraping data...');
        $this->seedScrapingData($userUser, $products, $now);

        $this->command->info('Seeding admin user...');
        $this->seedAdminUser($now);

        $this->command->info('Seeding thesis documents...');
        $this->seedThesisDocuments($superadminUser, $now);

        $this->command->info('Demo data seeded successfully.');
    }

    // ── Admin user (admin@cccontrol.test) ───────────────────────────────────

    private function seedAdminUser($now)
    {
        $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');
        if (!$roleId) {
            $this->command->warn('Role super_admin not found; skipping admin user.');
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@cccontrol.test'],
            [
                'name'              => 'Admin',
                'lastname'          => 'Test',
                'username'          => 'admin',
                'password'          => \Illuminate\Support\Facades\Hash::make('password123'),
                'status'            => 'active',
                'nivel_acceso'      => 1,
                'email_verified_at' => $now,
                'updated_at'        => $now,
                'created_at'        => $now,
                'deleted_at'        => null,
            ]
        );

        $adminId = DB::table('users')->where('email', 'admin@cccontrol.test')->value('id');
        if (!$adminId) {
            return;
        }

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $adminId, 'role_id' => $roleId],
            ['created_at' => $now]
        );
    }

    // ── Family Group ────────────────────────────────────────────────────────

    private function seedFamilyGroup($userUser, $dietUser, $superadminUser, $adminUser, $now)
    {
        $existing = DB::table('family_groups')
            ->where('owner_user_id', $userUser->id)
            ->where('name', 'Familia Demo')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $cityId = DB::table('cities')->value('id');

        $fgId = DB::table('family_groups')->insertGetId([
            'name'              => 'Familia Demo',
            'owner_user_id'     => $userUser->id,
            'city_id'           => $cityId,
            'default_address'   => 'Mitre 1234, Bariloche',
            'default_latitude'  => -41.1335,
            'default_longitude' => -71.3103,
            'status'            => 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // Preferences
        DB::table('family_group_preferences')->updateOrInsert(
            ['family_group_id' => $fgId],
            [
                'family_group_id'              => $fgId,
                'default_budget_mode'          => 'monthly',
                'default_shopping_mode'        => 'cost',
                'default_recipe_priority_mode' => 'balanced',
                'allow_auto_stock_discount'    => true,
                'created_at'                   => $now,
                'updated_at'                   => $now,
            ]
        );

        // Owner member
        DB::table('family_group_members')->updateOrInsert(
            ['family_group_id' => $fgId, 'user_id' => $userUser->id],
            [
                'family_group_id' => $fgId,
                'user_id'         => $userUser->id,
                'role_in_group'   => 'owner',
                'status'          => 'active',
                'joined_at'       => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        // Dietologist as member
        if ($dietUser) {
            DB::table('family_group_members')->updateOrInsert(
                ['family_group_id' => $fgId, 'user_id' => $dietUser->id],
                [
                    'family_group_id' => $fgId,
                    'user_id'         => $dietUser->id,
                    'role_in_group'   => 'member',
                    'status'          => 'active',
                    'joined_at'       => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]
            );
        }

        // Superadmin and admin as members (so they can see stock/shopping data in user screens)
        foreach ([$superadminUser, $adminUser] as $extraUser) {
            if ($extraUser) {
                DB::table('family_group_members')->updateOrInsert(
                    ['family_group_id' => $fgId, 'user_id' => $extraUser->id],
                    [
                        'family_group_id' => $fgId,
                        'user_id'         => $extraUser->id,
                        'role_in_group'   => 'member',
                        'status'          => 'active',
                        'joined_at'       => $now,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]
                );
            }
        }

        return $fgId;
    }

    // ── User Profiles ────────────────────────────────────────────────────────

    private function seedUserProfiles(array $users, $now)
    {
        $profiles = [
            'usuario@cccontrol.test' => [
                'birth_date'                => '1992-05-15',
                'gender'                    => 'male',
                'height_cm'                 => 178,
                'current_weight_kg'         => 80.5,
                'target_weight_kg'          => 75.0,
                'activity_level'            => 'moderate',
                'meals_per_day'             => 4,
                'uses_app_for_health'       => true,
                'uses_app_for_budget'       => true,
                'uses_app_for_organization' => true,
            ],
            'dietologo@cccontrol.test' => [
                'birth_date'                => '1985-09-20',
                'gender'                    => 'female',
                'height_cm'                 => 165,
                'current_weight_kg'         => 62.0,
                'target_weight_kg'          => 60.0,
                'activity_level'            => 'active',
                'meals_per_day'             => 5,
                'uses_app_for_health'       => true,
                'uses_app_for_budget'       => false,
                'uses_app_for_organization' => true,
            ],
        ];

        foreach ($users as $user) {
            if (!$user) {
                continue;
            }

            $exists = DB::table('user_profiles')->where('user_id', $user->id)->exists();
            if ($exists) {
                continue;
            }

            $data = $profiles[$user->email] ?? [];

            DB::table('user_profiles')->insert(array_merge([
                'user_id'                   => $user->id,
                'birth_date'                => null,
                'gender'                    => null,
                'height_cm'                 => null,
                'current_weight_kg'         => null,
                'target_weight_kg'          => null,
                'activity_level'            => null,
                'meals_per_day'             => null,
                'uses_app_for_health'       => false,
                'uses_app_for_budget'       => false,
                'uses_app_for_organization' => false,
                'notes'                     => null,
                'created_at'                => $now,
                'updated_at'                => $now,
            ], $data));
        }
    }

    // ── Professional Link ────────────────────────────────────────────────────

    private function seedProfessionalLink($userUser, $dietUser, $now)
    {
        if (!$dietUser) {
            return;
        }

        DB::table('professional_user_links')->updateOrInsert(
            ['user_id' => $userUser->id, 'professional_user_id' => $dietUser->id],
            [
                'user_id'              => $userUser->id,
                'professional_user_id' => $dietUser->id,
                'can_view_profile'     => true,
                'can_view_stock'       => true,
                'can_view_meal_plans'  => true,
                'can_edit_meal_plans'  => true,
                'can_view_reports'     => true,
                'status'               => 'active',
                'granted_at'           => $now,
                'revoked_at'           => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]
        );
    }

    // ── Unit and Nutrient helpers ────────────────────────────────────────────

    private function getUnits()
    {
        return DB::table('unit_measures')->pluck('id', 'code')->toArray();
    }

    private function getNutrients()
    {
        return DB::table('nutrients')->pluck('id', 'code')->toArray();
    }

    // ── Ingredients ──────────────────────────────────────────────────────────

    private function seedIngredients(array $units, array $nutrients, $now)
    {
        $gId    = $units['g']    ?? null;
        $mlId   = $units['ml']   ?? null;
        $unitId = $units['unit'] ?? null;

        // Look up a category
        $cerealCatId  = DB::table('ingredient_categories')->where('name', 'ilike', '%cereal%')->value('id');
        $lacteoCatId  = DB::table('ingredient_categories')->where('name', 'ilike', '%lácte%')->orWhere('name', 'ilike', '%lacte%')->value('id');
        $verduraCatId = DB::table('ingredient_categories')->where('name', 'ilike', '%verdura%')->orWhere('name', 'ilike', '%hortaliz%')->value('id');
        $carneCatId   = DB::table('ingredient_categories')->where('name', 'ilike', '%carne%')->orWhere('name', 'ilike', '%aviar%')->value('id');

        $defs = [
            [
                'name'            => 'Harina de trigo 000',
                'normalized_name' => 'harina de trigo 000',
                'category_id'     => $cerealCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 364, 'protein' => 10.3, 'carbohydrates' => 76.3, 'fat' => 1.0, 'sodium' => 2, 'sugar' => 0.3, 'fiber' => 2.7],
            ],
            [
                'name'            => 'Azúcar refinada',
                'normalized_name' => 'azucar refinada',
                'category_id'     => null,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 387, 'protein' => 0, 'carbohydrates' => 99.8, 'fat' => 0, 'sodium' => 1, 'sugar' => 99.8, 'fiber' => 0],
            ],
            [
                'name'            => 'Huevo de gallina',
                'normalized_name' => 'huevo de gallina',
                'category_id'     => null,
                'base_unit_id'    => $unitId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 143, 'protein' => 12.6, 'carbohydrates' => 0.7, 'fat' => 9.5, 'sodium' => 142, 'sugar' => 0.4, 'fiber' => 0],
            ],
            [
                'name'            => 'Leche entera',
                'normalized_name' => 'leche entera',
                'category_id'     => $lacteoCatId,
                'base_unit_id'    => $mlId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 61, 'protein' => 3.2, 'carbohydrates' => 4.8, 'fat' => 3.3, 'sodium' => 44, 'sugar' => 4.8, 'fiber' => 0],
            ],
            [
                'name'            => 'Aceite de girasol',
                'normalized_name' => 'aceite de girasol',
                'category_id'     => null,
                'base_unit_id'    => $mlId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 884, 'protein' => 0, 'carbohydrates' => 0, 'fat' => 100, 'sodium' => 0, 'sugar' => 0, 'fiber' => 0],
            ],
            [
                'name'            => 'Tomate fresco',
                'normalized_name' => 'tomate fresco',
                'category_id'     => $verduraCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 18, 'protein' => 0.9, 'carbohydrates' => 3.9, 'fat' => 0.2, 'sodium' => 5, 'sugar' => 2.6, 'fiber' => 1.2],
            ],
            [
                'name'            => 'Cebolla',
                'normalized_name' => 'cebolla',
                'category_id'     => $verduraCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 40, 'protein' => 1.1, 'carbohydrates' => 9.3, 'fat' => 0.1, 'sodium' => 4, 'sugar' => 4.2, 'fiber' => 1.7],
            ],
            [
                'name'            => 'Papa',
                'normalized_name' => 'papa',
                'category_id'     => $verduraCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 77, 'protein' => 2.0, 'carbohydrates' => 17.5, 'fat' => 0.1, 'sodium' => 6, 'sugar' => 0.8, 'fiber' => 2.2],
            ],
            [
                'name'            => 'Pechuga de pollo',
                'normalized_name' => 'pechuga de pollo',
                'category_id'     => $carneCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 165, 'protein' => 31.0, 'carbohydrates' => 0, 'fat' => 3.6, 'sodium' => 74, 'sugar' => 0, 'fiber' => 0],
            ],
            [
                'name'            => 'Arroz blanco',
                'normalized_name' => 'arroz blanco',
                'category_id'     => $cerealCatId,
                'base_unit_id'    => $gId,
                'is_generic'      => true,
                'nutrients'       => ['calories' => 130, 'protein' => 2.7, 'carbohydrates' => 28.2, 'fat' => 0.3, 'sodium' => 1, 'sugar' => 0, 'fiber' => 0.4],
            ],
        ];

        $ids = [];

        foreach ($defs as $def) {
            $existing = DB::table('ingredients')
                ->where('normalized_name', $def['normalized_name'])
                ->first();

            if ($existing) {
                $ids[$def['normalized_name']] = $existing->id;
                continue;
            }

            $ingNutrients = $def['nutrients'];
            unset($def['nutrients']);

            $ingId = DB::table('ingredients')->insertGetId(array_merge($def, [
                'is_preparation'  => false,
                'is_supplement'   => false,
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]));

            $ids[$def['normalized_name']] = $ingId;

            // Nutrient values per 100g
            foreach ($ingNutrients as $nutrientCode => $amount) {
                $nutrientId = $nutrients[$nutrientCode] ?? null;
                if (!$nutrientId) {
                    continue;
                }

                DB::table('ingredient_nutrients')->updateOrInsert(
                    ['ingredient_id' => $ingId, 'nutrient_id' => $nutrientId],
                    [
                        'ingredient_id'   => $ingId,
                        'nutrient_id'     => $nutrientId,
                        'amount_per_100g' => $amount,
                        'source'          => 'demo',
                        'status'          => 'active',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]
                );
            }
        }

        return $ids;
    }

    // ── Products ─────────────────────────────────────────────────────────────

    private function seedProducts(array $units, array $ingredientIds, $now)
    {
        $gId  = $units['g']  ?? null;
        $mlId = $units['ml'] ?? null;
        $kgId = $units['kg'] ?? null;
        $lId  = $units['l']  ?? null;

        // Ensure demo brands exist
        $brandIds = $this->ensureBrands($now);

        // Ensure demo product categories exist
        $catIds = $this->ensureProductCategories($now);

        $defs = [
            [
                'name'            => 'Harina 000 Morixe 1 kg',
                'normalized_name' => 'harina 000 morixe 1 kg',
                'brand_id'        => $brandIds['morixe'] ?? null,
                'category_id'     => $catIds['harinas'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 1000,
                'package_unit_id' => $kgId,
                'barcode'         => '7798013000111',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Azúcar Ledesma 1 kg',
                'normalized_name' => 'azucar ledesma 1 kg',
                'brand_id'        => $brandIds['ledesma'] ?? null,
                'category_id'     => $catIds['azucares'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 1000,
                'package_unit_id' => $kgId,
                'barcode'         => '7790380000123',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Aceite de Girasol Cañuelas 900 ml',
                'normalized_name' => 'aceite de girasol canuelas 900 ml',
                'brand_id'        => $brandIds['canuelas'] ?? null,
                'category_id'     => $catIds['aceites'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $mlId,
                'net_quantity'    => 900,
                'package_unit_id' => $mlId,
                'barcode'         => '7793160000234',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Leche Entera La Serenísima 1 L',
                'normalized_name' => 'leche entera la serenisima 1 l',
                'brand_id'        => $brandIds['serenisima'] ?? null,
                'category_id'     => $catIds['lacteos'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $mlId,
                'net_quantity'    => 1000,
                'package_unit_id' => $lId,
                'barcode'         => '7792800000345',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Arroz Doble Carolina SOS 500 g',
                'normalized_name' => 'arroz doble carolina sos 500 g',
                'brand_id'        => $brandIds['sos'] ?? null,
                'category_id'     => $catIds['arroz'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 500,
                'package_unit_id' => $gId,
                'barcode'         => '7793831000456',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Limón',
                'normalized_name' => 'limon',
                'brand_id'        => $brandIds['generica'] ?? null,
                'category_id'     => $catIds['frutas-verduras'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => null,
                'package_unit_id' => $gId,
                'barcode'         => '7790001000001',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Tomate Perita',
                'normalized_name' => 'tomate perita',
                'brand_id'        => $brandIds['generica'] ?? null,
                'category_id'     => $catIds['frutas-verduras'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => null,
                'package_unit_id' => $gId,
                'barcode'         => '7790001000002',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Banana',
                'normalized_name' => 'banana',
                'brand_id'        => $brandIds['generica'] ?? null,
                'category_id'     => $catIds['frutas-verduras'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => null,
                'package_unit_id' => $gId,
                'barcode'         => '7790001000003',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Fideos Spaghetti Lucchetti 500 g',
                'normalized_name' => 'fideos spaghetti lucchetti 500 g',
                'brand_id'        => $brandIds['lucchetti'] ?? null,
                'category_id'     => $catIds['pastas'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 500,
                'package_unit_id' => $gId,
                'barcode'         => '7792200000567',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Sal Entrefina Suspiria 500 g',
                'normalized_name' => 'sal entrefina suspiria 500 g',
                'brand_id'        => $brandIds['suspiria'] ?? null,
                'category_id'     => $catIds['condimentos'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 500,
                'package_unit_id' => $gId,
                'barcode'         => '7790005000678',
                'barcode_type'    => 'EAN13',
            ],
            [
                'name'            => 'Salsa de Tomate Arcor 530 g',
                'normalized_name' => 'salsa de tomate arcor 530 g',
                'brand_id'        => $brandIds['arcor'] ?? null,
                'category_id'     => $catIds['salsas'] ?? null,
                'ingredient_id'   => null,
                'default_unit_id' => $gId,
                'net_quantity'    => 530,
                'package_unit_id' => $gId,
                'barcode'         => '7790580000789',
                'barcode_type'    => 'EAN13',
            ],
        ];

        $ids = [];

        foreach ($defs as $def) {
            $barcode     = $def['barcode'];
            $barcodeType = $def['barcode_type'];
            unset($def['barcode'], $def['barcode_type']);

            $existing = DB::table('products')
                ->where('normalized_name', $def['normalized_name'])
                ->first();

            if ($existing) {
                $ids[$def['normalized_name']] = $existing->id;
                continue;
            }

            $supplyId = $this->ensureLegacySupply($def['name'], $now);

            $productId = DB::table('products')->insertGetId(array_merge($def, [
                'nombre'       => $def['name'],
                'codigo'       => $barcode,
                'img'          => '',
                'habilitado'   => 1,
                'supply_id'    => $supplyId,
                'description'  => null,
                'is_verified'  => true,
                'is_active'    => true,
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]));

            $ids[$def['normalized_name']] = $productId;

            DB::table('product_barcodes')->updateOrInsert(
                ['barcode' => $barcode, 'type' => $barcodeType],
                [
                    'product_id'  => $productId,
                    'barcode'     => $barcode,
                    'type'        => $barcodeType,
                    'status'      => 'active',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
        }

        return $ids;
    }

    private function ensureBrands($now)
    {
        $brands = [
            'generica'   => ['name' => 'Genérica',        'normalized_name' => 'generica'],
            'morixe'     => ['name' => 'Morixe',          'normalized_name' => 'morixe'],
            'ledesma'    => ['name' => 'Ledesma',         'normalized_name' => 'ledesma'],
            'canuelas'   => ['name' => 'Cañuelas',        'normalized_name' => 'canuelas'],
            'serenisima' => ['name' => 'La Serenísima',   'normalized_name' => 'la serenisima'],
            'sos'        => ['name' => 'SOS',              'normalized_name' => 'sos'],
            'lucchetti'  => ['name' => 'Lucchetti',        'normalized_name' => 'lucchetti'],
            'arcor'      => ['name' => 'Arcor',            'normalized_name' => 'arcor'],
            'suspiria'   => ['name' => 'Suspiria',         'normalized_name' => 'suspiria'],
        ];

        $ids = [];
        foreach ($brands as $key => $brand) {
            $existing = DB::table('brands')->where('normalized_name', $brand['normalized_name'])->first();
            if ($existing) {
                $ids[$key] = $existing->id;
                continue;
            }
            $ids[$key] = DB::table('brands')->insertGetId(array_merge($brand, [
                'nombre'     => $brand['name'],
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        return $ids;
    }

    private function ensureProductCategories($now)
    {
        $cats = [
            'harinas'         => 'Harinas',
            'azucares'        => 'Azúcares',
            'aceites'         => 'Aceites',
            'lacteos'         => 'Lácteos',
            'arroz'           => 'Arroz',
            'frutas-verduras' => 'Frutas y Verduras',
            'pastas'          => 'Pastas',
            'condimentos'     => 'Condimentos',
            'salsas'          => 'Salsas y Conservas',
        ];

        $ids = [];
        foreach ($cats as $key => $name) {
            $existing = DB::table('product_categories')->where('name', $name)->first();
            if ($existing) {
                $ids[$key] = $existing->id;
                continue;
            }
            $ids[$key] = DB::table('product_categories')->insertGetId([
                'name'       => $name,
                'parent_id'  => null,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    // ── Recipes ──────────────────────────────────────────────────────────────

    private function seedRecipes(array $ingredientIds, array $units, $userUser, $recipeUser, $now)
    {
        $gId  = $units['g']    ?? null;
        $mlId = $units['ml']   ?? null;

        $catId = $this->ensureRecipeCategory('Platos Principales', $now);

        $recipes = [
            [
                'name'             => 'Arroz con pollo',
                'normalized_name'  => 'arroz con pollo',
                'description'      => 'Clásico arroz con pollo al estilo casero.',
                'servings'         => 4,
                'prep_time_minutes'=> 15,
                'cook_time_minutes'=> 35,
                'difficulty'       => 'easy',
                'is_official'      => true,
                'is_public'        => true,
                'is_verified'      => true,
                'owner_user_id'    => $recipeUser ? $recipeUser->id : ($userUser ? $userUser->id : null),
                'category_id'      => $catId,
                'status'           => 'active',
                'ingredients' => [
                    ['key' => 'arroz blanco',    'quantity' => 300,  'unit_id' => $gId],
                    ['key' => 'pechuga de pollo','quantity' => 400,  'unit_id' => $gId],
                    ['key' => 'cebolla',         'quantity' => 100,  'unit_id' => $gId],
                    ['key' => 'tomate fresco',   'quantity' => 150,  'unit_id' => $gId],
                    ['key' => 'aceite de girasol','quantity' => 30,  'unit_id' => $mlId],
                ],
                'steps' => [
                    ['step_number' => 1, 'description' => 'Cortar el pollo en cubos y dorar en aceite caliente.', 'estimated_minutes' => 8],
                    ['step_number' => 2, 'description' => 'Agregar cebolla y tomate picados. Rehogar 5 minutos.', 'estimated_minutes' => 5],
                    ['step_number' => 3, 'description' => 'Incorporar el arroz, cubrir con agua y cocinar a fuego medio hasta absorción.', 'estimated_minutes' => 22],
                ],
            ],
            [
                'name'             => 'Tortilla de papa',
                'normalized_name'  => 'tortilla de papa',
                'description'      => 'Tortilla española de papa y cebolla.',
                'servings'         => 3,
                'prep_time_minutes'=> 20,
                'cook_time_minutes'=> 25,
                'difficulty'       => 'medium',
                'is_official'      => true,
                'is_public'        => true,
                'is_verified'      => true,
                'owner_user_id'    => $recipeUser ? $recipeUser->id : ($userUser ? $userUser->id : null),
                'category_id'      => $catId,
                'status'           => 'active',
                'ingredients' => [
                    ['key' => 'papa',            'quantity' => 500,  'unit_id' => $gId],
                    ['key' => 'huevo de gallina','quantity' => 4,    'unit_id' => $units['unit'] ?? $gId],
                    ['key' => 'cebolla',         'quantity' => 150,  'unit_id' => $gId],
                    ['key' => 'aceite de girasol','quantity' => 60,  'unit_id' => $mlId],
                ],
                'steps' => [
                    ['step_number' => 1, 'description' => 'Pelar y cortar las papas en rodajas finas. Freír en aceite abundante.', 'estimated_minutes' => 15],
                    ['step_number' => 2, 'description' => 'Batir los huevos, mezclar con las papas y la cebolla.', 'estimated_minutes' => 5],
                    ['step_number' => 3, 'description' => 'Cocinar la tortilla en sartén antiadherente, dar vuelta con cuidado.', 'estimated_minutes' => 10],
                ],
            ],
            [
                'name'             => 'Bizcochuelo esponjoso',
                'normalized_name'  => 'bizcochuelo esponjoso',
                'description'      => 'Bizcochuelo clásico, ideal para tortas y postres.',
                'servings'         => 8,
                'prep_time_minutes'=> 20,
                'cook_time_minutes'=> 35,
                'difficulty'       => 'easy',
                'is_official'      => true,
                'is_public'        => true,
                'is_verified'      => true,
                'owner_user_id'    => $recipeUser ? $recipeUser->id : ($userUser ? $userUser->id : null),
                'category_id'      => $this->ensureRecipeCategory('Repostería', $now),
                'status'           => 'active',
                'ingredients' => [
                    ['key' => 'harina de trigo 000', 'quantity' => 200, 'unit_id' => $gId],
                    ['key' => 'azucar refinada',     'quantity' => 200, 'unit_id' => $gId],
                    ['key' => 'huevo de gallina',    'quantity' => 4,   'unit_id' => $units['unit'] ?? $gId],
                    ['key' => 'leche entera',        'quantity' => 100, 'unit_id' => $mlId],
                ],
                'steps' => [
                    ['step_number' => 1, 'description' => 'Batir los huevos con el azúcar hasta obtener punto letra.', 'estimated_minutes' => 10],
                    ['step_number' => 2, 'description' => 'Incorporar la harina tamizada de forma envolvente.', 'estimated_minutes' => 5],
                    ['step_number' => 3, 'description' => 'Agregar la leche tibia, volcar en molde enmantecado y hornear a 180°C.', 'estimated_minutes' => 35],
                ],
            ],
        ];

        foreach ($recipes as $recipeDef) {
            $existing = DB::table('recipes')
                ->where('normalized_name', $recipeDef['normalized_name'])
                ->first();

            if ($existing) {
                continue;
            }

            $recipeIngredients = $recipeDef['ingredients'];
            $recipeSteps       = $recipeDef['steps'];
            unset($recipeDef['ingredients'], $recipeDef['steps']);

            $totalMinutes = ($recipeDef['prep_time_minutes'] ?? 0) + ($recipeDef['cook_time_minutes'] ?? 0);

            $recipeId = DB::table('recipes')->insertGetId(array_merge($recipeDef, [
                'nombre'      => $recipeDef['name'],
                'descripcion' => $recipeDef['description'],
                'tiempo'      => (string) $totalMinutes . ' min',
                'img'         => '',
                'video'       => '',
                'porcion'     => (string) ($recipeDef['servings'] ?? 1),
                'calorias'    => 0,
                'source_type' => 'official',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]));

            foreach ($recipeIngredients as $sortOrder => $ri) {
                $ingId = $ingredientIds[$ri['key']] ?? null;
                if (!$ingId || !$ri['unit_id']) {
                    continue;
                }

                DB::table('recipe_ingredients')->insert([
                    'recipe_id'     => $recipeId,
                    'ingredient_id' => $ingId,
                    'quantity'      => $ri['quantity'],
                    'unit_id'       => $ri['unit_id'],
                    'is_optional'   => false,
                    'sort_order'    => $sortOrder,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            foreach ($recipeSteps as $step) {
                DB::table('recipe_steps')->insert([
                    'recipe_id'         => $recipeId,
                    'step_number'       => $step['step_number'],
                    'description'       => $step['description'],
                    'estimated_minutes' => $step['estimated_minutes'],
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }
    }

    private function ensureLegacyCategory($nombre, $now)
    {
        $existing = DB::table('categories')->where('nombre', $nombre)->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('categories')->insertGetId([
            'nombre'     => $nombre,
            'padre'      => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureLegacySupply($productName, $now)
    {
        $nombre = substr($productName, 0, 100);
        $existing = DB::table('supplies')->where('nombre', $nombre)->first();
        if ($existing) {
            return $existing->id;
        }
        $catId = $this->ensureLegacyCategory('Almacen Demo', $now);
        return DB::table('supplies')->insertGetId([
            'nombre'     => $nombre,
            'medida'     => 'g',
            'category_id'=> $catId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureRecipeCategory($name, $now)
    {
        $existing = DB::table('recipe_categories')->where('name', $name)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('recipe_categories')->insertGetId([
            'name'       => $name,
            'parent_id'  => null,
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // ── Stock ─────────────────────────────────────────────────────────────────

    private function seedStock($fgId, array $productIds, array $units, $now)
    {
        $gId = $units['g'] ?? null;
        if (!$gId) {
            return;
        }

        // Stock locations
        $locations = [
            ['name' => 'Alacena',  'type' => 'pantry'],
            ['name' => 'Heladera', 'type' => 'fridge'],
            ['name' => 'Freezer',  'type' => 'freezer'],
        ];

        $locationIds = [];
        foreach ($locations as $loc) {
            $existing = DB::table('stock_locations')
                ->where('family_group_id', $fgId)
                ->where('name', $loc['name'])
                ->first();

            if ($existing) {
                $locationIds[$loc['name']] = $existing->id;
                continue;
            }

            $locationIds[$loc['name']] = DB::table('stock_locations')->insertGetId([
                'family_group_id' => $fgId,
                'name'            => $loc['name'],
                'type'            => $loc['type'],
                'status'          => 'active',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // Stock items (one per product)
        $stockDefs = [
            ['normalized_name' => 'harina 000 morixe 1 kg',               'quantity' => 2000, 'location' => 'Alacena',  'expiry' => null],
            ['normalized_name' => 'azucar ledesma 1 kg',                  'quantity' => 1000, 'location' => 'Alacena',  'expiry' => null],
            ['normalized_name' => 'aceite de girasol canuelas 900 ml',    'quantity' => 900,  'location' => 'Alacena',  'expiry' => null],
            ['normalized_name' => 'leche entera la serenisima 1 l',       'quantity' => 2000, 'location' => 'Heladera', 'expiry' => date('Y-m-d', strtotime('+7 days'))],
            ['normalized_name' => 'arroz doble carolina sos 500 g',       'quantity' => 500,  'location' => 'Alacena',  'expiry' => null],
            ['normalized_name' => 'limon',                                'quantity' => 500,  'location' => 'Heladera', 'expiry' => date('Y-m-d', strtotime('+5 days'))],
            ['normalized_name' => 'tomate perita',                        'quantity' => 800,  'location' => 'Heladera', 'expiry' => date('Y-m-d', strtotime('+4 days'))],
            ['normalized_name' => 'banana',                               'quantity' => 600,  'location' => 'Alacena',  'expiry' => date('Y-m-d', strtotime('+3 days'))],
            ['normalized_name' => 'fideos spaghetti lucchetti 500 g',     'quantity' => 500,  'location' => 'Alacena',  'expiry' => null],
            ['normalized_name' => 'salsa de tomate arcor 530 g',          'quantity' => 530,  'location' => 'Alacena',  'expiry' => date('Y-m-d', strtotime('+180 days'))],
        ];

        foreach ($stockDefs as $def) {
            $productId  = $productIds[$def['normalized_name']] ?? null;
            $locationId = $locationIds[$def['location']] ?? null;
            if (!$productId) {
                continue;
            }

            $exists = DB::table('stock_items')
                ->where('family_group_id', $fgId)
                ->where('product_id', $productId)
                ->where('stock_location_id', $locationId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('stock_items')->insert([
                'family_group_id'         => $fgId,
                'product_id'              => $productId,
                'stock_location_id'       => $locationId,
                'quantity'                => $def['quantity'],
                'unit_id'                 => $gId,
                'purchase_date'           => date('Y-m-d', strtotime('-3 days')),
                'expiration_date'         => $def['expiry'],
                'is_open'                 => false,
                'estimated_purchase_price'=> null,
                'status'                  => 'active',
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);
        }
    }

    // ── Shopping List ─────────────────────────────────────────────────────────

    private function seedShoppingList($fgId, array $ingredientIds, array $productIds, array $units, $userUser, $now)
    {
        $exists = DB::table('shopping_lists')
            ->where('family_group_id', $fgId)
            ->where('source_type', 'manual')
            ->exists();

        if ($exists) {
            return;
        }

        $listId = DB::table('shopping_lists')->insertGetId([
            'family_group_id'             => $fgId,
            'meal_plan_id'                => null,
            'created_by'                  => $userUser ? $userUser->id : null,
            'source_type'                 => 'manual',
            'status'                      => 'draft',
            'estimated_total'             => 2500.00,
            'selected_supermarket_branch_id' => null,
            'optimization_mode'           => 'cost',
            'created_at'                  => $now,
            'updated_at'                  => $now,
        ]);

        $gId    = $units['g']    ?? null;
        $mlId   = $units['ml']   ?? null;
        $unitId = $units['unit'] ?? null;

        $items = [
            ['ingredient_key' => 'harina de trigo 000', 'product_key' => 'harina 000 morixe 1 kg',         'quantity' => 1000, 'unit_id' => $gId,    'estimated_price' => 850.00],
            ['ingredient_key' => 'leche entera',         'product_key' => 'leche entera la serenisima 1 l', 'quantity' => 2000, 'unit_id' => $mlId,   'estimated_price' => 720.00],
            ['ingredient_key' => 'arroz blanco',         'product_key' => 'arroz doble carolina sos 500 g', 'quantity' => 500,  'unit_id' => $gId,    'estimated_price' => 480.00],
            ['ingredient_key' => 'huevo de gallina',     'product_key' => null,                              'quantity' => 12,   'unit_id' => $unitId, 'estimated_price' => 450.00],
        ];

        foreach ($items as $item) {
            $ingId     = $ingredientIds[$item['ingredient_key']] ?? null;
            $productId = $item['product_key'] ? ($productIds[$item['product_key']] ?? null) : null;
            $unitId    = $item['unit_id'];

            if (!$unitId) {
                continue;
            }

            DB::table('shopping_list_items')->insert([
                'shopping_list_id'              => $listId,
                'ingredient_id'                 => $ingId,
                'product_id'                    => $productId,
                'selected_supermarket_product_id' => null,
                'quantity'                      => $item['quantity'],
                'unit_id'                       => $unitId,
                'estimated_price'               => $item['estimated_price'],
                'actual_price'                  => null,
                'status'                        => 'pending',
                'notes'                         => null,
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ]);
        }
    }

    // ── Budget ────────────────────────────────────────────────────────────────

    private function seedBudget($fgId, $now)
    {
        $year  = (int) date('Y');
        $month = (int) date('n');

        DB::table('budgets')->updateOrInsert(
            ['family_group_id' => $fgId, 'year' => $year, 'month' => $month],
            [
                'family_group_id' => $fgId,
                'year'            => $year,
                'month'           => $month,
                'total_amount'    => 50000.00,
                'currency'        => 'ARS',
                'status'          => 'active',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );
    }

    // ── Supermarket data ──────────────────────────────────────────────────────

    private function seedSupermarketData(array $productIds, $now)
    {
        $chains = DB::table('supermarket_chains')->get()->keyBy('code');
        $city   = DB::table('cities')->first();

        if (!$city || $chains->isEmpty()) {
            return;
        }

        // Ensure at least one branch per chain in the city
        $branchIds = [];
        foreach ($chains as $code => $chain) {
            $existing = DB::table('supermarket_branches')
                ->where('supermarket_chain_id', $chain->id)
                ->where('city_id', $city->id)
                ->first();

            if ($existing) {
                $branchIds[$code] = $existing->id;
                continue;
            }

            $branchIds[$code] = DB::table('supermarket_branches')->insertGetId([
                'supermarket_chain_id' => $chain->id,
                'city_id'              => $city->id,
                'name'                 => $chain->name . ' - Centro',
                'address'              => 'Av. San Martín 500, Bariloche',
                'latitude'             => -41.1335 + (rand(-50, 50) / 10000),
                'longitude'            => -71.3103 + (rand(-50, 50) / 10000),
                'phone'                => null,
                'delivery_available'   => true,
                'pickup_available'     => true,
                'status'               => 'active',
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }

        // Create supermarket products + prices for each product in the first chain
        $firstChain = $chains->first();
        if (!$firstChain) {
            return;
        }

        $branchId = $branchIds[$firstChain->code] ?? null;

        $priceDefs = [
            'harina 000 morixe 1 kg'            => 850.00,
            'azucar ledesma 1 kg'               => 620.00,
            'aceite de girasol canuelas 900 ml'  => 1250.00,
            'leche entera la serenisima 1 l'    => 360.00,
            'arroz doble carolina sos 500 g'    => 480.00,
        ];

        foreach ($priceDefs as $normalizedName => $price) {
            $productId = $productIds[$normalizedName] ?? null;
            if (!$productId) {
                continue;
            }

            $existing = DB::table('supermarket_products')
                ->where('product_id', $productId)
                ->where('supermarket_chain_id', $firstChain->id)
                ->first();

            if ($existing) {
                continue;
            }

            $spId = DB::table('supermarket_products')->insertGetId([
                'product_id'           => $productId,
                'supermarket_chain_id' => $firstChain->id,
                'supermarket_branch_id'=> $branchId,
                'last_seen_at'         => $now,
                'last_scraped_at'      => $now,
                'scrape_status'        => 'ok',
                'status'               => 'active',
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            DB::table('supermarket_product_prices')->insert([
                'supermarket_product_id' => $spId,
                'price'                  => $price,
                'unit_price'             => null,
                'currency'               => 'ARS',
                'price_type'             => 'regular',
                'scraped_at'             => $now,
                'valid_from'             => $now,
                'valid_to'               => null,
                'source'                 => 'demo',
                'status'                 => 'active',
                'created_at'             => $now,
            ]);
        }
    }

    // ── Scraping Demo Data ──────────────────────────────────────────────────

    private function seedScrapingData($userUser, array $productIds, $now)
    {
        $source = DB::table('scraping_sources')->where('code', 'carrefour')->first();
        if (!$source) {
            return;
        }

        // Job 1: completed — 2 candidates found, 1 product matched
        $completedJobId = DB::table('scraping_jobs')->insertGetId([
            'source_id'            => $source->id,
            'job_type'             => 'product_prices',
            'requested_by'         => $userUser->id,
            'status'               => 'completed',
            'parameters_json'      => json_encode(['max_pages' => 1]),
            'started_at'           => now()->subMinutes(30),
            'finished_at'          => now()->subMinutes(29),
            'total_found'          => 2,
            'total_created'        => 0,
            'total_updated'        => 1,
            'total_pending_review' => 1,
            'error_message'        => null,
            'created_at'           => now()->subMinutes(31),
            'updated_at'           => now()->subMinutes(29),
        ]);

        DB::table('scraping_job_logs')->insert([
            ['scraping_job_id' => $completedJobId, 'level' => 'info',  'message' => 'Iniciando scraping',      'context_json' => json_encode(['source' => 'carrefour']), 'created_at' => now()->subMinutes(30)],
            ['scraping_job_id' => $completedJobId, 'level' => 'info',  'message' => 'Pagina 0: 2 productos',   'context_json' => null,                                    'created_at' => now()->subMinutes(30)],
            ['scraping_job_id' => $completedJobId, 'level' => 'info',  'message' => 'Scraping completado',     'context_json' => json_encode(['found' => 2, 'updated' => 1, 'review' => 1]), 'created_at' => now()->subMinutes(29)],
        ]);

        // Candidate 1: pending review
        DB::table('scraped_product_candidates')->insert([
            'scraping_job_id'     => $completedJobId,
            'source_id'           => $source->id,
            'raw_name'            => 'Leche Entera La Serenisima 1L',
            'raw_brand'           => 'La Serenisima',
            'raw_price'           => 450.00,
            'raw_unit_price'      => 0,
            'raw_image_url'       => 'https://img.carrefour.com.ar/leche.jpg',
            'raw_product_url'     => 'https://www.carrefour.com.ar/leche-entera-1l',
            'external_product_id' => 'p001',
            'raw_payload_json'    => null,
            'review_status'       => 'pending',
            'created_at'          => now()->subMinutes(29),
            'updated_at'          => now()->subMinutes(29),
        ]);

        // Candidate 2: approved and matched
        $firstProductId = array_values($productIds)[0] ?? null;
        DB::table('scraped_product_candidates')->insert([
            'scraping_job_id'        => $completedJobId,
            'source_id'              => $source->id,
            'raw_name'               => 'Aceite de Girasol Canuelas 900ml',
            'raw_brand'              => 'Canuelas',
            'raw_price'              => 1250.00,
            'raw_unit_price'         => 0,
            'raw_image_url'          => null,
            'raw_product_url'        => 'https://www.carrefour.com.ar/aceite-girasol',
            'external_product_id'    => 'p002',
            'raw_payload_json'       => null,
            'suggested_product_id'   => $firstProductId,
            'match_confidence'       => 92.00,
            'review_status'          => 'approved',
            'reviewed_by'            => $userUser->id,
            'reviewed_at'            => now()->subMinutes(25),
            'created_at'             => now()->subMinutes(29),
            'updated_at'             => now()->subMinutes(25),
        ]);

        // Job 2: failed — source not available (ChangoMas)
        $changomas = DB::table('scraping_sources')->where('code', 'changomas')->first();
        if ($changomas) {
            $failedJobId = DB::table('scraping_jobs')->insertGetId([
                'source_id'       => $changomas->id,
                'job_type'        => 'product_prices',
                'requested_by'    => $userUser->id,
                'status'          => 'failed',
                'parameters_json' => json_encode(['max_pages' => 2]),
                'started_at'      => now()->subHours(2),
                'finished_at'     => now()->subHours(2),
                'total_found'     => 0,
                'error_message'   => 'Fuente no disponible con la infraestructura actual: changomas',
                'created_at'      => now()->subHours(2),
                'updated_at'      => now()->subHours(2),
            ]);

            DB::table('scraping_job_logs')->insert([
                ['scraping_job_id' => $failedJobId, 'level' => 'info',    'message' => 'Iniciando scraping',       'context_json' => json_encode(['source' => 'changomas']), 'created_at' => now()->subHours(2)],
                ['scraping_job_id' => $failedJobId, 'level' => 'warning', 'message' => 'Fuente no disponible con la infraestructura actual: changomas', 'context_json' => null, 'created_at' => now()->subHours(2)],
            ]);

            DB::table('scraping_alerts')->insert([
                'scraping_job_id' => $failedJobId,
                'source_id'       => $changomas->id,
                'alert_type'      => 'source_unavailable',
                'message'         => 'Fuente no disponible con la infraestructura actual: changomas',
                'severity'        => 'high',
                'status'          => 'open',
                'created_at'      => now()->subHours(2),
            ]);

            DB::table('scraping_errors')->insert([
                'scraping_job_id' => $failedJobId,
                'source_id'       => $changomas->id,
                'error_type'      => 'source_unavailable',
                'message'         => 'Fuente no disponible con la infraestructura actual: changomas',
                'stack_trace'     => null,
                'context_json'    => json_encode(['source' => 'changomas']),
                'created_at'      => now()->subHours(2),
            ]);
        }

        // Job 3: cancelled
        $cancelledJobId = DB::table('scraping_jobs')->insertGetId([
            'source_id'       => $source->id,
            'job_type'        => 'product_prices',
            'requested_by'    => $userUser->id,
            'status'          => 'cancelled',
            'parameters_json' => json_encode(['max_pages' => 5]),
            'started_at'      => null,
            'finished_at'     => now()->subHours(1),
            'total_found'     => 0,
            'error_message'   => null,
            'created_at'      => now()->subHours(1)->subMinutes(5),
            'updated_at'      => now()->subHours(1),
        ]);

        DB::table('scraping_job_logs')->insert([
            ['scraping_job_id' => $cancelledJobId, 'level' => 'info', 'message' => 'Iniciando scraping', 'context_json' => json_encode(['source' => 'carrefour']), 'created_at' => now()->subHours(1)->subMinutes(5)],
        ]);
    }

    // ── Promotions, Branch Availability & Payment Methods ───────────────────

    private function seedPromotionsAndPaymentMethods($userUser, array $productIds, $now)
    {
        $chain = DB::table('supermarket_chains')->where('code', 'carrefour')->first();
        if (!$chain) {
            return;
        }

        $city = DB::table('cities')->first();

        // Ensure two branches exist for Carrefour
        $branch1 = DB::table('supermarket_branches')
            ->where('supermarket_chain_id', $chain->id)
            ->first();

        if (!$branch1) {
            return;
        }

        $branch2 = DB::table('supermarket_branches')
            ->where('supermarket_chain_id', $chain->id)
            ->where('id', '!=', $branch1->id)
            ->first();

        if (!$branch2 && $city) {
            $branch2Id = DB::table('supermarket_branches')->insertGetId([
                'supermarket_chain_id' => $chain->id,
                'city_id'              => $city->id,
                'name'                 => $chain->name . ' - Alto',
                'address'              => 'Av. Bustillo 8500, Bariloche',
                'latitude'             => -41.1335 + 0.02,
                'longitude'            => -71.3103 - 0.02,
                'delivery_available'   => false,
                'pickup_available'     => true,
                'status'               => 'active',
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $branch2 = DB::table('supermarket_branches')->where('id', $branch2Id)->first();
        }

        if (!$branch2) {
            return;
        }

        // Payment methods
        $cashId   = DB::table('payment_methods')->where('type', 'cash')->value('id');
        $debitId  = DB::table('payment_methods')->where('type', 'debit_card')->value('id');
        $creditId = DB::table('payment_methods')->where('type', 'credit_card')->value('id');

        // User payment methods for demo user
        if ($cashId) {
            DB::table('user_payment_methods')->updateOrInsert(
                ['user_id' => $userUser->id, 'payment_method_id' => $cashId, 'alias' => null],
                ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]
            );
        }
        if ($debitId) {
            DB::table('user_payment_methods')->updateOrInsert(
                ['user_id' => $userUser->id, 'payment_method_id' => $debitId, 'alias' => 'Debito personal'],
                ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Promotions
        $promotionDefs = [
            [
                'name'                   => '15% en productos seleccionados',
                'description'            => 'Descuento general en productos de almacen.',
                'discount_type'          => 'percentage',
                'discount_value'         => 15.00,
                'valid_from'             => now()->startOfMonth(),
                'valid_to'               => now()->endOfMonth(),
                'day_of_week'            => null,
                'requires_payment_method'=> false,
                'supermarket_branch_id'  => null,
            ],
            [
                'name'                   => '2x1 en harinas',
                'description'            => 'Llevate dos harinas y paga una.',
                'discount_type'          => 'two_for_one',
                'discount_value'         => null,
                'valid_from'             => now()->subDays(5),
                'valid_to'               => now()->addDays(10),
                'day_of_week'            => null,
                'requires_payment_method'=> false,
                'supermarket_branch_id'  => $branch1->id,
            ],
            [
                'name'                   => 'Miercoles ahorro 20%',
                'description'            => 'Todos los miercoles 20% de descuento en almacen.',
                'discount_type'          => 'percentage',
                'discount_value'         => 20.00,
                'valid_from'             => now()->startOfMonth(),
                'valid_to'               => now()->addMonths(3),
                'day_of_week'            => 3,
                'requires_payment_method'=> false,
                'supermarket_branch_id'  => null,
            ],
            [
                'name'                   => '10% con debito',
                'description'            => 'Descuento con tarjeta de debito todos los dias.',
                'discount_type'          => 'percentage',
                'discount_value'         => 10.00,
                'valid_from'             => now()->startOfMonth(),
                'valid_to'               => now()->addMonths(6),
                'day_of_week'            => null,
                'requires_payment_method'=> true,
                'supermarket_branch_id'  => $branch2->id,
            ],
            [
                'name'                   => 'Promo verano 25% (vencida)',
                'description'            => 'Promocion de verano ya expirada.',
                'discount_type'          => 'percentage',
                'discount_value'         => 25.00,
                'valid_from'             => now()->subMonths(3),
                'valid_to'               => now()->subMonths(1),
                'day_of_week'            => null,
                'requires_payment_method'=> false,
                'supermarket_branch_id'  => null,
            ],
        ];

        $promoWithDebitId = null;
        foreach ($promotionDefs as $def) {
            $existing = DB::table('promotions')
                ->where('supermarket_chain_id', $chain->id)
                ->where('name', $def['name'])
                ->first();

            if ($existing) {
                if ($def['requires_payment_method']) {
                    $promoWithDebitId = $existing->id;
                }
                continue;
            }

            $promoId = DB::table('promotions')->insertGetId(array_merge($def, [
                'supermarket_chain_id' => $chain->id,
                'status'               => $def['valid_to'] < now() ? 'inactive' : 'active',
                'created_at'           => $now,
                'updated_at'           => $now,
            ]));

            if ($def['requires_payment_method']) {
                $promoWithDebitId = $promoId;
            }
        }

        // Link debit payment method to the payment-required promotion
        if ($promoWithDebitId && $debitId) {
            DB::table('promotion_payment_methods')->updateOrInsert(
                ['promotion_id' => $promoWithDebitId, 'payment_method_id' => $debitId],
                ['created_at' => $now]
            );
        }

        // Branch product availability — add products to both branches
        $productKey = 'harina 000 morixe 1 kg';
        $productId  = $productIds[$productKey] ?? null;
        if (!$productId) {
            return;
        }

        // Ensure a supermarket_product exists for branch2
        $sp = DB::table('supermarket_products')
            ->where('product_id', $productId)
            ->where('supermarket_chain_id', $chain->id)
            ->where('supermarket_branch_id', $branch2->id)
            ->first();

        if (!$sp) {
            $spId = DB::table('supermarket_products')->insertGetId([
                'product_id'            => $productId,
                'supermarket_chain_id'  => $chain->id,
                'supermarket_branch_id' => $branch2->id,
                'last_seen_at'          => $now,
                'last_scraped_at'       => $now,
                'scrape_status'         => 'ok',
                'status'                => 'active',
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);

            DB::table('supermarket_product_prices')->insert([
                'supermarket_product_id' => $spId,
                'price'                  => 890.00,
                'currency'               => 'ARS',
                'price_type'             => 'regular',
                'scraped_at'             => $now,
                'valid_from'             => $now,
                'source'                 => 'demo',
                'status'                 => 'active',
                'created_at'             => $now,
            ]);

            $sp = DB::table('supermarket_products')->where('id', $spId)->first();
        }

        // branch_product_availability for branch1 (find existing sp for branch1)
        $sp1 = DB::table('supermarket_products')
            ->where('product_id', $productId)
            ->where('supermarket_chain_id', $chain->id)
            ->where('supermarket_branch_id', $branch1->id)
            ->first();

        if ($sp1) {
            DB::table('branch_product_availability')->updateOrInsert(
                ['supermarket_branch_id' => $branch1->id, 'supermarket_product_id' => $sp1->id],
                [
                    'is_available'    => true,
                    'last_checked_at' => $now,
                    'source'          => 'demo',
                    'status'          => 'active',
                ]
            );
        }

        if ($sp) {
            DB::table('branch_product_availability')->updateOrInsert(
                ['supermarket_branch_id' => $branch2->id, 'supermarket_product_id' => $sp->id],
                [
                    'is_available'    => true,
                    'last_checked_at' => $now,
                    'source'          => 'demo',
                    'status'          => 'active',
                ]
            );
        }
    }

    // ── Thesis documents ────────────────────────────────────────────────────

    private function seedThesisDocuments($superadminUser, $now)
    {
        $authorId = $superadminUser ? $superadminUser->id : null;

        $docs = [
            [
                'slug'        => 'especificacion-funcional',
                'title'       => 'Especificación Funcional',
                'description' => 'Documento de requerimientos funcionales del sistema CC Control.',
                'status'      => 'published',
                'sections'    => [
                    [
                        'title'      => 'Introducción',
                        'content'    => "CC Control es una plataforma orientada a la gestión de alimentación, stock, recetas, compras y presupuesto familiar. Este documento describe los módulos funcionales que componen el sistema.",
                        'sort_order' => 1,
                        'parent'     => null,
                        'children'   => [
                            ['title' => 'Objetivo del sistema', 'content' => "El objetivo principal de CC Control es brindar herramientas concretas para que los usuarios gestionen su alimentación de forma ordenada, eficiente y saludable, con soporte para grupos familiares y seguimiento nutricional.", 'sort_order' => 1],
                            ['title' => 'Alcance', 'content' => "El sistema abarca los módulos de catálogo de productos e ingredientes, stock del hogar, planificación de recetas, lista de compras, seguimiento de presupuesto y comparación de precios en supermercados.", 'sort_order' => 2],
                        ],
                    ],
                    [
                        'title'      => 'Módulos principales',
                        'content'    => "A continuación se describen los módulos funcionales principales del sistema.",
                        'sort_order' => 2,
                        'parent'     => null,
                        'children'   => [
                            ['title' => 'Catálogo', 'content' => "Gestión de productos e ingredientes. Permite buscar, filtrar por categoría, marca y etiquetas, y ver información nutricional por cada ingrediente.", 'sort_order' => 1],
                            ['title' => 'Stock', 'content' => "Control de inventario del hogar por grupo familiar. Permite registrar cantidades, fechas de vencimiento y unidades de medida.", 'sort_order' => 2],
                            ['title' => 'Recetas', 'content' => "Creación y administración de recetas con ingredientes, pasos y porciones. Integración con el catálogo y cálculo nutricional automático.", 'sort_order' => 3],
                            ['title' => 'Compras', 'content' => "Generación de listas de compras basadas en el stock y las recetas planificadas. Registro de precios y comparación entre supermercados.", 'sort_order' => 4],
                            ['title' => 'Presupuesto', 'content' => "Registro y seguimiento del presupuesto mensual del grupo familiar. Alertas cuando el gasto se acerca al límite establecido.", 'sort_order' => 5],
                        ],
                    ],
                    [
                        'title'      => 'Roles y permisos',
                        'content'    => "El sistema define roles con distintos niveles de acceso: super_admin, admin, user, dietologo. Cada rol tiene permisos específicos sobre los módulos del sistema.",
                        'sort_order' => 3,
                        'parent'     => null,
                        'children'   => [],
                    ],
                ],
            ],
            [
                'slug'        => 'arquitectura-tecnica',
                'title'       => 'Arquitectura Técnica',
                'description' => 'Descripción de la arquitectura de software, stack tecnológico y decisiones de diseño del sistema CC Control.',
                'status'      => 'published',
                'sections'    => [
                    [
                        'title'      => 'Stack tecnológico',
                        'content'    => "CC Control utiliza Laravel 7.x como framework backend con PHP 7.4+, PostgreSQL como base de datos principal y Vue.js / Blade como capa de presentación.",
                        'sort_order' => 1,
                        'parent'     => null,
                        'children'   => [
                            ['title' => 'Backend', 'content' => "Laravel 7.x + PHP 7.4. Arquitectura Route → Controller → FormRequest → Service → Repository → Model. Autenticación con Bearer token propio (ApiTokenService, sin Sanctum/Passport).", 'sort_order' => 1],
                            ['title' => 'Base de datos', 'content' => "PostgreSQL para producción y demo. SQLite in-memory para tests unitarios y de integración. ORM: Eloquent con SoftDeletes y auditoría mediante AuditLog.", 'sort_order' => 2],
                            ['title' => 'Frontend', 'content' => "Vistas Blade con scripts JavaScript vanilla (IIFE, DOMContentLoaded, data-* attributes). Comunicación con la API a través de window.CCApi.request(). No usa jQuery ni frameworks SPA.", 'sort_order' => 3],
                        ],
                    ],
                    [
                        'title'      => 'API REST',
                        'content'    => "Todas las APIs nuevas usan el prefijo /api/v1 y responden exclusivamente en JSON. El formato de respuesta es uniforme con data, meta, links y trace_id.",
                        'sort_order' => 2,
                        'parent'     => null,
                        'children'   => [
                            ['title' => 'Autenticación', 'content' => "Los endpoints protegidos requieren un Bearer token emitido por ApiTokenService. El middleware api_token valida el token y autentica al usuario.", 'sort_order' => 1],
                            ['title' => 'Autorización', 'content' => "RBAC basado en tablas roles, permissions, user_roles y role_permissions. Métodos User::hasRole() y User::hasPermission() disponibles en el modelo de usuario.", 'sort_order' => 2],
                            ['title' => 'Trazabilidad', 'content' => "Cada request genera un trace_id UUID que se incluye en headers de respuesta, body JSON y registros de auditoría. Se puede propagar mediante el header X-Trace-Id.", 'sort_order' => 3],
                        ],
                    ],
                    [
                        'title'      => 'Auditoría',
                        'content'    => "Todas las operaciones relevantes (creación, modificación, baja lógica, restore) se registran en la tabla audit_logs con actor, acción, recurso, estado anterior y posterior, trace_id, IP y user-agent.",
                        'sort_order' => 3,
                        'parent'     => null,
                        'children'   => [],
                    ],
                ],
            ],
            [
                'slug'        => 'guia-de-integracion',
                'title'       => 'Guía de Integración',
                'description' => 'Guía práctica para integrar servicios externos y configurar el entorno de desarrollo.',
                'status'      => 'draft',
                'sections'    => [
                    [
                        'title'      => 'Configuración del entorno',
                        'content'    => "Para configurar el entorno de desarrollo es necesario contar con PHP 7.4+, Composer, PostgreSQL y Node.js. Copiar .env.example a .env y ajustar las variables de base de datos y APP_KEY.",
                        'sort_order' => 1,
                        'parent'     => null,
                        'children'   => [
                            ['title' => 'Variables de entorno', 'content' => "Las variables principales son: APP_KEY, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET.", 'sort_order' => 1],
                            ['title' => 'Migraciones y seeds', 'content' => "Ejecutar php artisan migrate para crear las tablas y php artisan db:seed --class=DemoDataSeeder para poblar datos de demostración.", 'sort_order' => 2],
                        ],
                    ],
                    [
                        'title'      => 'Integración con scraping',
                        'content'    => "El módulo de scraping obtiene precios de supermercados mediante fuentes configuradas en la tabla scraping_sources. Los candidatos procesados se almacenan en scraped_product_candidates para revisión manual antes de confirmar en supermarket_products.",
                        'sort_order' => 2,
                        'parent'     => null,
                        'children'   => [],
                    ],
                ],
            ],
        ];

        foreach ($docs as $docData) {
            $docId = DB::table('thesis_documents')->where('slug', $docData['slug'])->value('id');

            if (!$docId) {
                $docId = DB::table('thesis_documents')->insertGetId([
                    'title'       => $docData['title'],
                    'slug'        => $docData['slug'],
                    'description' => $docData['description'],
                    'status'      => $docData['status'],
                    'created_by'  => $authorId,
                    'updated_by'  => $authorId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                DB::table('thesis_documents')->where('id', $docId)->update([
                    'title'       => $docData['title'],
                    'description' => $docData['description'],
                    'status'      => $docData['status'],
                    'updated_by'  => $authorId,
                    'updated_at'  => $now,
                ]);
            }

            // Version snapshot
            $hasVersion = DB::table('thesis_document_versions')
                ->where('document_id', $docId)
                ->where('version_number', 1)
                ->exists();

            if (!$hasVersion) {
                DB::table('thesis_document_versions')->insert([
                    'document_id'      => $docId,
                    'version_number'   => 1,
                    'content_snapshot' => json_encode(['title' => $docData['title'], 'status' => $docData['status']]),
                    'created_by'       => $authorId,
                    'created_at'       => $now,
                ]);
            }

            // Sections
            $sortOrder = 1;
            foreach ($docData['sections'] as $sectionData) {
                $sectionId = DB::table('thesis_document_sections')
                    ->where('document_id', $docId)
                    ->where('title', $sectionData['title'])
                    ->whereNull('parent_id')
                    ->value('id');

                if (!$sectionId) {
                    $sectionId = DB::table('thesis_document_sections')->insertGetId([
                        'document_id' => $docId,
                        'parent_id'   => null,
                        'title'       => $sectionData['title'],
                        'content'     => $sectionData['content'],
                        'sort_order'  => $sectionData['sort_order'],
                        'status'      => 'published',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                } else {
                    DB::table('thesis_document_sections')->where('id', $sectionId)->update([
                        'content'    => $sectionData['content'],
                        'sort_order' => $sectionData['sort_order'],
                        'updated_at' => $now,
                    ]);
                }

                foreach ($sectionData['children'] as $child) {
                    $childExists = DB::table('thesis_document_sections')
                        ->where('document_id', $docId)
                        ->where('parent_id', $sectionId)
                        ->where('title', $child['title'])
                        ->exists();

                    if (!$childExists) {
                        DB::table('thesis_document_sections')->insert([
                            'document_id' => $docId,
                            'parent_id'   => $sectionId,
                            'title'       => $child['title'],
                            'content'     => $child['content'],
                            'sort_order'  => $child['sort_order'],
                            'status'      => 'published',
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ]);
                    }
                }

                $sortOrder++;
            }
        }
    }
}
