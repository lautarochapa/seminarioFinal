<?php

namespace App\Services\PaymentMethods;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\PaymentMethods\PaymentMethodRepository;
use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    private $repo;

    public function __construct(PaymentMethodRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function show(int $id)
    {
        return $this->repo->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $userAgent)
    {
        if ($this->repo->existsDuplicate($data['name'], $data['type'], $data['issuer'] ?? null)) {
            throw new IngredientException('PAYMENT_METHOD_ALREADY_EXISTS', 'Ya existe un metodo de pago con ese nombre, tipo y emisor.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $pm = $this->repo->create([
                'name'   => $data['name'],
                'type'   => $data['type'],
                'issuer' => $data['issuer'] ?? null,
                'status' => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'payment_method.created',
                'entity_name' => 'payment_methods',
                'entity_id'   => (string) $pm->id,
                'old_values'  => null,
                'new_values'  => $this->auditPayload($pm),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $pm;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $pm = $this->repo->findOrFail($id);

        return DB::transaction(function () use ($actorId, $pm, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($pm);
            $allowed = array_intersect_key($data, array_flip(['name', 'type', 'issuer']));
            $updated = $this->repo->update($pm, $allowed);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'payment_method.updated',
                'entity_name' => 'payment_methods',
                'entity_id'   => (string) $updated->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function destroy(int $actorId, int $id, string $ip, string $userAgent)
    {
        $pm = $this->repo->findOrFail($id);

        if ($pm->status === 'inactive') {
            throw new IngredientException('RESOURCE_ALREADY_DELETED', 'El metodo de pago ya esta inactivo.', 409);
        }

        return DB::transaction(function () use ($actorId, $pm, $ip, $userAgent) {
            $old     = $this->auditPayload($pm);
            $deleted = $this->repo->softDelete($pm);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'payment_method.deleted',
                'entity_name' => 'payment_methods',
                'entity_id'   => (string) $deleted->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($deleted),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $deleted;
        });
    }

    public function restore(int $actorId, int $id, string $ip, string $userAgent)
    {
        $pm = $this->repo->findWithTrashedOrFail($id);

        if (! $pm->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El metodo de pago no esta eliminado.', 409);
        }

        return DB::transaction(function () use ($actorId, $pm, $ip, $userAgent) {
            $old      = $this->auditPayload($pm);
            $restored = $this->repo->restore($pm);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'payment_method.restored',
                'entity_name' => 'payment_methods',
                'entity_id'   => (string) $restored->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($restored),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $restored;
        });
    }

    public function catalog(array $filters)
    {
        return $this->repo->catalog($filters);
    }

    public function userMethods(int $userId)
    {
        return $this->repo->userMethods($userId);
    }

    public function addUserMethod(int $actorId, array $data, string $ip, string $userAgent)
    {
        $pmId = (int) $data['payment_method_id'];

        $pm = $this->repo->findActiveById($pmId);
        if (! $pm) {
            throw new IngredientException('PAYMENT_METHOD_NOT_FOUND', 'Metodo de pago no encontrado o inactivo.', 422);
        }

        if ($this->repo->hasActiveUserMethod($actorId, $pmId)) {
            throw new IngredientException('USER_PAYMENT_METHOD_ALREADY_EXISTS', 'Ya tenes activo este metodo de pago.', 409);
        }

        return DB::transaction(function () use ($actorId, $pmId, $data, $ip, $userAgent) {
            $upm = $this->repo->addUserMethod($actorId, $pmId, $data['alias'] ?? null);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user_payment_method.added',
                'entity_name' => 'user_payment_methods',
                'entity_id'   => (string) $upm->id,
                'old_values'  => null,
                'new_values'  => [
                    'payment_method_id' => $pmId,
                    'alias'             => $upm->alias,
                ],
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $upm;
        });
    }

    public function removeUserMethod(int $actorId, int $id, string $ip, string $userAgent)
    {
        $upm = $this->repo->findUserMethodForOwner($id, $actorId);

        if (! $upm) {
            throw new IngredientException('USER_PAYMENT_METHOD_NOT_FOUND', 'Registro no encontrado.', 404);
        }

        return DB::transaction(function () use ($actorId, $upm, $ip, $userAgent) {
            $deactivated = $this->repo->deactivateUserMethod($upm);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user_payment_method.removed',
                'entity_name' => 'user_payment_methods',
                'entity_id'   => (string) $deactivated->id,
                'old_values'  => ['status' => 'active'],
                'new_values'  => ['status' => 'inactive'],
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $deactivated;
        });
    }

    private function auditPayload($pm): array
    {
        return [
            'name'   => $pm->name,
            'type'   => $pm->type,
            'issuer' => $pm->issuer,
            'status' => $pm->status,
        ];
    }
}
