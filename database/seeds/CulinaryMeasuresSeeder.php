<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CulinaryMeasuresSeeder extends Seeder
{
    public function run()
    {
        $catalog = json_decode(file_get_contents(database_path('data/culinary-measures.json')), true, 32, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($catalog) {
            foreach ($catalog['units'] as $unit) {
                // Existing units may have been customized; never overwrite them.
                if (!DB::table('unit_measures')->where('code', $unit['code'])->exists()) {
                    DB::table('unit_measures')->insert($unit + ['created_at'=>now(), 'updated_at'=>now()]);
                }
            }
            $ids = DB::table('unit_measures')->where('status', 'active')->pluck('id', 'code');
            foreach ($catalog['conversions'] as $conversion) {
                if (!isset($ids[$conversion['from_code']], $ids[$conversion['to_code']])) continue;
                $identity = ['from_unit_id'=>$ids[$conversion['from_code']], 'to_unit_id'=>$ids[$conversion['to_code']], 'ingredient_id'=>null];
                if (!DB::table('unit_conversions')->where($identity)->exists()) {
                    DB::table('unit_conversions')->insert($identity + ['factor'=>$conversion['factor'], 'notes'=>'Equivalencia exacta de volumen SI.', 'status'=>'active', 'created_at'=>now(), 'updated_at'=>now()]);
                }
            }
        });
    }
}
