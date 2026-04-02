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
     * Orquestación simple: genera ID y guarda en crmentity
     *
     * @param  array  $data  Datos para vtiger_crmentity
     * @param  string  $setype  Tipo de entidad (Accounts, Contacts, Potentials, etc.)
     * @param  string|null  $table  Tabla específica para generar ID (opcional)
     * @param  int|null  $userId  Usuario que crea la entidad
     * @param  int|null  $crmId  ID pre-generado (opcional, para cuando ya se generó IDexternamente)
     * @return int ID de la entidad creada
     *
     * @throws InvalidArgumentException Si faltan datos requeridos
     * @throws RuntimeException Si falla la creación
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

        // Determinar la tabla fuente para el ID (crmentity es la fuente de verdad)
        $idTable = $table ?? 'vtiger_crmentity';
        $lockName = self::CONFIG['lock_prefix'].strtolower($setype);

        return DB::connection('vtiger')->transaction(function () use ($data, $setype, $idTable, $lockName, $userId, $now, $crmId) {
            // 1. Generar ID único desde crmentity (o usar el pre-generado)
            $crmId = $crmId ?? $this->idGenerator->generateNextId(
                table: $idTable,
                column: 'crmid',
                lockName: $lockName
            );

            // 2. Preparar datos para crmentity
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

            // 3. Insertar en vtiger_crmentity
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
