<?php
namespace App\Application\DTOs\Procurement;

class ListPurchaseOrdersRequestDto
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $page,
        public readonly int $limit,
        public readonly ?string $status = null,
        public readonly ?int $vendorId = null,
    ) {}
}