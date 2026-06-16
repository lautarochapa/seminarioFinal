<?php

namespace App\Services\WasteReports;

use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\WasteReports\WasteReportRepository;

class WasteReportService
{
    private $groups;
    private $reports;

    public function __construct(FamilyGroupRepository $groups, WasteReportRepository $reports)
    {
        $this->groups = $groups;
        $this->reports = $reports;
    }

    public function report(int $groupId, int $userId, array $filters): array
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return [
            'paginator' => $this->reports->paginate($groupId, $filters),
            'totals' => $this->reports->totals($groupId, $filters),
        ];
    }
}
