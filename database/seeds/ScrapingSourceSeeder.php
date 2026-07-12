<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScrapingSourceSeeder extends Seeder
{
    public function run()
    {
        $cityId = DB::table('cities')->value('id');

        $sources = [
            [
                'code'     => 'carrefour',
                'name'     => 'Carrefour',
                'type'     => 'web_scraper',
                'base_url' => 'https://www.carrefour.com.ar',
                'city_id'  => $cityId,
            ],
            [
                'code'     => 'changomas',
                'name'     => 'ChangoMas',
                'type'     => 'web_scraper',
                'base_url' => 'https://www.masonline.com.ar',
                'city_id'  => $cityId,
            ],
            [
                'code'     => 'la_anonima',
                'name'     => 'La Anonima',
                'type'     => 'web_scraper',
                'base_url' => 'https://www.laanonima.com.ar',
                'city_id'  => $cityId,
            ],
            [
                'code'     => 'cookpad',
                'name'     => 'Cookpad Argentina',
                'type'     => 'web_scraper',
                'base_url' => 'https://cookpad.com/ar',
                'city_id'  => null,
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
