<?php
namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\ListPurchaseOrdersRequestDto;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;

class ListPurchaseOrdersUseCase
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $repo
    ) {}

    public function execute(ListPurchaseOrdersRequestDto $dto): array
    {
        //TODO check user permissions
        
        return $this->repo->findByProjectId($dto);
    }
}