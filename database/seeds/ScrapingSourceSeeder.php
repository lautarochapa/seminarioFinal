<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScrapingSourceSeeder extends Seeder
{
    public function run()
    {
        $barilocheId = DB::table('cities')
            ->where('name', 'San Carlos de Bariloche')
            ->where('province', 'Rio Negro')
            ->value('id');

        $sources = [
            [
                'code' => 'carrefour_bariloche',
                'name' => 'Carrefour Bariloche',
                'type' => 'supermarket',
                'base_url' => 'https://www.carrefour.com.ar',
                'city_id' => $barilocheId,
            ],
            [
                'code' => 'changomas_bariloche',
                'name' => 'ChangoMas Bariloche',
                'type' => 'supermarket',
                'base_url' => 'https://www.masonline.com.ar',
                'city_id' => $barilocheId,
            ],
            [
                'code' => 'la_anonima_bariloche',
                'name' => 'La Anonima Bariloche',
                'type' => 'supermarket',
                'base_url' => 'https://www.laanonima.com.ar',
                'city_id' => $barilocheId,
            ],
            [
                'code' => 'cookpad_argentina',
                'name' => 'Cookpad Argentina',
                'type' => 'recipe',
                'base_url' => 'https://cookpad.com/ar',
                'city_id' => null,
            ],
        ];

        foreach ($sources as $source) {
            DB::table('scraping_sources')->updateOrInsert(
                ['code' => $source['code']],
                $source + [
                    'is_active' => true,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
