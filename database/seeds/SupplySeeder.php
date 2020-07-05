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
       /*  DB::table('supplies')->insert([
            'nombre' => 'insumo_1',
            'medida' => 'gramos',
            'cantidad' => '501',
            ''category_id' => '11',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_2',
            'medida' => 'gramos',
            'cantidad' => '502',
            ''category_id' => '12',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_3',
            'medida' => 'gramos',
            'cantidad' => '503',
            ''category_id' => '13',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_4',
            'medida' => 'gramos',
            'cantidad' => '504',
            ''category_id' => '14',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_5',
            'medida' => 'gramos',
            'cantidad' => '505',
            ''category_id' => '15',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_6',
            'medida' => 'gramos',
            'cantidad' => '506',
            ''category_id' => '16',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_7',
            'medida' => 'gramos',
            'cantidad' => '507',
            ''category_id' => '17',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_8',
            'medida' => 'gramos',
            'cantidad' => '508',
            ''category_id' => '18',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_9',
            'medida' => 'gramos',
            'cantidad' => '509',
            ''category_id' => '19',
        ]);
        DB::table('supplies')->insert([
            'nombre' => 'insumo_10',
            'medida' => 'gramos',
            'cantidad' => '500',
            ''category_id' => '20',
        ]);*/


        DB::table('supplies')->insert([ 'nombre' => 'Alfajores de arroz','medida'=> 'gramos', 'category_id'=> '3', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Alfajores','medida'=> 'gramos', 'category_id'=> '3', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Bombones y bocaditos','medida'=> 'gramos', 'category_id'=> '4', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Caramelos y chupetines','medida'=> 'gramos', 'category_id'=> '5', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Chicles Y Pastillas','medida'=> 'gramos', 'category_id'=> '6', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Aceitunas','medida'=> 'gramos', 'category_id'=> '11', ]);
        DB::table('supplies')->insert([ 'nombre' => 'AJÍ','medida'=> 'gramos', 'category_id'=> '12', ]);
        DB::table('supplies')->insert([ 'nombre' => 'ALCAPARRAS','medida'=> 'gramos', 'category_id'=> '13', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Alimentos para bebé','medida'=> 'gramos', 'category_id'=> '16', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Infusiones infantiles','medida'=> 'gramos', 'category_id'=> '17', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Mezcla lista','medida'=> 'gramos', 'category_id'=> '53', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Sopas y saborizantes','medida'=> 'gramos', 'category_id'=> '54', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Azúcar','medida'=> 'gramos', 'category_id'=> '8', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Edulcorante','medida'=> 'gramos', 'category_id'=> '9', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Harina de trigo','medida'=> 'gramos', 'category_id'=> '19', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Harina de maíz','medida'=> 'gramos', 'category_id'=> '20', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Premezcla','medida'=> 'gramos', 'category_id'=> '21', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Sémola','medida'=> 'gramos', 'category_id'=> '22', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Arroz','medida'=> 'gramos', 'category_id'=> '56', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Legumbres ','medida'=> 'gramos', 'category_id'=> '57', ]);
        DB::table('supplies')->insert([ 'nombre' => 'Cereales ','medida'=> 'gramos', 'category_id'=> '72', ]);

    }
}
