<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\PotentialRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreateOpportunityUseCase
{
    private const ID_LOCK_NAME = 'opportunity_id_generation';

    private const ENTITY_SETYPE = 'Potentials';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly PotentialRepository $potential,
    ) {}

    /**
     * Execute the opportunity creation use case
     *
     * Orquestación de DML:
     * 1. Generar ID único
     * 2. Insertar vtiger_crmentity (metadata)
     * 3. Insertar vtiger_potential (datos de oportunidad)
     *
     * @param  CreateOpportunityRequest  $request  The opportunity creation request
     * @param  int|null  $createdByUserId  User creating the opportunity
     * @return int The ID of the created opportunity
     *
     * @throws InvalidArgumentException When the opportunity name is missing
     * @throws RuntimeException When opportunity creation fails
     */
    public function execute(CreateOpportunityRequest $request, ?int $createdByUserId = null): int
    {
        if (empty($request->potentialname)) {
            throw new InvalidArgumentException('Opportunity name is required');
        }

        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        return DB::connection('vtiger')->transaction(function () use ($request, $userId) {
            // 1. Generar ID único
            $potentialId = $this->idGenerator->generateNextId(
                table: 'vtiger_potential',
                column: 'potentialid',
                lockName: self::ID_LOCK_NAME
            );

            // 2. Insertar vtiger_crmentity using generic use case
            $this->createEntity->execute(
                data: [
                    'label' => trim($request->potentialname),
                    'description' => $request->description ?? '',
                    'smownerid' => $request->assigned_user_id ?? $userId,
                    'smcreatorid' => $userId,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $potentialId
            );

            // 3. Insertar vtiger_potential
            $this->potential->insert([
                'potentialid' => $potentialId,
                'potential_no' => $this->potential->getNextPotentialNumber(),
                'related_to' => $request->related_to ?? null,
                'potentialname' => $request->potentialname,
                'amount' => $request->amount ?? null,
                'currency' => $request->currency ?? null,
                'closingdate' => $request->closingdate ?? null,
                'typeofrevenue' => $request->typeofrevenue ?? null,
                'nextstep' => $request->nextstep ?? null,
                'private' => $request->private ?? '0',
                'probability' => $request->probability ?? null,
                'campaignid' => $request->campaignid ?? null,
                'sales_stage' => $request->sales_stage ?? 'Prospecting',
                'potentialtype' => $request->potentialtype ?? null,
                'leadsource' => $request->leadsource ?? null,
                'description' => $request->description ?? null,
                'forecastcategory' => $request->forecastcategory ?? null,
                'isconvertedfromlead' => $request->isconvertedfromlead ?? '0',
            ]);

            // Registrar actividad
            $this->logActivity($potentialId, $userId);

            return $potentialId;
        });
    }

    private function logActivity(int $opportunityId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'Potentials',
                crmid: $opportunityId,
                userId: $userId
            );
        } catch (\Exception $e) {
            //
        }
    }
}
