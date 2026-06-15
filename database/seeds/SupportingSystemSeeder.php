<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupportingSystemSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        foreach ([
            ['code' => 'app', 'name' => 'App'],
            ['code' => 'email', 'name' => 'Email'],
            ['code' => 'push', 'name' => 'Push'],
        ] as $channel) {
            DB::table('notification_channels')->updateOrInsert(
                ['code' => $channel['code']],
                [
                    'name' => $channel['name'],
                    'description' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['key' => 'module.ai', 'name' => 'IA', 'enabled' => false],
            ['key' => 'module.scraping', 'name' => 'Scraping', 'enabled' => true],
            ['key' => 'auth.google', 'name' => 'Google Auth', 'enabled' => false],
        ] as $flag) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $flag['key']],
                [
                    'name' => $flag['name'],
                    'description' => null,
                    'enabled' => $flag['enabled'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
