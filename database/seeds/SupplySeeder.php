<?php

use Illuminate\Database\Seeder;

class SupplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('supplies')->insert([
            'nombre' => 'insumo_1',
            'medida' => 'gramos',
            'cantidad' => '501',
            'category_id' => '11',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_2',
            'medida' => 'gramos',
            'cantidad' => '502',
            'category_id' => '12',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_3',
            'medida' => 'gramos',
            'cantidad' => '503',
            'category_id' => '13',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_4',
            'medida' => 'gramos',
            'cantidad' => '504',
            'category_id' => '14',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_5',
            'medida' => 'gramos',
            'cantidad' => '505',
            'category_id' => '15',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_6',
            'medida' => 'gramos',
            'cantidad' => '506',
            'category_id' => '16',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_7',
            'medida' => 'gramos',
            'cantidad' => '507',
            'category_id' => '17',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_8',
            'medida' => 'gramos',
            'cantidad' => '508',
            'category_id' => '18',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_9',
            'medida' => 'gramos',
            'cantidad' => '509',
            'category_id' => '19',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_10',
            'medida' => 'gramos',
            'cantidad' => '500',
            'category_id' => '20',
        ]);
    }
}
