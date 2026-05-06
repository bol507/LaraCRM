<?php

namespace App\Application\UseCases\Core\Entity;

use App\Infrastructure\Repositories\Core\CrmentityRepository;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CreateEntityUseCase
{
    private const CONFIG = [
        'lock_prefix' => 'entity_id_',
    ];

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CrmentityRepository $crmentity,
    ) {}

    /**
     * Execute the creation of a generic CRM entity
     *
     * Simple orchestration: generates ID and saves to crmentity
     *
     * @param  array  $data  Data for vtiger_crmentity
     * @param  string  $setype  Entity type (Accounts, Contacts, Potentials, etc.)
     * @param  string|null  $table  Specific table for ID generation (optional)
     * @param  int|null  $userId  User creating the entity
     * @param  int|null  $crmId  Pre-generated ID (optional, for when ID was already generated externally)
     * @return int ID of the created entity
     *
     * @throws InvalidArgumentException If required data is missing
     * @throws RuntimeException If creation fails
     */
    public function execute(
        array $data,
        string $setype,
        ?string $table = null,
        ?int $userId = null,
        ?int $crmId = null
    ): int {
        $this->validateData($data, $setype);

        $userId = $userId ?? 1;
        $now = now()->format('Y-m-d H:i:s');

        // Determine the source table for the ID (crmentity is the source of truth)
        $idTable = $table ?? 'vtiger_crmentity';
        $lockName = self::CONFIG['lock_prefix'].strtolower($setype);

        return DB::connection('vtiger')->transaction(function () use ($data, $setype, $idTable, $lockName, $userId, $now, $crmId) {
            // 1. Generate unique ID from crmentity (or use pre-generated one)
            $crmId = $data['crmid'] ?? $this->idGenerator->generateNextId(
                table: $idTable,
                column: 'crmid',
                lockName: $lockName
            );

            // 2. Prepare data for crmentity
            $entityData = [
                'crmid' => $crmId,
                'smcreatorid' => $data['smcreatorid'] ?? $userId,
                'smownerid' => $data['smownerid'] ?? $userId,
                'setype' => $setype,
                'description' => $data['description'] ?? '',
                'label' => $data['label'] ?? '',
                'createdtime' => $now,
                'modifiedtime' => $now,
                'deleted' => 0,
            ];

            // 3. Insert into vtiger_crmentity
            $this->crmentity->insert($entityData);

            return $crmId;
        });
    }

    private function validateData(array $data, string $setype): void
    {
        if (empty($setype)) {
            throw new InvalidArgumentException('Entity setype is required');
        }

        if (empty($data['label']) && empty($data['description'])) {
            throw new InvalidArgumentException('Entity label or description is required');
        }
    }
}