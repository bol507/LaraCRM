<?php
namespace App\Application\DTOs\Procurement;

use InvalidArgumentException;

class CreateMaterialRequestDto
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $requestedBy,
        /** @var array{name: string, type: string, reason: string, qty: float, unit: string, priority: string, estCost: ?float, notes: ?string}[] */
        public readonly array $items,
    ) {
        foreach ($this->items as $idx => $item) {
            if (empty($item['name']) || $item['qty'] <= 0) {
                throw new InvalidArgumentException("Item #{$idx} requires a valid name and positive quantity");
            }
        }
    }
}