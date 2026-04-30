<?php

namespace App\Application\UseCases\Project;

use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\ProjectRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreateProjectUseCase
{
    private const ID_LOCK_NAME = 'project_id_generation';

    private const ENTITY_SETYPE = 'Project';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly ProjectRepository $project,
    ) {}

    /**
     * Execute the use case to create a project
     *
     * DML orchestration:
     * 1. Generate unique ID
     * 2. Insert vtiger_crmentity (metadata)
     * 3. Insert vtiger_project (project data)
     *
     * @param  array  $data  Project data
     * @param  int|null  $createdByUserId  ID of the user creating the project
     * @return int ID of the created project
     *
     * @throws InvalidArgumentException If the project name is missing
     * @throws RuntimeException If the project creation fails
     */
    public function execute(array $data, ?int $createdByUserId = null): int
    {
        if (empty($data['projectname'])) {
            throw new InvalidArgumentException('Project name is required');
        }

        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        $assignedUserId = $data['assigned_user_id'] ?? null;
        if ($assignedUserId === null) {
            $assignedUserId = 2; // ID "All" in Vtiger
        }

        return DB::connection('vtiger')->transaction(function () use ($data, $userId, $assignedUserId) {
            // 1. Generate unique ID
            $projectId = $this->idGenerator->generateNextId(
                table: 'vtiger_project',
                column: 'projectid',
                lockName: self::ID_LOCK_NAME
            );

            // 2. Insert vtiger_crmentity using generic use case
            $this->createEntity->execute(
                data: [
                    'label' => trim($data['projectname']),
                    'description' => $data['description'] ?? '',
                    'smownerid' => $assignedUserId,
                    'smcreatorid' => $userId,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $projectId
            );

            // 3. Insert vtiger_project
            $this->project->insert([
                'projectid' => $projectId,
                'project_no' => $this->project->getNextProjectNumber(),
                'projectname' => $data['projectname'],
                'startdate' => $data['startdate'] ?? null,
                'targetenddate' => $data['targetenddate'] ?? null,
                'actualenddate' => $data['actualenddate'] ?? null,
                'targetbudget' => $data['targetbudget'] ?? null,
                'projecturl' => $data['projecturl'] ?? null,
                'projectstatus' => $data['projectstatus'] ?? 'Draft',
                'projectpriority' => $data['projectpriority'] ?? 'Normal',
                'projecttype' => $data['projecttype'] ?? null,
                'progress' => $data['progress'] ?? 0,
                'tags' => null,
                'linktoaccountscontacts' => $data['accountid'] ?? null,
                'isconvertedfrompotential' => isset($data['quoteid']) && $data['quoteid'] ? 1 : 0,
                'potentialid' => $data['potentialid'] ?? $data['quoteid'] ?? null,
                'cf_922' => null,
            ]);

            // Register activity
            $this->logActivity($projectId, $userId);

            return $projectId;
        });
    }

    private function logActivity(int $projectId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'Project',
                crmid: $projectId,
                userId: $userId
            );
        } catch (\Exception $e) {
            // Silently fail - activity logging is non-critical
        }
    }
}