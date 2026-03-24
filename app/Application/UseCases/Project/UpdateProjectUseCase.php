<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class UpdateProjectUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case to update a project
     * 
     * @param int $projectId ID of the project to update
     * @param array $data Project data to update
     * @param int|null $modifiedByUserId ID of the user performing the update
     * @return bool True if updated successfully
     * @throws \InvalidArgumentException If the project ID is missing
     * @throws \Exception If the project update fails
     */
    public function execute(int $projectId, array $data, ?int $modifiedByUserId = null): bool
    {
        // Validate project ID
        if (!$projectId) {
            throw new \InvalidArgumentException('Project ID is required');
        }

        // Determine the modifying user (JWT or parameter)
        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        // Update vtiger_crmentity.label if the project name changed
        if (!empty($data['projectname'])) {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->where('setype', 'Project')
                ->update([
                    'label' => $data['projectname'],
                    'modifiedtime' => now(),
                ]);
        }

        // Update project in the repository
        $updated = $this->repository->update($projectId, $data, $userId);
        
        if (!$updated) {
            throw new \Exception('Failed to update project');
        }

        // Register activity in vtiger_modtracker_basic
        VtigerActivityTracker::updated(
            module: 'Project', // Correct module for projects
            crmid: $projectId,
            userId: $userId
        );
        
        return $updated;
    }
}