<?php

namespace App\Services\Supermarkets;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Supermarkets\SupermarketChainRepository;
use App\SupermarketChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupermarketChainService
{
    private $chains;

    public function __construct(SupermarketChainRepository $chains)
    {
        $this->chains = $chains;
    }

    public function list(array $filters)
    {
        return $this->chains->paginate($filters);
    }

    public function show(int $id)
    {
        return $this->chains->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $userAgent)
    {
        $name = $data['name'];

        if ($this->chains->nameExists($name)) {
            throw new IngredientException('CHAIN_NAME_ALREADY_EXISTS', 'Ya existe una cadena con ese nombre.', 409);
        }

        $code = $this->generateCode($name);

        return DB::transaction(function () use ($actorId, $data, $name, $code, $ip, $userAgent) {
            $chain = $this->chains->create([
                'name'        => $name,
                'code'        => $code,
                'website_url' => $data['website_url'] ?? null,
                'status'      => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'chain.created',
                'entity_name' => 'supermarket_chains',
                'entity_id'   => (string) $chain->id,
                'old_values'  => null,
                'new_values'  => $this->auditPayload($chain),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $chain;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $chain = $this->chains->findOrFail($id);

        if (isset($data['name']) && $data['name'] !== $chain->name) {
            if ($this->chains->nameExists($data['name'], $chain->id)) {
                throw new IngredientException('CHAIN_NAME_ALREADY_EXISTS', 'Ya existe una cadena con ese nombre.', 409);
            }
        }

        return DB::transaction(function () use ($actorId, $chain, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($chain);
            $fillable = array_intersect_key($data, array_flip(['name', 'website_url']));
            $updated  = $this->chains->update($chain, $fillable);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'chain.updated',
                'entity_name' => 'supermarket_chains',
                'entity_id'   => (string) $chain->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function delete(int $actorId, int $id, string $ip, string $userAgent)
    {
        $chain = $this->chains->findOrFail($id);

        return DB::transaction(function () use ($actorId, $chain, $ip, $userAgent) {
            $old     = $this->auditPayload($chain);
            $deleted = $this->chains->softDelete($chain);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'chain.deleted',
                'entity_name' => 'supermarket_chains',
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
        $chain = $this->chains->findWithTrashedOrFail($id);

        if (! $chain->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'La cadena no está eliminada.', 409);
        }

        if ($this->chains->nameExists($chain->name, $chain->id)) {
            throw new IngredientException('CHAIN_NAME_ALREADY_EXISTS', 'Ya existe una cadena activa con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $chain, $ip, $userAgent) {
            $old      = $this->auditPayload($chain);
            $restored = $this->chains->restore($chain);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'chain.restored',
                'entity_name' => 'supermarket_chains',
                'entity_id'   => (string) $restored->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($restored),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $restored;
        });
    }

    public function catalog()
    {
        return $this->chains->allActive();
    }

    public function catalogShow(int $id)
    {
        return $this->chains->findActiveOrFail($id);
    }

    private function generateCode(string $name): string
    {
        $base = Str::slug($name, '_');
        if (! $this->chains->codeExists($base)) {
            return $base;
        }
        return $base . '_' . Str::random(6);
    }

    private function auditPayload(SupermarketChain $chain): array
    {
        return [
            'name'        => $chain->name,
            'website_url' => $chain->website_url,
            'status'      => $chain->status,
            'deleted_at'  => $chain->deleted_at ? (string) $chain->deleted_at : null,
        ];
    }
}
