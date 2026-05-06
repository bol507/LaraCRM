<?php
namespace App\Application\DTOs\Procurement;

class GetPurchaseOrderRequestDto
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $poId,
    ) {}
}