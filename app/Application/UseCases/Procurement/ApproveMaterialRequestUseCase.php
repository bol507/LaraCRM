<?php
// app/Application/UseCases/Procurement/ApproveMaterialRequestUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\ApproveMaterialRequestDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Domain\Events\MaterialRequestApproved;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class ApproveMaterialRequestUseCase
{
    public function __construct(
        private readonly MaterialRequestRepositoryInterface $requestRepo
    ) {}

    public function execute(ApproveMaterialRequestDto $dto): bool
    {
        return DB::transaction(function () use ($dto) {
            $request = $this->requestRepo->findById($dto->requestId);
            
            if (!$request) {
                throw new DomainException('Material request not found');
            }
            
            if ($request->status !== 'submitted') {
                throw new DomainException('Only submitted requests can be approved/rejected');
            }
            
            if (empty($dto->itemDecisions)) {
                throw new DomainException('At least one item decision is required');
            }

            $approvedCount = 0;
            $rejectedCount = 0;
            $partialCount = 0;
            $totalDecisions = count($dto->itemDecisions);

            // 1. Apply decisions to each item with explicit logic per action
            foreach ($dto->itemDecisions as $decision) {
                $action = $decision['action'];
                $itemId = $decision['itemId'];
                
                // Determine status and approved quantity based on the action
                [$newItemStatus, $approvedQty] = match ($action) {
                    'approve' => ['approved', $decision['quantity'] ?? null],
                    'partial' => ['partially_approved', $decision['quantity'] ?? 0], // Uses explicit quantity
                    'reject' => ['rejected', 0],
                    default => throw new InvalidArgumentException("Invalid action: {$action}"),
                };

                // Validation: 'partial' requires explicit quantity
                if ($action === 'partial' && ($approvedQty === null || $approvedQty <= 0)) {
                    throw new DomainException("Partial approval requires a positive quantity for item #{$itemId}");
                }

                // Update item in database
                $this->requestRepo->updateItemStatus(
                    itemId: $itemId,
                    status: $newItemStatus,
                    approvedQty: $approvedQty,
                    approvedBy: $dto->approverId
                );

                // Count for final status calculation
                match ($action) {
                    'approve' => $approvedCount++,
                    'partial' => $partialCount++,
                    'reject' => $rejectedCount++,
                };
            }

            // 2. Calculate new header status considering partial approvals
            $newRequestStatus = match (true) {
                // All rejected -> request rejected
                $rejectedCount === $totalDecisions => 'rejected',
                
                // All approved (no partials or rejects) -> approved
                $approvedCount === $totalDecisions => 'approved',
                
                // Mixed states or any partial -> partially approved
                default => 'partially_approved',
            };

            // 3. Update header with calculated status
            $this->requestRepo->updateStatus(
                id: $dto->requestId,
                status: $newRequestStatus,
                approvedBy: $dto->approverId,
                notes: $newRequestStatus === 'rejected' ? $dto->notes : null
            );

            Event::dispatch(new MaterialRequestApproved(
                requestId: $dto->requestId,
                projectId: $request->project_id,
                approvedBy: $dto->approverId,
                status: $newRequestStatus,
                requestData: [
                    'requested_by' => $request->requested_by,
                    'approved_by_name' => $dto->approverName ?? null,
                    'notes' => $dto->notes,
                ]
            ));

            return true;
        });
    }
}