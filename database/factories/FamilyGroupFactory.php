<?php

use App\FamilyGroup;
use App\User;
use Faker\Generator as Faker;

$factory->define(FamilyGroup::class, function (Faker $faker) {
    return [
        'name'              => $faker->words(3, true),
        'owner_user_id'     => function () { return factory(User::class)->create()->id; },
        'city_id'           => null,
        'default_address'   => null,
        'default_latitude'  => null,
        'default_longitude' => null,
        'status'            => 'active',
    ];
});
