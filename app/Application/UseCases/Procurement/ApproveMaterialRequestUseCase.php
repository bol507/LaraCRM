<?php
// app/Application/UseCases/Procurement/ApproveMaterialRequestUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\ApproveMaterialRequestDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Domain\Events\MaterialRequestApprovedEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
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

            // ✅ Contadores para determinar el estado final de la cabecera
            $approvedCount = 0;
            $rejectedCount = 0;
            $partialCount = 0;
            $totalDecisions = count($dto->itemDecisions);

            // 1. ✅ Aplicar decisiones a cada ítem con lógica explícita por acción
            foreach ($dto->itemDecisions as $decision) {
                $action = $decision['action'];
                $itemId = $decision['itemId'];
                
                // ✅ Determinar estado y cantidad aprobada según la acción
                [$newItemStatus, $approvedQty] = match ($action) {
                    'approve' => ['approved', $decision['quantity'] ?? null],
                    'partial' => ['partially_approved', $decision['quantity'] ?? 0], // ✅ Usa cantidad explícita
                    'reject' => ['rejected', 0],
                    default => throw new InvalidArgumentException("Invalid action: {$action}"),
                };

                // ✅ Validación: 'partial' requiere cantidad explícita
                if ($action === 'partial' && ($approvedQty === null || $approvedQty <= 0)) {
                    throw new DomainException("Partial approval requires a positive quantity for item #{$itemId}");
                }

                // ✅ Actualizar ítem en BD
                $this->requestRepo->updateItemStatus(
                    itemId: $itemId,
                    status: $newItemStatus,
                    approvedQty: $approvedQty,
                    approvedBy: $dto->approverId
                );

                // ✅ Contar para cálculo del estado final
                match ($action) {
                    'approve' => $approvedCount++,
                    'partial' => $partialCount++,
                    'reject' => $rejectedCount++,
                };
            }

            // 2. ✅ Calcular nuevo estado de la cabecera considerando parciales
            $newRequestStatus = match (true) {
                // Todos rechazados → solicitud rechazada
                $rejectedCount === $totalDecisions => 'rejected',
                
                // Todos aprobados (sin parciales ni rechazados) → aprobada
                $approvedCount === $totalDecisions => 'approved',
                
                // Mezcla de estados o algún parcial → parcialmente aprobada
                default => 'partially_approved',
            };

            // 3. ✅ Actualizar cabecera con estado calculado
            $this->requestRepo->updateStatus(
                id: $dto->requestId,
                status: $newRequestStatus,
                approvedBy: $dto->approverId,
                notes: $newRequestStatus === 'rejected' ? $dto->notes : null
            );

            // 🔔 Placeholder para WebSocket
            // event(new MaterialRequestApprovedEvent($dto->requestId, $newRequestStatus));

            return true;
        });
    }
}