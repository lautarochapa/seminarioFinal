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
            'nombre' => 'Coca-Cola 600',
            'marca' => 'Coca-Cola Company',
            'codigo' => '1234',
        ]);
    }
}

