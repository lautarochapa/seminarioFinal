<?php

use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('products')->insert([ 'nombre' => 'Comensal', ]);
        DB::table('products')->insert([ 'nombre' => 'Admin', ]);
        DB::table('products')->insert([ 'nombre' => 'SuperAdmin', ]);
        DB::table('products')->insert([ 'nombre' => 'Somelier', ]);
        DB::table('products')->insert([ 'nombre' => 'Chef', ]);
        DB::table('products')->insert([ 'nombre' => 'Nutricionista', ]);
    }
}
