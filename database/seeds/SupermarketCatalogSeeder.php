<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupermarketCatalogSeeder extends Seeder
{
    public function run()
    {
        DB::table('cities')->updateOrInsert(
            ['name' => 'San Carlos de Bariloche', 'province' => 'Rio Negro', 'country' => 'Argentina'],
            [
                'latitude' => -41.1335,
                'longitude' => -71.3103,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $chains = [
            ['name' => 'Carrefour', 'code' => 'carrefour', 'website_url' => 'https://www.carrefour.com.ar'],
            ['name' => 'ChangoMas', 'code' => 'changomas', 'website_url' => 'https://www.masonline.com.ar'],
            ['name' => 'La Anonima', 'code' => 'la_anonima', 'website_url' => 'https://www.laanonima.com.ar'],
        ];

        foreach ($chains as $chain) {
            DB::table('supermarket_chains')->updateOrInsert(
                ['code' => $chain['code']],
                $chain + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $methods = [
            ['name' => 'Efectivo', 'type' => 'cash', 'issuer' => null],
            ['name' => 'Tarjeta de debito', 'type' => 'debit_card', 'issuer' => null],
            ['name' => 'Tarjeta de credito', 'type' => 'credit_card', 'issuer' => null],
            ['name' => 'Billetera virtual', 'type' => 'wallet', 'issuer' => null],
            ['name' => 'Banco', 'type' => 'bank', 'issuer' => null],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['name' => $method['name'], 'type' => $method['type'], 'issuer' => $method['issuer']],
                $method + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
