<?php

namespace App\Services\RecipeTags;

use App\AuditLog;
use App\Exceptions\RecipeTags\RecipeTagException;
use App\RecipeTag;
use App\Repositories\RecipeTags\RecipeTagRepository;
use Illuminate\Support\Facades\DB;

class RecipeTagService
{
    private $repo;

    public function __construct(RecipeTagRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function catalog()
    {
        return $this->repo->active();
    }

    public function show($id)
    {
        return $this->repo->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);

        if ($this->repo->codeExists($data['code'])) {
            throw new RecipeTagException('RECIPE_TAG_CODE_ALREADY_EXISTS', 'El codigo ya existe.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $tag = RecipeTag::create([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'type'        => $data['type'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);

            $this->audit($actorId, 'recipe-tag.created', $tag->id, null, $this->auditPayload($tag), $ip, $userAgent);

            return $tag->fresh();
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $tag  = $this->repo->findOrFail($id);
        $data = $this->prepare($data, false);

        if (array_key_exists('code', $data) && $this->repo->codeExists($data['code'], $tag->id)) {
            throw new RecipeTagException('RECIPE_TAG_CODE_ALREADY_EXISTS', 'El codigo ya existe.', 409);
        }

        return DB::transaction(function () use ($actorId, $tag, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($tag);
            $allowed = ['code', 'name', 'description', 'type', 'status'];
            $tag->fill(array_intersect_key($data, array_flip($allowed)));
            $tag->save();
            $fresh = $tag->fresh();
            $new   = $this->auditPayload($fresh);

            if ($old != $new) {
                $this->audit($actorId, 'recipe-tag.updated', $fresh->id, $old, $new, $ip, $userAgent);
            }

            return $fresh;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $tag = $this->repo->findOrFail($id);

        if ($tag->status === 'inactive') {
            throw new RecipeTagException('RECIPE_TAG_ALREADY_INACTIVE', 'El tag ya esta inactivo.', 409);
        }

        return DB::transaction(function () use ($actorId, $tag, $ip, $userAgent) {
            $old        = $this->auditPayload($tag);
            $tag->status = 'inactive';
            $tag->save();
            $new = $this->auditPayload($tag);

            $this->audit($actorId, 'recipe-tag.deleted', $tag->id, $old, $new, $ip, $userAgent);

            return $tag->fresh();
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $tag = $this->repo->findOrFail($id);

        if ($tag->status === 'active') {
            throw new RecipeTagException('RECIPE_TAG_ALREADY_ACTIVE', 'El tag ya esta activo.', 409);
        }

        return DB::transaction(function () use ($actorId, $tag, $ip, $userAgent) {
            $old        = $this->auditPayload($tag);
            $tag->status = 'active';
            $tag->save();
            $new = $this->auditPayload($tag);

            $this->audit($actorId, 'recipe-tag.restored', $tag->id, $old, $new, $ip, $userAgent);

            return $tag->fresh();
        });
    }

    private function prepare(array $data, $creating = true)
    {
        foreach (['code', 'name', 'description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        return $data;
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function auditPayload(RecipeTag $tag)
    {
        return [
            'code'        => $tag->code,
            'name'        => $tag->name,
            'description' => $tag->description,
            'type'        => $tag->type,
            'status'      => $tag->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'recipe_tags',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
