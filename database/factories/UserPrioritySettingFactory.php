<?php

use App\User;
use App\UserPrioritySetting;
use Faker\Generator as Faker;

$factory->define(UserPrioritySetting::class, function (Faker $faker) {
    return [
        'user_id'            => function () { return factory(User::class)->create()->id; },
        'health_weight'      => 0,
        'budget_weight'      => 0,
        'time_weight'        => 0,
        'stock_usage_weight' => 0,
        'preferred_mode'     => null,
    ];
});
