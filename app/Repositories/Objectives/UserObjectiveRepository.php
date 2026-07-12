<?php

namespace App\Repositories\Objectives;

use App\Exceptions\Objectives\ObjectivesException;
use App\UserObjective;

class UserObjectiveRepository
{
    public function listForUser($userId)
    {
        return UserObjective::with('objective')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function findForUserOrFail($userId, $id)
    {
        $assignment = UserObjective::with('objective')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (! $assignment) {
            throw new ObjectivesException('USER_OBJECTIVE_NOT_FOUND', 'El objetivo del usuario no existe.', 404);
        }

        return $assignment;
    }

    public function activeExists($userId, $objectiveId, $exceptId = null)
    {
        $query = UserObjective::where('user_id', $userId)
            ->where('objective_id', $objectiveId)
            ->where('is_active', true);

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return UserObjective::create($data)->load('objective');
    }

    public function update(UserObjective $assignment, array $data)
    {
        $assignment->fill($data);
        $assignment->save();

        return $assignment->fresh()->load('objective');
    }
}
