<?php
namespace App\Domain\Entities;

use DateTimeImmutable;
use DomainException;

class PurchaseOrder
{
    public function __construct(
        private readonly int $id,
        private readonly string $poNumber,
        private readonly int $projectId,
        private ?int $vendorId,
        private string $status,
        private float $totalAmount,
        private readonly int $createdBy,
        private ?int $approvedBy,
        private readonly DateTimeImmutable $orderDate,
        private ?DateTimeImmutable $expectedDelivery,
        private ?string $notes,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        /** @var PurchaseOrderItem[] */
        private array $items = [],
    ) {}

    public function addItem(PurchaseOrderItem $item): void
    {
        $this->items[] = $item;
        $this->totalAmount += $item->getTotalCost();
    }

    public function markAsSent(): void
    {
        if (!in_array($this->status, ['draft', 'approved'], true)) {
            throw new DomainException('Only draft/approved POs can be marked as sent');
        }
        $this->status = 'sent_to_vendor';
    }

    public function getId(): int { return $this->id; }
    public function getPoNumber(): string { return $this->poNumber; }
    public function getProjectId(): int { return $this->projectId; }
    public function getVendorId(): ?int { return $this->vendorId; }
    public function getStatus(): string { return $this->status; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getCreatedBy(): int { return $this->createdBy; }
    public function getApprovedBy(): ?int { return $this->approvedBy; }
    public function getOrderDate(): DateTimeImmutable { return $this->orderDate; }
    public function getExpectedDelivery(): ?DateTimeImmutable { return $this->expectedDelivery; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    /** @return PurchaseOrderItem[] */
    public function getItems(): array { return $this->items; }
}