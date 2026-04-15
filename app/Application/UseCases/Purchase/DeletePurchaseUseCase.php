<?php

namespace App\Application\UseCases\Purchase;

use App\Infrastructure\Repositories\PurchaseRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DeletePurchaseUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    /**
     * Execute the delete purchase use case.
     *
     * @param int $purchaseId Purchase order ID to delete
     * @return bool True if deleted successfully
     *
     * @throws InvalidArgumentException If purchaseId is invalid
     * @throws RuntimeException If purchase not found
     */
    public function execute(int $purchaseId): bool
    {
        // 1. Validate parameters
        $this->validateParameters($purchaseId);

        // 2. Check if purchase exists
        $purchase = $this->repository->findById($purchaseId);
        if (!$purchase) {
            throw new RuntimeException("Purchase order {$purchaseId} not found or has been deleted");
        }

        // 3. Delete in transaction
        return DB::connection('vtiger')->transaction(function () use ($purchaseId) {
            $now = now()->format('Y-m-d H:i:s');

            // 4. Mark vtiger_crmentity as deleted (soft delete)
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $purchaseId)
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => $now,
                ]);

            // 5. Delete purchase items
            DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->where('id', $purchaseId)
                ->delete();

            return true;
        });
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