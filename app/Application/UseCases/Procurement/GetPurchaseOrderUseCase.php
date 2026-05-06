<?php
namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\GetPurchaseOrderRequestDto;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use DomainException;

class GetPurchaseOrderUseCase
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $repo
    ) {}

    public function execute(GetPurchaseOrderRequestDto $dto): array
    {
        //TODO check user permissions
        $po = $this->repo->findById($dto->poId, $dto->projectId);

        if (!$po) {
            throw new DomainException('Purchase order not found');
        }

        return $po;
    }
}