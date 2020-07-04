<?php

use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {        
        

        DB::table('brands')->insert([
            'nombre' => 'marca_1',
            'padre' => '0',
        ]);
        DB::table('brands')->insert([
            'nombre' => 'marca_2',
            'padre' => '0',
        ]);
        DB::table('brands')->insert([
            'nombre' => 'marca_3',
            'padre' => '0',
        ]);
        
        DB::table('brands')->insert([
            'nombre' => 'marca_4',
            'padre' => '1',
        ]);
        DB::table('brands')->insert([
            'nombre' => 'marca_5',
            'padre' => '4',
        ]);
        
    }
}
