<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class CreateProjectUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case to create a project
     * 
     * @param array $data Project data
     * @param int $createdByUserId ID of the user creating the project
     * @return int ID of the created project
     * @throws \InvalidArgumentException If the project name is missing
     * @throws \Exception If the project creation fails
     */
    public function execute(array $data, int $createdByUserId): int
    {
        if (empty($data['projectname'])) {
            throw new \InvalidArgumentException('Project name is required');
        }

        // Determine the creating user (JWT or parameter)
        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        // Prepare additional data
        $data['created_by_user_id'] = $userId;
        
        // Map quoteid to potentialid if exists (relationship Quote → Opportunity → Project)
        if (isset($data['quoteid']) && $data['quoteid']) {
            $data['potentialid'] = $data['quoteid'];
        }
        
        if (!isset($data['assigned_user_id'])) {
            $data['assigned_user_id'] = null;
        }

        // Create project in the repository (returns the ID)
        $projectId = $this->repository->create($data, $userId);
        
        if (!$projectId || !is_numeric($projectId)) {
            throw new \Exception('Failed to create project');
        }

        // Synchronize vtiger_crmentity.label with projectname
        // This is crucial for the activity panel to display the correct name
        DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->updateOrInsert(
                ['crmid' => $projectId, 'setype' => 'Project'],
                [
                    'label' => $data['projectname'],
                    'createdtime' => now(),
                    'modifiedtime' => now(),
                    'deleted' => 0,
                ]
            );

        // Register activity in vtiger_modtracker_basic
        VtigerActivityTracker::created(
            module: 'Project', // Correct module for projects in Vtiger
            crmid: (int) $projectId,
            userId: $userId
        );
        
        return $projectId;
    }
}