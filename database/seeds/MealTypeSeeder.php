<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MealTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            ['code' => 'breakfast', 'name' => 'Desayuno', 'sort_order' => 10],
            ['code' => 'lunch', 'name' => 'Almuerzo', 'sort_order' => 20],
            ['code' => 'snack', 'name' => 'Merienda', 'sort_order' => 30],
            ['code' => 'dinner', 'name' => 'Cena', 'sort_order' => 40],
            ['code' => 'collation', 'name' => 'Colacion', 'sort_order' => 50],
        ];

        foreach ($types as $type) {
            DB::table('meal_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
