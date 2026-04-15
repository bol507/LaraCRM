<?php
// app/Application/UseCases/Purchases/GetPurchaseUseCase.php

namespace App\Application\UseCases\Purchase;

use App\Domain\Entities\Purchase;
use App\Infrastructure\Repositories\PurchaseRepository;
use InvalidArgumentException;
use RuntimeException;

class GetPurchaseUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    /**
     * Execute the get purchase use case.
     *
     * @param int $purchaseId Purchase order ID to retrieve
     * @return Purchase Purchase entity
     *
     * @throws InvalidArgumentException If purchaseId is invalid
     * @throws RuntimeException If purchase not found
     */
    public function execute(int $purchaseId): Purchase
    {
        // 1. Validate parameters
        $this->validateParameters($purchaseId);

        // 2. Retrieve purchase from repository
        $purchase = $this->repository->findById($purchaseId);

        // 3. Handle not found
        if (!$purchase) {
            throw new RuntimeException("Purchase order {$purchaseId} not found or has been deleted");
        }

        // 4. Return entity
        return $purchase;
    }

    /**
     * Validate input parameters.
     *
     * @param int $purchaseId Purchase order ID
     * @throws InvalidArgumentException If purchaseId is invalid
     */
    private function validateParameters(int $purchaseId): void
    {
        if ($purchaseId <= 0) {
            throw new InvalidArgumentException('Purchase order ID must be a positive integer');
        }
    }
}