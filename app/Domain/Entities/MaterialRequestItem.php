<?php
namespace App\Domain\Entities;

use DomainException;
use InvalidArgumentException;

class MaterialRequestItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $requestId,
        private string $catalogItemType,
        private string $catalogReasonType,
        private string $itemName,
        private float $quantity,
        private string $unit,
        private string $priority,
        private ?float $estimatedCost,
        private ?string $notes,
        private string $itemStatus,
        private ?float $approvedQuantity,
        private ?int $approvedBy,
    ) {
        $this->validate($catalogItemType, $catalogReasonType, $unit, $priority, $itemStatus);
        if ($quantity <= 0) throw new InvalidArgumentException('Quantity must be positive');
        if ($itemName === '') throw new InvalidArgumentException('Item name is required');
    }

    private function validate(string $type, string $reason, string $unit, string $priority, string $status): void
    {
        $allowedTypes = ['material', 'tool', 'consumable', 'service'];
        $allowedStatuses = ['pending', 'approved', 'rejected', 'partially_approved'];
        if (!in_array($type, $allowedTypes, true)) throw new InvalidArgumentException("Invalid item_type: {$type}");
        if (!in_array($status, $allowedStatuses, true)) throw new InvalidArgumentException("Invalid item_status: {$status}");
    }

    public function approve(float $quantity, int $approverId): void
    {
        if ($quantity <= 0 || $quantity > $this->quantity) {
            throw new DomainException('Approved quantity must be between 0 and requested quantity');
        }
        $this->itemStatus = $quantity === $this->quantity ? 'approved' : 'partially_approved';
        $this->approvedQuantity = $quantity;
        $this->approvedBy = $approverId;
    }

    public function reject(int $approverId): void
    {
        $this->itemStatus = 'rejected';
        $this->approvedQuantity = 0.0;
        $this->approvedBy = $approverId;
    }

    public function getId(): int { return $this->id; }
    public function getRequestId(): int { return $this->requestId; }
    public function getCatalogItemType(): string { return $this->catalogItemType; }
    public function getCatalogReasonType(): string { return $this->catalogReasonType; }
    public function getItemName(): string { return $this->itemName; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnit(): string { return $this->unit; }
    public function getPriority(): string { return $this->priority; }
    public function getEstimatedCost(): ?float { return $this->estimatedCost; }
    public function getNotes(): ?string { return $this->notes; }
    public function getItemStatus(): string { return $this->itemStatus; }
    public function getApprovedQuantity(): ?float { return $this->approvedQuantity; }
    public function getApprovedBy(): ?int { return $this->approvedBy; }
}