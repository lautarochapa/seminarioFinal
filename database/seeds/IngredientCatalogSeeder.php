<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IngredientCatalogSeeder extends Seeder
{
    public function run()
    {
        $units = [
            ['code' => 'g', 'name' => 'Gramo', 'type' => 'weight', 'symbol' => 'g'],
            ['code' => 'kg', 'name' => 'Kilogramo', 'type' => 'weight', 'symbol' => 'kg'],
            ['code' => 'ml', 'name' => 'Mililitro', 'type' => 'volume', 'symbol' => 'ml'],
            ['code' => 'l', 'name' => 'Litro', 'type' => 'volume', 'symbol' => 'l'],
            ['code' => 'unit', 'name' => 'Unidad', 'type' => 'count', 'symbol' => 'u'],
            ['code' => 'cup', 'name' => 'Taza', 'type' => 'volume', 'symbol' => 'taza'],
            ['code' => 'tbsp', 'name' => 'Cucharada', 'type' => 'volume', 'symbol' => 'cda'],
            ['code' => 'package', 'name' => 'Paquete', 'type' => 'package', 'symbol' => 'paq'],
            ['code' => 'kcal', 'name' => 'Kilocaloria', 'type' => 'energy', 'symbol' => 'kcal'],
            ['code' => 'mg', 'name' => 'Miligramo', 'type' => 'weight', 'symbol' => 'mg'],
        ];

        foreach ($units as $unit) {
            DB::table('unit_measures')->updateOrInsert(
                ['code' => $unit['code']],
                $unit + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $unitIds = DB::table('unit_measures')->pluck('id', 'code');

        $nutrients = [
            ['code' => 'calories', 'name' => 'Calorias', 'unit_id' => $unitIds['kcal']],
            ['code' => 'protein', 'name' => 'Proteinas', 'unit_id' => $unitIds['g']],
            ['code' => 'carbohydrates', 'name' => 'Carbohidratos', 'unit_id' => $unitIds['g']],
            ['code' => 'fat', 'name' => 'Grasas', 'unit_id' => $unitIds['g']],
            ['code' => 'sodium', 'name' => 'Sodio', 'unit_id' => $unitIds['mg']],
            ['code' => 'sugar', 'name' => 'Azucar', 'unit_id' => $unitIds['g']],
            ['code' => 'fiber', 'name' => 'Fibra', 'unit_id' => $unitIds['g']],
        ];

        foreach ($nutrients as $nutrient) {
            DB::table('nutrients')->updateOrInsert(
                ['code' => $nutrient['code']],
                $nutrient + ['description' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $tags = [
            ['code' => 'low_sodium', 'name' => 'Bajo sodio', 'type' => 'nutrition'],
            ['code' => 'gluten_free', 'name' => 'Sin gluten', 'type' => 'restriction'],
            ['code' => 'high_sugar', 'name' => 'Alto azucar', 'type' => 'nutrition'],
            ['code' => 'vegan', 'name' => 'Vegano', 'type' => 'diet'],
            ['code' => 'lactose_free', 'name' => 'Sin lactosa', 'type' => 'restriction'],
        ];

        foreach ($tags as $tag) {
            DB::table('food_tags')->updateOrInsert(
                ['code' => $tag['code']],
                $tag + ['description' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $conversions = [
            ['from' => 'kg', 'to' => 'g', 'factor' => 1000],
            ['from' => 'g', 'to' => 'kg', 'factor' => 0.001],
            ['from' => 'l', 'to' => 'ml', 'factor' => 1000],
            ['from' => 'ml', 'to' => 'l', 'factor' => 0.001],
        ];

        foreach ($conversions as $conversion) {
            DB::table('unit_conversions')->updateOrInsert(
                [
                    'from_unit_id' => $unitIds[$conversion['from']],
                    'to_unit_id' => $unitIds[$conversion['to']],
                    'ingredient_id' => null,
                ],
                [
                    'factor' => $conversion['factor'],
                    'notes' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
