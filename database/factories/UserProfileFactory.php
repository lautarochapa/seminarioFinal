<?php

use App\User;
use App\UserProfile;
use Faker\Generator as Faker;

$factory->define(UserProfile::class, function (Faker $faker) {
    return [
        'user_id'                  => function () { return factory(User::class)->create()->id; },
        'birth_date'               => null,
        'gender'                   => null,
        'height_cm'                => null,
        'current_weight_kg'        => null,
        'target_weight_kg'         => null,
        'activity_level'           => null,
        'meals_per_day'            => null,
        'uses_app_for_health'      => false,
        'uses_app_for_budget'      => false,
        'uses_app_for_organization'=> false,
        'notes'                    => null,
    ];
});
