<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserProfileCatalogSeeder extends Seeder
{
    public function run()
    {
        $objectives = [
            ['code' => 'lose_weight', 'name' => 'Bajar peso', 'category' => 'health'],
            ['code' => 'save_money', 'name' => 'Ahorrar', 'category' => 'budget'],
            ['code' => 'reduce_sodium', 'name' => 'Reducir sodio', 'category' => 'health'],
            ['code' => 'organize_meals', 'name' => 'Organizar comidas', 'category' => 'organization'],
        ];

        foreach ($objectives as $objective) {
            DB::table('objectives')->updateOrInsert(
                ['code' => $objective['code']],
                $objective + ['description' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $restrictions = [
            ['code' => 'vegan', 'name' => 'Vegano'],
            ['code' => 'vegetarian', 'name' => 'Vegetariano'],
            ['code' => 'gluten_free', 'name' => 'Sin gluten'],
            ['code' => 'lactose_free', 'name' => 'Sin lactosa'],
        ];

        foreach ($restrictions as $restriction) {
            DB::table('dietary_restrictions')->updateOrInsert(
                ['code' => $restriction['code']],
                $restriction + ['description' => null, 'status' => 'active']
            );
        }

        $conditions = [
            ['code' => 'diabetes', 'name' => 'Diabetes'],
            ['code' => 'hypertension', 'name' => 'Hipertension'],
            ['code' => 'celiac', 'name' => 'Celiaquia'],
            ['code' => 'high_cholesterol', 'name' => 'Colesterol alto'],
        ];

        foreach ($conditions as $condition) {
            DB::table('health_conditions')->updateOrInsert(
                ['code' => $condition['code']],
                $condition + ['description' => null, 'status' => 'active']
            );
        }

        $allergies = [
            ['code' => 'peanuts', 'name' => 'Mani'],
            ['code' => 'nuts', 'name' => 'Frutos secos'],
            ['code' => 'milk', 'name' => 'Leche'],
            ['code' => 'egg', 'name' => 'Huevo'],
            ['code' => 'fish', 'name' => 'Pescado'],
            ['code' => 'shellfish', 'name' => 'Mariscos'],
        ];

        foreach ($allergies as $allergy) {
            DB::table('allergies')->updateOrInsert(
                ['code' => $allergy['code']],
                $allergy + ['description' => null, 'status' => 'active']
            );
        }
    }
}
