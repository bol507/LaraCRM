<?php
namespace App\Application\DTOs\Procurement;

class ApproveMaterialRequestDto
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $approverId,
        /** @var array{itemId: int, action: 'approve'|'reject', quantity: float|null}[] */
        public readonly array $itemDecisions,
        public readonly ?string $notes = null,
    ) {}
}