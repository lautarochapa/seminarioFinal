<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplementTypeSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $types = [
            ['code' => 'protein', 'name' => 'Proteina'],
            ['code' => 'creatine', 'name' => 'Creatina'],
            ['code' => 'vitamins', 'name' => 'Vitaminas'],
            ['code' => 'minerals', 'name' => 'Minerales'],
            ['code' => 'other', 'name' => 'Otros'],
        ];

        foreach ($types as $type) {
            DB::table('supplement_types')->updateOrInsert(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
