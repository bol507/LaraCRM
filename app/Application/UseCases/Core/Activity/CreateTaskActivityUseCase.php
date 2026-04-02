<?php

namespace App\Application\UseCases\Core\Activity;

use App\Infrastructure\Repositories\Core\ActivityRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTaskActivityUseCase
{
    public function __construct(
        private readonly ActivityRepository $activity,
    ) {}

    /**
     * Execute the creation of a Task activity
     *
     * Orquestación simple: guarda en vtiger_activity para tipo Task
     *
     * @param  int  $activityId  ID de la actividad (debe venir del generador)
     * @param  array  $data  Datos para vtiger_activity
     * @return bool True si se creó correctamente
     *
     * @throws InvalidArgumentException Si los datos son inválidos
     */
    public function execute(int $activityId, array $data): bool
    {
        if ($activityId <= 0) {
            throw new InvalidArgumentException('Activity ID must be positive');
        }

        if (empty($data['subject'])) {
            throw new InvalidArgumentException('Subject is required');
        }

        $data['activityid'] = $activityId;
        $data['activitytype'] = $data['activitytype'] ?? 'Task';

        return DB::connection('vtiger')->transaction(function () use ($data) {
            return $this->activity->insert($data);
        });
    }
}
