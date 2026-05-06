<?php
// app/Application/UseCases/Procurement/CreateMaterialRequestUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\CreateMaterialRequestDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Domain\Events\MaterialRequestSubmittedEvent;
use DomainException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class CreateMaterialRequestUseCase
{
    public function __construct(
        private readonly MaterialRequestRepositoryInterface $requestRepo
    ) {}

    public function execute(CreateMaterialRequestDto $dto): int
    {
        if (empty($dto->items)) {
            throw new InvalidArgumentException('Cannot create a request without items');
        }

        return DB::transaction(function () use ($dto) {
            // 1. Crear cabecera en estado 'draft'
            $requestId = $this->requestRepo->create([
                'project_id' => $dto->projectId,
                'requested_by' => $dto->requestedBy,
                'status' => 'draft',
            ]);

            // 2. Insertar ítems
            $itemsData = array_map(fn($i) => [
                'catalog_item_type' => $i['type'],
                'catalog_reason_type' => $i['reason'],
                'item_name' => $i['name'],
                'quantity' => $i['qty'],
                'unit' => $i['unit'],
                'priority' => $i['priority'] ?? 'medium',
                'estimated_cost' => $i['estCost'] ?? null,
                'notes' => $i['notes'] ?? null,
                
                'reason_other' => $i['reasonOther'] ?? null,

            ], $dto->items);

            $this->requestRepo->createItems($requestId, $itemsData);

            // 3. Transición a 'submitted'
            $this->requestRepo->updateStatus($requestId, 'submitted');

            // 🔔 Placeholder para WebSocket: event(new MaterialRequestSubmittedEvent($requestId, $dto->projectId));
            
            return $requestId;
        });
    }
}