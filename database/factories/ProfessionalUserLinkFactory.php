<?php

use App\ProfessionalUserLink;
use App\User;
use Faker\Generator as Faker;

$factory->define(ProfessionalUserLink::class, function (Faker $faker) {
    return [
        'user_id'              => function () { return factory(User::class)->create()->id; },
        'professional_user_id' => function () { return factory(User::class)->create()->id; },
        'can_view_profile'     => true,
        'can_view_stock'       => false,
        'can_view_meal_plans'  => true,
        'can_edit_meal_plans'  => false,
        'can_view_reports'     => false,
        'status'               => 'active',
        'granted_at'           => now(),
        'revoked_at'           => null,
    ];
});
