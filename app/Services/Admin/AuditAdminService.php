<?php

namespace App\Services\Admin;

use App\Exceptions\Rbac\RbacException;
use App\Ingredient;
use App\IngredientEquivalence;
use App\Nutrient;
use App\Permission;
use App\Repositories\Admin\AuditLogRepository;
use App\Role;
use App\UnitConversion;
use App\UnitMeasure;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuditAdminService
{
    const ALLOWED_RESOURCES = [
        'users'       => User::class,
        'roles'       => Role::class,
        'permissions' => Permission::class,
        'ingredients' => Ingredient::class,
        'nutrients' => Nutrient::class,
        'units' => UnitMeasure::class,
        'unit_measures' => UnitMeasure::class,
        'unit-conversions' => UnitConversion::class,
        'unit_conversions' => UnitConversion::class,
        'ingredient-equivalences' => IngredientEquivalence::class,
        'ingredient_equivalences' => IngredientEquivalence::class,
    ];

    const ENTITY_NAMES = [
        'units' => 'unit_measures',
        'unit-conversions' => 'unit_conversions',
        'ingredient-equivalences' => 'ingredient_equivalences',
    ];

    private $auditRepo;

    public function __construct(AuditLogRepository $auditRepo)
    {
        $this->auditRepo = $auditRepo;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->auditRepo->paginate($filters);
    }

    public function forEntity(string $entityName, int $entityId, array $filters): LengthAwarePaginator
    {
        $filters['entity_name'] = $entityName;
        $filters['entity_id']   = (string) $entityId;
        unset($filters['resource'], $filters['resource_id']);

        return $this->auditRepo->paginate($filters);
    }

    public function forResource(string $resource, $id, array $filters): LengthAwarePaginator
    {
        if (!array_key_exists($resource, self::ALLOWED_RESOURCES)) {
            throw new ModelNotFoundException("Tipo de recurso no permitido: {$resource}");
        }

        if ($resource === 'users' || $resource === 'ingredients') {
            $entity = User::withTrashed()->find($id);
            if ($resource === 'ingredients') {
                $entity = Ingredient::withTrashed()->find($id);
            }
        } else {
            $modelClass = self::ALLOWED_RESOURCES[$resource];
            $entity     = $modelClass::find($id);
        }

        if (!$entity) {
            throw new ModelNotFoundException();
        }

        $entityName = self::ENTITY_NAMES[$resource] ?? $resource;

        return $this->forEntity($entityName, (int) $id, $filters);
    }
}
