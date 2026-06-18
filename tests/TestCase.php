<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        if ($this->app) {
            try {
                foreach (array_keys(config('database.connections', [])) as $name) {
                    $connection = DB::connection($name);
                    $connection->disableQueryLog();
                    $connection->flushQueryLog();
                }
            } catch (\Throwable $e) {
            }
        }

        parent::tearDown();

        Facade::clearResolvedInstances();

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
