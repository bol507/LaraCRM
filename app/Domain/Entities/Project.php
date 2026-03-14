<?php

namespace App\Domain\Entities;

class Project
{
    public function __construct(
        public int $projectid,
        public string $projectname,
        public ?string $project_no,
        public ?string $startdate,
        public ?string $targetenddate,
        public ?string $actualenddate,
        public ?string $targetbudget,
        public ?string $projecturl,
        public ?string $projectstatus,
        public ?string $projectpriority,
        public ?string $projecttype,
        public ?string $progress,
        public ?string $linktoaccountscontacts,
        public ?int $assigned_user_id,
        public ?string $account_name,
        public ?string $assigned_user_name,
        public ?string $potential_name,
        public ?int $totalTasks,
        public ?int $completedTasks,
        public ?int $hits,
        public ?string $description,
        public ?string $createdtime,
        public ?string $modifiedtime,
        public readonly ?string $lastActivity = null  // calculated in PHP
    ) {}
}