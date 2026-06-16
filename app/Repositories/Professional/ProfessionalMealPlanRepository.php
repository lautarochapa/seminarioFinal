<?php

namespace App\Repositories\Professional;

use App\MealPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfessionalMealPlanRepository
{
    public function listForUser(int $userId): Collection
    {
        $groupIds = DB::table('family_group_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('family_group_id');

        return MealPlan::whereIn('family_group_id', $groupIds)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function findByIdForUser(int $planId, int $userId): ?MealPlan
    {
        $groupIds = DB::table('family_group_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('family_group_id');

        return MealPlan::where('id', $planId)
            ->whereIn('family_group_id', $groupIds)
            ->first();
    }

    public function update(MealPlan $plan, array $data): MealPlan
    {
        $plan->fill($data)->save();
        return $plan->fresh();
    }
}
