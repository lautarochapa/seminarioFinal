<?php

namespace App\Services\RecipeSharingBranch;

use App\AuditLog;
use App\Exceptions\RecipeSharingBranch\RecipeSharingBranchException;
use App\Recipe;
use App\Repositories\RecipeSharingBranch\RecipeSharingBranchRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeSharingBranchService
{
    private RecipeSharingBranchRepository $repo;

    public function __construct(RecipeSharingBranchRepository $repo)
    {
        $this->repo = $repo;
    }

    public function share(User $user, int $recipeId, string $ip, string $userAgent): Recipe
    {
        $recipe = $this->repo->findActive($recipeId);
        if (!$recipe) {
            throw RecipeSharingBranchException::recipeNotFound();
        }

        $this->assertAuthorOrAdmin($user, $recipe);

        if ($recipe->is_public) {
            throw RecipeSharingBranchException::alreadyPublic();
        }

        $old = ['is_public' => false, 'is_verified' => $recipe->is_verified];
        $this->repo->setPublic($recipe, true);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_shared',
            'entity_name'=> 'recipes',
            'entity_id'  => $recipe->id,
            'old_values' => $old,
            'new_values' => ['is_public' => true, 'is_verified' => false],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $recipe->fresh();
    }

    public function unshare(User $user, int $recipeId, string $ip, string $userAgent): Recipe
    {
        $recipe = $this->repo->findActive($recipeId);
        if (!$recipe) {
            throw RecipeSharingBranchException::recipeNotFound();
        }

        $this->assertAuthorOrAdmin($user, $recipe);

        if (!$recipe->is_public) {
            throw RecipeSharingBranchException::alreadyPrivate();
        }

        $old = ['is_public' => true, 'is_verified' => $recipe->is_verified];
        $this->repo->setPublic($recipe, false);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_unshared',
            'entity_name'=> 'recipes',
            'entity_id'  => $recipe->id,
            'old_values' => $old,
            'new_values' => ['is_public' => false, 'is_verified' => false],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $recipe->fresh();
    }

    public function branch(User $user, int $recipeId, string $ip, string $userAgent): Recipe
    {
        $origin = $this->repo->findVisible($recipeId, $user->id);
        if (!$origin) {
            throw RecipeSharingBranchException::recipeNotFound();
        }

        $branchRecipe = DB::transaction(function () use ($user, $recipeId, $ip, $userAgent) {
            $origin = $this->repo->loadForBranch($recipeId);
            $branch = $this->repo->createBranch($origin, $user->id);

            $this->repo->copyIngredients($origin, $branch);
            $this->repo->copySteps($origin, $branch);
            $this->repo->copyTags($origin, $branch);

            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'recipe_branched',
                'entity_name'=> 'recipes',
                'entity_id'  => $branch->id,
                'old_values' => ['branched_from_recipe_id' => $origin->id],
                'new_values' => ['id' => $branch->id, 'owner_user_id' => $user->id, 'is_public' => false],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return $branch;
        });

        return $branchRecipe;
    }

    private function assertAuthorOrAdmin(User $user, Recipe $recipe): void
    {
        $isAuthor = (int) $recipe->owner_user_id === (int) $user->id;
        $isAdmin  = $user->hasPermission('recipes.manage') || $user->hasRole('super_admin');

        if (!$isAuthor && !$isAdmin) {
            throw RecipeSharingBranchException::forbidden();
        }
    }
}
