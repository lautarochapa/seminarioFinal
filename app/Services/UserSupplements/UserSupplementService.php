<?php

namespace App\Services\UserSupplements;

use App\AuditLog;
use App\Repositories\UserSupplements\UserSupplementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserSupplementService
{
    private $repo;

    public function __construct(UserSupplementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(int $userId, array $filters): LengthAwarePaginator
    {
        return $this->repo->paginateForUser($userId, $filters);
    }

    public function create(int $userId, array $data, string $ip, string $ua): array
    {
        return DB::transaction(function () use ($userId, $data, $ip, $ua) {
            $supplement = $this->repo->create([
                'user_id'             => $userId,
                'supplement_type_id'  => $data['supplement_type_id'],
                'product_id'          => $data['product_id'] ?? null,
                'ingredient_id'       => $data['ingredient_id'] ?? null,
                'dose_quantity'       => $data['dose_quantity'] ?? null,
                'dose_unit_id'        => $data['dose_unit_id'] ?? null,
                'frequency'           => $data['frequency'] ?? null,
                'start_date'          => $data['start_date'] ?? null,
                'end_date'            => $data['end_date'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'user_supplement.create',
                'entity_name' => 'user_supplements',
                'entity_id'   => (string) $supplement->id,
                'old_values'  => null,
                'new_values'  => ['supplement_type_id' => $supplement->supplement_type_id, 'frequency' => $supplement->frequency],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->format($supplement->load(['supplementType', 'product', 'ingredient', 'doseUnit']));
        });
    }

    public function update(int $userId, int $supplementId, array $data, string $ip, string $ua): array
    {
        $supplement = $this->repo->findForUser($userId, $supplementId);
        $old        = ['supplement_type_id' => $supplement->supplement_type_id, 'frequency' => $supplement->frequency, 'dose_quantity' => $supplement->dose_quantity];

        return DB::transaction(function () use ($supplement, $data, $old, $userId, $ip, $ua) {
            $allowed = ['supplement_type_id', 'product_id', 'ingredient_id', 'dose_quantity', 'dose_unit_id', 'frequency', 'start_date', 'end_date', 'notes'];
            $updates = array_intersect_key($data, array_flip($allowed));

            $updated = $this->repo->update($supplement, $updates);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'user_supplement.update',
                'entity_name' => 'user_supplements',
                'entity_id'   => (string) $updated->id,
                'old_values'  => $old,
                'new_values'  => $updates,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->format($updated);
        });
    }

    public function delete(int $userId, int $supplementId, string $ip, string $ua): void
    {
        $supplement = $this->repo->findForUser($userId, $supplementId);

        DB::transaction(function () use ($supplement, $userId, $ip, $ua) {
            $old = ['status' => $supplement->status];
            $this->repo->deactivate($supplement);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'user_supplement.delete',
                'entity_name' => 'user_supplements',
                'entity_id'   => (string) $supplement->id,
                'old_values'  => $old,
                'new_values'  => ['status' => 'inactive'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    private function format($s): array
    {
        return [
            'id'                   => $s->id,
            'supplement_type_id'   => $s->supplement_type_id,
            'supplement_type_name' => $s->supplementType ? $s->supplementType->name : null,
            'product_id'           => $s->product_id,
            'product_name'         => $s->product ? $s->product->name : null,
            'ingredient_id'        => $s->ingredient_id,
            'ingredient_name'      => $s->ingredient ? $s->ingredient->name : null,
            'dose_quantity'        => $s->dose_quantity !== null ? (float) $s->dose_quantity : null,
            'dose_unit_id'         => $s->dose_unit_id,
            'dose_unit_name'       => $s->doseUnit ? $s->doseUnit->name : null,
            'frequency'            => $s->frequency,
            'start_date'           => $s->start_date ? $s->start_date->toDateString() : null,
            'end_date'             => $s->end_date ? $s->end_date->toDateString() : null,
            'notes'                => $s->notes,
            'status'               => $s->status,
            'created_at'           => $s->created_at,
            'updated_at'           => $s->updated_at,
        ];
    }
}
