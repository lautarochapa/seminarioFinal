<?php

namespace App\Services\FoodTags;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\FoodTag;
use App\Repositories\FoodTags\FoodTagRepository;
use Illuminate\Support\Facades\DB;

class FoodTagService
{
    private $tags;

    public function __construct(FoodTagRepository $tags)
    {
        $this->tags = $tags;
    }

    public function list(array $filters)
    {
        return $this->tags->paginate($filters);
    }

    public function publicList(array $filters)
    {
        return $this->tags->paginate($filters, true);
    }

    public function show($id)
    {
        return $this->tags->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data, true);

        if ($this->tags->activeCodeExists($data['code'])) {
            throw new IngredientException('FOOD_TAG_CODE_ALREADY_EXISTS', 'Ya existe un tag activo con ese código.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $tag = $this->tags->create($data);
            $this->audit($actorId, 'food-tag.created', $tag->id, null, $this->auditPayload($tag), $ip, $userAgent);

            return $tag;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $tag = $this->tags->findOrFail($id);
        $data = $this->prepare($data, false);

        if (array_key_exists('code', $data) && $this->tags->activeCodeExists($data['code'], $tag->id)) {
            throw new IngredientException('FOOD_TAG_CODE_ALREADY_EXISTS', 'Ya existe un tag activo con ese código.', 409);
        }

        return DB::transaction(function () use ($actorId, $tag, $data, $ip, $userAgent) {
            $old = $this->auditPayload($tag);
            $updated = $this->tags->update($tag, $data);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'food-tag.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $tag = $this->tags->findOrFail($id);

        return DB::transaction(function () use ($actorId, $tag, $ip, $userAgent) {
            $old = $this->auditPayload($tag);
            $tag->status = 'inactive';
            $tag->save();
            $tag->delete();
            $deleted = $this->tags->findWithTrashedOrFail($tag->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'food-tag.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $tag = $this->tags->findWithTrashedOrFail($id);

        if (! $tag->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no está eliminado.', 409);
        }

        if ($this->tags->activeCodeExists($tag->code, $tag->id)) {
            throw new IngredientException('FOOD_TAG_CODE_ALREADY_EXISTS', 'Ya existe un tag activo con ese código.', 409);
        }

        return DB::transaction(function () use ($actorId, $tag, $ip, $userAgent) {
            $old = $this->auditPayload($tag);
            $tag->restore();
            $tag->status = 'active';
            $tag->save();
            $restored = $tag->fresh();
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'food-tag.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    private function prepare(array $data, $creating)
    {
        foreach (['code', 'name', 'description', 'type', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating || array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code'] ?? '');
        }

        return array_intersect_key($data, array_flip([
            'code',
            'name',
            'description',
            'type',
            'status',
        ]));
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim((string) $code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function auditPayload(FoodTag $tag)
    {
        return [
            'code' => $tag->code,
            'name' => $tag->name,
            'description' => $tag->description,
            'type' => $tag->type,
            'status' => $tag->status,
            'deleted_at' => $tag->deleted_at ? (string) $tag->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'food_tags',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
