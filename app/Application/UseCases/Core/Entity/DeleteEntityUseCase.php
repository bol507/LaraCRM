<?php

namespace App\Application\UseCases\Core\Entity;

use App\Infrastructure\Repositories\Core\CrmentityRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteEntityUseCase
{
    public function __construct(
        private readonly CrmentityRepository $crmentity,
    ) {}

    /**
     * Execute the soft delete of a generic CRM entity
     *
     * Orquestación simple: marca como eliminada en crmentity
     *
     * @param  int  $crmId  ID de la entidad a eliminar
     * @param  int|null  $userId  Usuario que elimina
     * @return bool True si se eliminó correctamente
     *
     * @throws InvalidArgumentException Si el ID es inválido
     */
    public function execute(
        int $crmId,
        ?int $userId = null
    ): bool {
        if ($crmId <= 0) {
            throw new InvalidArgumentException('Entity ID must be positive');
        }

        $userId = $userId ?? 1;

        return DB::connection('vtiger')->transaction(function () use ($crmId) {
            return $this->crmentity->delete($crmId);
        });
    }

    /**
     * Restore a soft-deleted entity
     *
     * @param  int  $crmId  ID de la entidad a restaurar
     * @return bool True si se restauró correctamente
     */
    public function restore(int $crmId): bool
    {
        if ($crmId <= 0) {
            throw new InvalidArgumentException('Entity ID must be positive');
        }

        return DB::connection('vtiger')->transaction(function () use ($crmId) {
            return $this->crmentity->update($crmId, ['deleted' => 0]);
        });
    }
}
