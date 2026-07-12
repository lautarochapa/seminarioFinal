<?php

use App\FamilyGroup;
use App\FamilyGroupMember;
use App\User;
use Faker\Generator as Faker;

$factory->define(FamilyGroupMember::class, function (Faker $faker) {
    return [
        'family_group_id' => function () { return factory(FamilyGroup::class)->create()->id; },
        'user_id'         => function () { return factory(User::class)->create()->id; },
        'role_in_group'   => 'member',
        'status'          => 'active',
        'joined_at'       => now(),
    ];
});
