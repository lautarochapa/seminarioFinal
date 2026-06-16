<?php

namespace App\Repositories\PaymentMethods;

use App\Exceptions\Ingredients\IngredientException;
use App\PaymentMethod;
use App\UserPaymentMethod;

class PaymentMethodRepository
{
    public function paginate(array $filters)
    {
        $query = PaymentMethod::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('name', 'ILIKE', $search);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('name')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $pm = PaymentMethod::find($id);

        if (! $pm) {
            throw new IngredientException('PAYMENT_METHOD_NOT_FOUND', 'Metodo de pago no encontrado.', 404);
        }

        return $pm;
    }

    public function findWithTrashedOrFail($id)
    {
        $pm = PaymentMethod::withTrashed()->find($id);

        if (! $pm) {
            throw new IngredientException('PAYMENT_METHOD_NOT_FOUND', 'Metodo de pago no encontrado.', 404);
        }

        return $pm;
    }

    public function existsDuplicate(string $name, string $type, ?string $issuer, $exceptId = null)
    {
        $query = PaymentMethod::withTrashed()
            ->where('name', $name)
            ->where('type', $type);

        if ($issuer === null) {
            $query->whereNull('issuer');
        } else {
            $query->where('issuer', $issuer);
        }

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $pm, array $data)
    {
        $pm->fill($data);
        $pm->save();
        return $pm->fresh();
    }

    public function softDelete(PaymentMethod $pm)
    {
        $pm->status = 'inactive';
        $pm->save();
        $pm->delete();
        return PaymentMethod::withTrashed()->find($pm->id);
    }

    public function restore(PaymentMethod $pm)
    {
        $pm->restore();
        $pm->status = 'active';
        $pm->save();
        return $pm->fresh();
    }

    public function catalog(array $filters)
    {
        $query = PaymentMethod::where('status', 'active');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['issuer'])) {
            $query->where('issuer', 'ILIKE', '%' . $filters['issuer'] . '%');
        }

        return $query->orderBy('name')->get();
    }

    public function findActiveById(int $id)
    {
        return PaymentMethod::where('id', $id)->where('status', 'active')->first();
    }

    public function userMethods(int $userId)
    {
        return UserPaymentMethod::with('paymentMethod')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get();
    }

    public function hasActiveUserMethod(int $userId, int $pmId): bool
    {
        return UserPaymentMethod::where('user_id', $userId)
            ->where('payment_method_id', $pmId)
            ->where('status', 'active')
            ->exists();
    }

    public function addUserMethod(int $userId, int $pmId, ?string $alias)
    {
        $upm = UserPaymentMethod::create([
            'user_id'           => $userId,
            'payment_method_id' => $pmId,
            'alias'             => $alias,
            'status'            => 'active',
        ]);

        return $upm->load('paymentMethod');
    }

    public function findUserMethodForOwner(int $id, int $userId)
    {
        return UserPaymentMethod::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function deactivateUserMethod(UserPaymentMethod $upm)
    {
        $upm->status = 'inactive';
        $upm->save();
        return $upm->fresh();
    }
}
