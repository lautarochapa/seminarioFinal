<?php

use App\FamilyGroup;
use App\FamilyGroupPreference;
use Faker\Generator as Faker;

$factory->define(FamilyGroupPreference::class, function (Faker $faker) {
    return [
        'family_group_id'              => function () { return factory(FamilyGroup::class)->create()->id; },
        'default_budget_mode'          => null,
        'default_shopping_mode'        => null,
        'default_recipe_priority_mode' => null,
        'allow_auto_stock_discount'    => true,
    ];
});
