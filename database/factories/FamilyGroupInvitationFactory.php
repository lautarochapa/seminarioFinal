<?php

use App\FamilyGroup;
use App\FamilyGroupInvitation;
use App\User;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(FamilyGroupInvitation::class, function (Faker $faker) {
    return [
        'family_group_id' => function () { return factory(FamilyGroup::class)->create()->id; },
        'invited_email'   => $faker->unique()->safeEmail,
        'invited_user_id' => null,
        'invited_by'      => function () { return factory(User::class)->create()->id; },
        'token'           => Str::random(64),
        'status'          => 'pending',
        'expires_at'      => now()->addDays(7),
        'accepted_at'     => null,
        'rejected_at'     => null,
    ];
});
