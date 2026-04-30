<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\DTOs\UpdateOpportunityRequest;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\PotentialRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateOpportunityUseCase
{
    public function __construct(
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly PotentialRepository $potential,
    ) {}

    /**
     * Execute the use case to update an opportunity
     *
     * Orquestación de DML:
     * 1. Validar que la oportunidad existe
     * 2. Actualizar vtiger_potential (datos de oportunidad)
     * 3. Actualizar vtiger_crmentity (label + modifiedby)
     *
     * @param  UpdateOpportunityRequest  $request  The request with update data
     * @param  int|null  $modifiedByUserId  User performing the update
     * @return bool True if updated successfully
     *
     * @throws InvalidArgumentException If the opportunity ID is missing
     * @throws RuntimeException If the update fails
     */
    public function execute(UpdateOpportunityRequest $request, ?int $modifiedByUserId = null): bool
    {
        $opportunityId = $request->id;

        if (! $opportunityId) {
            throw new InvalidArgumentException('Opportunity ID is required');
        }

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        // Validar que la oportunidad existe
        if (! $this->potential->exists($opportunityId)) {
            throw new InvalidArgumentException('Opportunity not found');
        }

        // Preparar datos para actualizar
        $data = [
            'potentialname' => $request->potentialname,
            'amount' => $request->amount,
            'closingdate' => $request->closingdate,
            'sales_stage' => $request->sales_stage,
            'probability' => $request->probability,
            'related_to' => $request->related_to,
            'description' => $request->description,
        ];

        // Filtrar valores null
        $data = array_filter($data, fn ($v) => $v !== null);

        // Actualizar en transacción
        DB::connection('vtiger')->transaction(function () use ($opportunityId, $data, $userId, $request) {
            // 1. Actualizar vtiger_potential
            if (! empty($data)) {
                $this->potential->updatePotential($opportunityId, $data);
            }

            // 2. Actualizar vtiger_crmentity (label + description)
            $crmentityData = [];
            if ($request->potentialname) {
                $crmentityData['label'] = trim($request->potentialname);
            }
            if ($request->description !== null) {
                $crmentityData['description'] = $request->description;
            }
            if (! empty($crmentityData)) {
                $this->updateEntity->execute(
                    crmId: $opportunityId,
                    data: $crmentityData,
                    userId: $userId
                );
            }
        });

        // Registrar actividad
        $this->logActivity($opportunityId, $userId);

        return true;
    }

    private function logActivity(int $opportunityId, int $userId): void
    {
        try {
            VtigerActivityTracker::updated(
                module: 'Potentials',
                crmid: $opportunityId,
                userId: $userId
            );
        } catch (\Exception $e) {
            //
        }
    }
}
