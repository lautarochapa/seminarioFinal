<?php

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('users')->insert([
            'name' => 'Lautaro',
            'email' => 'lautaro.chiappero@davinci.edu.ar',
            'password' => Hash::make('1234'),
            'nivel_acceso' => '1',
        ]);

        DB::table('users')->insert([
            'name' => 'SuperLautaro',
            'email' => 'lautarochapa@gmail.com',
            'password' => Hash::make('1234'),
            'nivel_acceso' => '2',
        ]);
    }
}
