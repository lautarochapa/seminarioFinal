<?php

namespace App\Services\Admin;

use App\Repositories\Admin\LoginLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginLogAdminService
{
    private $loginLogRepo;

    public function __construct(LoginLogRepository $loginLogRepo)
    {
        $this->loginLogRepo = $loginLogRepo;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->loginLogRepo->paginate($filters);
    }
}
