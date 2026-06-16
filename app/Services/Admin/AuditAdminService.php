<?php

namespace App\Services\Admin;

use App\Exceptions\Rbac\RbacException;
use App\Permission;
use App\Repositories\Admin\AuditLogRepository;
use App\Role;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuditAdminService
{
    const ALLOWED_RESOURCES = [
        'users'       => User::class,
        'roles'       => Role::class,
        'permissions' => Permission::class,
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

        if ($resource === 'users') {
            $entity = User::withTrashed()->find($id);
        } else {
            $modelClass = self::ALLOWED_RESOURCES[$resource];
            $entity     = $modelClass::find($id);
        }

        if (!$entity) {
            throw new ModelNotFoundException();
        }

        return $this->forEntity($resource, (int) $id, $filters);
    }
}
