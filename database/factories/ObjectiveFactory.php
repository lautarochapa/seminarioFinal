<?php

use App\Objective;
use Faker\Generator as Faker;

$factory->define(Objective::class, function (Faker $faker) {
    return [
        'code'        => $faker->unique()->lexify('objective_????'),
        'name'        => $faker->words(3, true),
        'description' => null,
        'category'    => null,
        'status'      => 'active',
    ];
});
