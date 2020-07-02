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
        DB::table('profiles')->insert([ 'nombre' => 'Comensal', ]);
        DB::table('profiles')->insert([ 'nombre' => 'Admin', ]);
        DB::table('profiles')->insert([ 'nombre' => 'SuperAdmin', ]);
        DB::table('profiles')->insert([ 'nombre' => 'Somelier', ]);
        DB::table('profiles')->insert([ 'nombre' => 'Chef', ]);
        DB::table('profiles')->insert([ 'nombre' => 'Nutricionista', ]);
    }
}
