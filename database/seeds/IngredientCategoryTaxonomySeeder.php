<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IngredientCategoryTaxonomySeeder extends Seeder
{
    public function run()
    {
        $path = database_path('data/ingredient_categories.json');
        $categories = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Error decodificando ingredient_categories.json: '.json_last_error_msg());
        }

        DB::transaction(function () use ($categories) {
            foreach ($categories as $category) {
                $parentId = null;

                if (! empty($category['parent_code'])) {
                    $parentId = DB::table('ingredient_categories')
                        ->where('code', $category['parent_code'])
                        ->value('id');
                }

                DB::table('ingredient_categories')->updateOrInsert(
                    ['code' => $category['code']],
                    [
                        'parent_id' => $parentId,
                        'name' => $category['name'],
                        'description' => $category['description'] ?? null,
                        'sort_order' => $category['sort_order'] ?? 0,
                        'is_active' => $category['is_active'] ?? true,
                        'status' => ! empty($category['is_active']) ? 'active' : 'inactive',
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
