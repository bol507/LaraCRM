<?php

namespace App\Application\UseCases\Core\Activity;

use App\Infrastructure\Repositories\Core\ActivityRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateTaskActivityUseCase
{
    public function __construct(
        private readonly ActivityRepository $activity,
    ) {}

    /**
     * Execute the update of a Task activity
     *
     * Orquestación simple: actualiza datos en vtiger_activity
     *
     * @param  int  $activityId  ID de la actividad a actualizar
     * @param  array  $data  Datos a actualizar en vtiger_activity
     * @return bool True si se actualizó correctamente
     *
     * @throws InvalidArgumentException Si el ID es inválido
     */
    public function execute(int $activityId, array $data): bool
    {
        if ($activityId <= 0) {
            throw new InvalidArgumentException('Activity ID must be positive');
        }

        if (empty($data)) {
            return true;
        }

        // Filtrar campos no permitidos
        $sanitized = array_diff_key($data, [
            'activityid' => true,
            'activitytype' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }

        return DB::connection('vtiger')->transaction(function () use ($activityId, $sanitized) {
            return $this->activity->update($activityId, $sanitized);
        });
    }
}
