<?php

namespace App\Application\UseCases\Project;

use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\ProjectRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateProjectUseCase
{
    public function __construct(
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly ProjectRepository $project,
    ) {}

    /**
     * Execute the use case to update a project
     *
     * Orquestación de DML:
     * 1. Validar que el proyecto existe
     * 2. Actualizar vtiger_project (datos del proyecto)
     * 3. Actualizar vtiger_crmentity (label + modifiedby)
     *
     * @param  int  $projectId  ID of the project to update
     * @param  array  $data  Project data to update
     * @param  int|null  $modifiedByUserId  ID of the user performing the update
     * @return bool True if updated successfully
     *
     * @throws InvalidArgumentException If the project ID is missing
     * @throws RuntimeException If the project update fails
     */
    public function execute(int $projectId, array $data, ?int $modifiedByUserId = null): bool
    {
        if (! $projectId) {
            throw new InvalidArgumentException('Project ID is required');
        }

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        // Validar que el proyecto existe
        if (! $this->project->exists($projectId)) {
            throw new InvalidArgumentException('Project not found');
        }

        // Filtrar valores null
        $data = array_filter($data, fn ($v) => $v !== null);

        // Actualizar en transacción
        DB::connection('vtiger')->transaction(function () use ($projectId, $data, $userId) {
            // 1. Actualizar vtiger_project
            if (! empty($data)) {
                $this->project->updateProject($projectId, $data);
            }

            // 2. Actualizar vtiger_crmentity using generic use case
            if (! empty($data['projectname'])) {
                $this->updateEntity->execute(
                    crmId: $projectId,
                    data: [
                        'label' => trim($data['projectname']),
                        'description' => $data['description'] ?? ''
                    ],
                    userId: $userId
                );
            }
        });

        // Registrar actividad
        $this->logActivity($projectId, $userId);

        return true;
    }

    private function logActivity(int $projectId, int $userId): void
    {
        try {
            VtigerActivityTracker::updated(
                module: 'Project',
                crmid: $projectId,
                userId: $userId
            );
        } catch (\Exception $e) {
            //
        }
    }
}
