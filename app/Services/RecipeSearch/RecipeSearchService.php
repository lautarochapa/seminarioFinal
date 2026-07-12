<?php

namespace App\Services\RecipeSearch;

use App\Repositories\RecipeSearch\RecipeSearchRepository;
use App\User;

class RecipeSearchService
{
    private RecipeSearchRepository $repo;

    public function __construct(RecipeSearchRepository $repo)
    {
        $this->repo = $repo;
    }

    public function search(User $user, array $filters)
    {
        $canManage = $user->hasPermission('recipes.manage');
        return $this->repo->search($filters, $user->id, $canManage);
    }
}
