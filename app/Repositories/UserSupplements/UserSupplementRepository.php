<?php

namespace App\Repositories\UserSupplements;

use App\Exceptions\UserSupplements\UserSupplementException;
use App\UserSupplement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserSupplementRepository
{
    public function paginateForUser(int $userId, array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return UserSupplement::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['supplementType', 'product', 'ingredient', 'doseUnit'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findForUser(int $userId, int $supplementId): UserSupplement
    {
        $supplement = UserSupplement::where('user_id', $userId)
            ->where('id', $supplementId)
            ->whereNull('deleted_at')
            ->first();

        if (!$supplement) {
            throw new UserSupplementException('USER_SUPPLEMENT_NOT_FOUND', 'El suplemento no existe.', 404);
        }

        return $supplement;
    }

    public function create(array $data): UserSupplement
    {
        return UserSupplement::create($data);
    }

    public function update(UserSupplement $supplement, array $data): UserSupplement
    {
        $supplement->update($data);
        return $supplement->fresh(['supplementType', 'product', 'ingredient', 'doseUnit']);
    }

    public function deactivate(UserSupplement $supplement): void
    {
        $supplement->update(['status' => 'inactive']);
        $supplement->delete();
    }
}
