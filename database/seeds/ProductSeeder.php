<?php

use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('products')->insert([
            'nombre' => 'producto_1',
            'brand_id' => '1',
            'codigo' => '1234',
            'img' => '1',
            'habilitado' => '1',
            'supply_id' => '1',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_2',
            'brand_id' => '1',
            'codigo' => '1234',
            'img' => '2',
            'habilitado' => '1',
            'supply_id' => '2',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_3',
            'brand_id' => '2',
            'codigo' => '1234',
            'img' => '3',
            'habilitado' => '1',
            'supply_id' => '3',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_4',
            'brand_id' => '2',
            'codigo' => '1234',
            'img' => '4',
            'habilitado' => '1',
            'supply_id' => '4',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_5',
            'brand_id' => '3',
            'codigo' => '1234',
            'img' => '5',
            'habilitado' => '1',
            'supply_id' => '5',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_6',
            'brand_id' => '4',
            'codigo' => '1234',
            'img' => '6',
            'habilitado' => '1',
            'supply_id' => '6',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_7',
            'brand_id' => '5',
            'codigo' => '1234',
            'img' => '7',
            'habilitado' => '1',
            'supply_id' => '7',
        ]);
        DB::table('products')->insert([
            'nombre' => 'producto_8',
            'brand_id' => '5',
            'codigo' => '1234',
            'img' => '8',
            'habilitado' => '0',
            'supply_id' => '7',
        ]);
    }
}

