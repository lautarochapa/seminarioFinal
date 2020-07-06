<?php

use Illuminate\Database\Seeder;

class UtensilsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('utensils')->insert([ 'nombre' => 'Olla','descripcion'=> 'a', 'img'=> '1', ]);
        DB::table('utensils')->insert([ 'nombre' => 'cuchillo carnicero','descripcion'=> 'a', 'img'=> '2', ]);
        DB::table('utensils')->insert([ 'nombre' => 'rallador de queso','descripcion'=> 'a', 'img'=> '3', ]);
        DB::table('utensils')->insert([ 'nombre' => 'pela papa','descripcion'=> 'a', 'img'=> '4', ]);
        DB::table('utensils')->insert([ 'nombre' => 'horno electrico','descripcion'=> 'a', 'img'=> '5', ]);
        DB::table('utensils')->insert([ 'nombre' => 'horno','descripcion'=> 'a', 'img'=> '6', ]);
        DB::table('utensils')->insert([ 'nombre' => 'sarten','descripcion'=> 'a', 'img'=> '7', ]);
        DB::table('utensils')->insert([ 'nombre' => 'wok','descripcion'=> 'a', 'img'=> '8', ]);
        DB::table('utensils')->insert([ 'nombre' => 'abre latas','descripcion'=> 'a', 'img'=> '9', ]);

    }
}


