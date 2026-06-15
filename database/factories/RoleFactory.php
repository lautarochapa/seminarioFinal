<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Role;
use Faker\Generator as Faker;

$factory->define(Role::class, function (Faker $faker) {
    return [
        'code'        => $faker->unique()->lexify('role_????'),
        'name'        => $faker->words(2, true),
        'description' => $faker->sentence,
        'status'      => 'active',
    ];
});
