<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IngredientSupportingTaxonomiesSeeder extends Seeder
{
    public function run()
    {
        $path = database_path('data/ingredient_supporting_taxonomies.json');
        $data = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Error decodificando ingredient_supporting_taxonomies.json: '.json_last_error_msg());
        }

        DB::transaction(function () use ($data) {
            foreach ($data['food_tags'] as $tag) {
                DB::table('food_tags')->updateOrInsert(
                    ['code' => $tag['code']],
                    [
                        'name' => $tag['name'],
                        'description' => $tag['description'] ?? null,
                        'type' => $tag['type'] ?? null,
                        'status' => $tag['status'] ?? 'active',
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($data['allergies'] as $allergy) {
                DB::table('allergies')->updateOrInsert(
                    ['code' => $allergy['code']],
                    [
                        'name' => $allergy['name'],
                        'description' => $allergy['description'] ?? null,
                        'status' => $allergy['status'] ?? 'active',
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
