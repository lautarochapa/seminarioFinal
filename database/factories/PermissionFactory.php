<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Permission;
use Faker\Generator as Faker;

$factory->define(Permission::class, function (Faker $faker) {
    return [
        'code'        => $faker->unique()->lexify('test.module.????'),
        'module'      => $faker->word,
        'action'      => $faker->randomElement(['read', 'write', 'delete']),
        'description' => $faker->sentence,
        'status'      => 'active',
    ];
});
