<?php

namespace App\Application\UseCases\Core\Entity;

use App\Infrastructure\Repositories\Core\CrmentityRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateEntityUseCase
{
    public function __construct(
        private readonly CrmentityRepository $crmentity,
    ) {}

    /**
     * Execute the update of a generic CRM entity
     *
     * Orquestación simple: actualiza datos en crmentity
     *
     * @param  int  $crmId  ID de la entidad a actualizar
     * @param  array  $data  Datos a actualizar en vtiger_crmentity
     * @param  int|null  $userId  Usuario que modifica (para modifiedby)
     * @return bool True si se actualizó correctamente
     *
     * @throws InvalidArgumentException Si el ID es inválido
     */
    public function execute(
        int $crmId,
        array $data,
        ?int $userId = null
    ): bool {
        if ($crmId <= 0) {
            throw new InvalidArgumentException('Entity ID must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $userId = $userId ?? 1;

        // Filtrar campos no permitidos
        $sanitized = array_diff_key($data, [
            'crmid' => true,
            'createdtime' => true,
            'deleted' => true,
        ]);

        
        if (! isset($sanitized['modifiedby'])) {
            $sanitized['modifiedby'] = $userId;
        }

        return DB::connection('vtiger')->transaction(function () use ($crmId, $sanitized) {
            return $this->crmentity->update($crmId, $sanitized);
        });
    }

    /**
     * Update only the label of an entity
     *
     * @param  int  $crmId  ID de la entidad
     * @param  string  $label  Nuevo label
     * @return bool True si se actualizó
     */
    public function updateLabel(int $crmId, string $label): bool
    {
        if ($crmId <= 0) {
            throw new InvalidArgumentException('Entity ID must be positive');
        }

        return $this->crmentity->updateLabel($crmId, $label);
    }
}
