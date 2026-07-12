<?php

namespace App\Support;

use App\Allergy;
use App\DietaryRestriction;
use App\HealthCondition;
use App\UserAllergy;
use App\UserDietaryRestriction;
use App\UserHealthCondition;

class HealthPreferenceTypes
{
    public static function all()
    {
        return [
            'dietary-restrictions' => [
                'model' => DietaryRestriction::class,
                'relation_model' => UserDietaryRestriction::class,
                'table' => 'dietary_restrictions',
                'relation_table' => 'user_dietary_restrictions',
                'relation_fk' => 'dietary_restriction_id',
                'relation_name' => 'dietaryRestriction',
                'route' => 'dietary-restrictions',
                'resource' => 'dietary_restrictions',
                'uses_soft_delete' => false,
                'extra_relation_fields' => [],
            ],
            'health-conditions' => [
                'model' => HealthCondition::class,
                'relation_model' => UserHealthCondition::class,
                'table' => 'health_conditions',
                'relation_table' => 'user_health_conditions',
                'relation_fk' => 'health_condition_id',
                'relation_name' => 'healthCondition',
                'route' => 'health-conditions',
                'resource' => 'health_conditions',
                'uses_soft_delete' => false,
                'extra_relation_fields' => [],
            ],
            'allergies' => [
                'model' => Allergy::class,
                'relation_model' => UserAllergy::class,
                'table' => 'allergies',
                'relation_table' => 'user_allergies',
                'relation_fk' => 'allergy_id',
                'relation_name' => 'allergy',
                'route' => 'allergies',
                'resource' => 'allergies',
                'uses_soft_delete' => true,
                'extra_relation_fields' => ['severity'],
            ],
        ];
    }

    public static function get($type)
    {
        $all = static::all();

        return $all[$type] ?? null;
    }
}
