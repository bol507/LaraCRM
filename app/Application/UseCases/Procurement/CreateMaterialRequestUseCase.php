<?php
// app/Application/UseCases/Procurement/CreateMaterialRequestUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\CreateMaterialRequestDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;
use App\Domain\Events\MaterialRequestCreated;
use App\Services\CurrentUserService;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class CreateMaterialRequestUseCase
{
    public function __construct(
        private readonly MaterialRequestRepositoryInterface $requestRepo,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(CreateMaterialRequestDto $dto): int
    {
        if (empty($dto->items)) {
            throw new InvalidArgumentException('Cannot create a request without items');
        }

        return DB::connection('vtiger')->transaction(function () use ($dto) {
            // 1. Create header in 'draft' status
            $requestId = $this->requestRepo->create([
                'project_id' => $dto->projectId,
                'requested_by' => $dto->requestedBy,
                'status' => 'draft',
            ]);

            // 2. Insert items (correct mapping of DTO keys)
            $itemsData = array_map(fn($i) => [
                'catalog_item_type' => $i['type'],
                'catalog_reason_type' => $i['reason'],
                'item_name' => $i['name'],
                'quantity' => $i['qty'],              // Correct DTO key
                'unit' => $i['unit'],
                'priority' => $i['priority'] ?? 'medium',
                'estimated_cost' => $i['estCost'] ?? null,  // Correct DTO key
                'notes' => $i['notes'] ?? null,
                'reason_other' => $i['reasonOther'] ?? null,
            ], $dto->items);

            $this->requestRepo->createItems($requestId, $itemsData);

            // 3. Transition to 'submitted' (final status after creation)
            $this->requestRepo->updateStatus($requestId, 'submitted');

            // 4. Resolve creator name for notification
            $userId = CurrentUserService::idOr(1);
            // Fallback if name is not found
            $createdByName = $this->userRepository->getNameById($userId) ?? 'A user';
            
            // 5. Dispatch notification event
            Event::dispatch(new MaterialRequestCreated(
                materialRequestId: (int) $requestId,
                projectId: (int) $dto->projectId,
                createdBy: (int) $userId,
                requestData: [
                    'id' => $requestId,
                    'status' => 'submitted',
                    'items_count' => count($dto->items),
                    'total_estimated' => collect($dto->items)->sum(
                        fn($i) => ($i['estCost'] ?? 0) * $i['qty']
                    ),
                    'created_by_name' => $createdByName,
                    'project_responsible_id' => $dto->projectResponsibleId ?? null,
                ]
            ));

            return $requestId;
        });
    }
}
