<?php

use App\BodyMeasurement;
use App\User;
use Faker\Generator as Faker;

$factory->define(BodyMeasurement::class, function (Faker $faker) {
    return [
        'user_id'                  => function () { return factory(User::class)->create()->id; },
        'measurement_date'         => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        'weight_kg'                => $faker->randomFloat(2, 40, 150),
        'waist_cm'                 => null,
        'blood_pressure_systolic'  => null,
        'blood_pressure_diastolic' => null,
        'glucose_level'            => null,
        'notes'                    => null,
    ];
});
