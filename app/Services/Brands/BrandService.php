<?php

namespace App\Services\Brands;

use App\AuditLog;
use App\Brand;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Brands\BrandRepository;
use Illuminate\Support\Facades\DB;

class BrandService
{
    private $brands;

    public function __construct(BrandRepository $brands)
    {
        $this->brands = $brands;
    }

    public function list(array $filters)
    {
        return $this->brands->paginate($filters);
    }

    public function publicList(array $filters)
    {
        return $this->brands->paginate($filters, true);
    }

    public function show($id)
    {
        return $this->brands->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data, true);

        if ($this->brands->activeNameExists($data['normalized_name'])) {
            throw new IngredientException('BRAND_NAME_ALREADY_EXISTS', 'Ya existe una marca activa con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $brand = $this->brands->create($data);
            $this->audit($actorId, 'brand.created', $brand->id, null, $this->auditPayload($brand), $ip, $userAgent);

            return $brand;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $brand = $this->brands->findOrFail($id);
        $data = $this->prepare($data, false, $brand);

        if (array_key_exists('normalized_name', $data) && $this->brands->activeNameExists($data['normalized_name'], $brand->id)) {
            throw new IngredientException('BRAND_NAME_ALREADY_EXISTS', 'Ya existe una marca activa con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $brand, $data, $ip, $userAgent) {
            $old = $this->auditPayload($brand);
            $updated = $this->brands->update($brand, $data);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'brand.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $brand = $this->brands->findOrFail($id);

        return DB::transaction(function () use ($actorId, $brand, $ip, $userAgent) {
            $old = $this->auditPayload($brand);
            $brand->status = 'inactive';
            $brand->save();
            $brand->delete();
            $deleted = $this->brands->findWithTrashedOrFail($brand->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'brand.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $brand = $this->brands->findWithTrashedOrFail($id);

        if (! $brand->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no está eliminado.', 409);
        }

        if ($this->brands->activeNameExists($brand->normalized_name, $brand->id)) {
            throw new IngredientException('BRAND_NAME_ALREADY_EXISTS', 'Ya existe una marca activa con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $brand, $ip, $userAgent) {
            $old = $this->auditPayload($brand);
            $brand->restore();
            $brand->status = 'active';
            $brand->save();
            $restored = $brand->fresh();
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'brand.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    private function prepare(array $data, $creating, Brand $brand = null)
    {
        foreach (['name', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating || array_key_exists('name', $data)) {
            $name = $data['name'] ?? ($brand ? $brand->name : null);
            $data['name'] = trim((string) $name);
            $data['nombre'] = $data['name'];
            $data['normalized_name'] = $this->normalizeName($data['name']);
        }

        if ($creating && ! array_key_exists('status', $data)) {
            $data['status'] = 'active';
        }

        if ($creating && ! array_key_exists('padre', $data)) {
            $data['padre'] = 0;
        }

        return array_intersect_key($data, array_flip([
            'name',
            'normalized_name',
            'status',
            'nombre',
            'padre',
        ]));
    }

    private function normalizeName($name)
    {
        return strtolower(trim((string) $name));
    }

    private function auditPayload(Brand $brand)
    {
        return [
            'name' => $brand->name,
            'normalized_name' => $brand->normalized_name,
            'status' => $brand->status,
            'nombre' => $brand->nombre,
            'padre' => $brand->padre,
            'deleted_at' => $brand->deleted_at ? (string) $brand->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'brands',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
