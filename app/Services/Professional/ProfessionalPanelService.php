<?php

namespace App\Services\Professional;

use App\AuditLog;
use App\Exceptions\Professional\ProfessionalException;
use App\MealPlan;
use App\Repositories\Professional\ProfessionalLinkRepository;
use App\Repositories\Professional\ProfessionalMealPlanRepository;
use App\Repositories\UserProfile\UserProfileRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfessionalPanelService
{
    private $linkRepo;
    private $mealPlanRepo;
    private $profileRepo;

    public function __construct(
        ProfessionalLinkRepository $linkRepo,
        ProfessionalMealPlanRepository $mealPlanRepo,
        UserProfileRepository $profileRepo
    ) {
        $this->linkRepo     = $linkRepo;
        $this->mealPlanRepo = $mealPlanRepo;
        $this->profileRepo  = $profileRepo;
    }

    public function linkedUsers(int $professionalId): Collection
    {
        return $this->linkRepo->listLinkedUsersForProfessional($professionalId);
    }

    public function getUserProfile(int $userId, int $professionalId): object
    {
        $link = $this->linkRepo->findActiveLinkByProfessional($userId, $professionalId);

        if (!$link) {
            throw new ProfessionalException('PROFESSIONAL_USER_NOT_FOUND', 'No existe un vínculo activo con este usuario.', 404);
        }

        if (!$link->can_view_profile) {
            throw new ProfessionalException('PROFESSIONAL_ACCESS_DENIED', 'No tienes permiso para ver el perfil de este usuario.', 403);
        }

        return $this->profileRepo->getProfileData($userId);
    }

    public function getMealPlans(int $userId, int $professionalId): Collection
    {
        $link = $this->linkRepo->findActiveLinkByProfessional($userId, $professionalId);

        if (!$link) {
            throw new ProfessionalException('PROFESSIONAL_USER_NOT_FOUND', 'No existe un vínculo activo con este usuario.', 404);
        }

        if (!$link->can_view_meal_plans) {
            throw new ProfessionalException('PROFESSIONAL_ACCESS_DENIED', 'No tienes permiso para ver los planes de comida de este usuario.', 403);
        }

        return $this->mealPlanRepo->listForUser($userId);
    }

    public function updateMealPlan(int $userId, int $professionalId, int $planId, array $data, string $ip, string $ua): MealPlan
    {
        $link = $this->linkRepo->findActiveLinkByProfessional($userId, $professionalId);

        if (!$link) {
            throw new ProfessionalException('PROFESSIONAL_USER_NOT_FOUND', 'No existe un vínculo activo con este usuario.', 404);
        }

        if (!$link->can_edit_meal_plans) {
            throw new ProfessionalException('PROFESSIONAL_ACCESS_DENIED', 'No tienes permiso para editar los planes de comida de este usuario.', 403);
        }

        $plan = $this->mealPlanRepo->findByIdForUser($planId, $userId);

        if (!$plan) {
            throw new ProfessionalException('PROFESSIONAL_PLAN_NOT_FOUND', 'El plan de comida no existe para este usuario.', 404);
        }

        $oldValues = $plan->only(array_keys($data));

        $updated = DB::transaction(function () use ($plan, $data, $professionalId, $userId, $planId, $oldValues, $ip, $ua) {
            $updated = $this->mealPlanRepo->update($plan, $data);

            AuditLog::create([
                'user_id'     => $professionalId,
                'action'      => 'professional-meal-plan.updated',
                'entity_name' => 'meal_plans',
                'entity_id'   => (string) $planId,
                'old_values'  => $oldValues,
                'new_values'  => $data,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });

        return $updated;
    }
}
