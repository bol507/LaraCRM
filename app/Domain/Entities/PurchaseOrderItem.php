<?php
// app/Domain/Entities/PurchaseOrderItem.php

namespace App\Domain\Entities;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Purchase Order Item Domain Entity
 * 
 * Represents a single line item within a Purchase Order.
 * Enforces invariants: quantities, costs, and reception limits.
 * 
 * @package App\Domain\Entities
 */
class PurchaseOrderItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $poId,
        private readonly ?int $sourceRequestItemId,
        private readonly string $itemName,
        private readonly float $quantityOrdered,
        private float $quantityReceived,
        private readonly float $unitCost,
        private readonly float $totalCost,
        private ?string $vendorNotes,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
        $this->validateInvariants();
    }

    /**
     * Validate domain invariants upon creation/update
     */
    private function validateInvariants(): void
    {
        if (trim($this->itemName) === '') {
            throw new InvalidArgumentException('Item name is required');
        }
        if ($this->quantityOrdered <= 0) {
            throw new InvalidArgumentException('Quantity ordered must be positive');
        }
        if ($this->unitCost < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative');
        }
        if ($this->quantityReceived < 0 || $this->quantityReceived > $this->quantityOrdered) {
            throw new InvalidArgumentException('Received quantity must be between 0 and ordered quantity');
        }
        
        // Validate cost consistency (tolerance for float rounding)
        $expectedTotal = $this->quantityOrdered * $this->unitCost;
        if (abs($this->totalCost - $expectedTotal) > 0.01) {
            throw new InvalidArgumentException(
                "Total cost ({$this->totalCost}) does not match ordered quantity × unit cost ({$expectedTotal})"
            );
        }
    }

    /**
     * Record partial or full reception of this item
     * 
     * @param float $qty Quantity to add to received amount
     * @throws DomainException If exceeds ordered quantity or is negative
     */
    public function receive(float $qty): void
    {
        if ($qty <= 0) {
            throw new DomainException('Reception quantity must be positive');
        }

        $newReceived = $this->quantityReceived + $qty;
        if ($newReceived > $this->quantityOrdered) {
            throw new DomainException(
                "Cannot receive {$qty}. Max remaining: {$this->getRemainingQuantity()}"
            );
        }

        $this->quantityReceived = $newReceived;
    }

    /**
     * Check if this item has been fully received
     */
    public function isFullyReceived(): bool
    {
        return $this->quantityReceived >= $this->quantityOrdered;
    }

    /**
     * Calculate remaining quantity to be received
     */
    public function getRemainingQuantity(): float
    {
        return max(0.0, $this->quantityOrdered - $this->quantityReceived);
    }

    /**
     * Update vendor notes (mutable operational field)
     */
    public function updateVendorNotes(?string $notes): void
    {
        $this->vendorNotes = $notes;
    }

    // ==================== GETTERS ====================

    public function getId(): int { return $this->id; }
    public function getPoId(): int { return $this->poId; }
    public function getSourceRequestItemId(): ?int { return $this->sourceRequestItemId; }
    public function getItemName(): string { return $this->itemName; }
    public function getQuantityOrdered(): float { return $this->quantityOrdered; }
    public function getQuantityReceived(): float { return $this->quantityReceived; }
    public function getUnitCost(): float { return $this->unitCost; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getVendorNotes(): ?string { return $this->vendorNotes; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
}