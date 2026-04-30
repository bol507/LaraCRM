<?php
namespace App\Domain\Entities;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class MaterialRequest
{
    public function __construct(
        private readonly int $id,
        private readonly int $projectId,
        private readonly int $requestedBy,
        private string $status,
        private ?DateTimeImmutable $submittedAt,
        private ?int $approvedBy,
        private ?DateTimeImmutable $approvedAt,
        private ?string $rejectionNotes,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        /** @var MaterialRequestItem[] */
        private array $items = [],
    ) {
        $this->validateStatus($status);
    }

    private function validateStatus(string $status): void
    {
        $allowed = ['draft', 'submitted', 'approved', 'partially_procured', 'fully_procured', 'closed', 'rejected', 'partially_approved'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid MaterialRequest status: {$status}");
        }
    }

    public function submit(): void
    {
        if ($this->status !== 'draft') {
            throw new DomainException('Only draft requests can be submitted');
        }
        if (empty($this->items)) {
            throw new DomainException('Cannot submit a request without items');
        }
        $this->status = 'submitted';
        $this->submittedAt = new DateTimeImmutable();
    }

    public function addItem(MaterialRequestItem $item): void
    {
        if ($this->status !== 'draft') {
            throw new DomainException('Cannot add items to a submitted or approved request');
        }
        $this->items[] = $item;
    }

    public function calculateApprovedItemsCount(): int
    {
        return count(array_filter($this->items, fn($i) => in_array($i->getItemStatus(), ['approved', 'partially_approved'], true)));
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getProjectId(): int { return $this->projectId; }
    public function getRequestedBy(): int { return $this->requestedBy; }
    public function getStatus(): string { return $this->status; }
    public function getSubmittedAt(): ?DateTimeImmutable { return $this->submittedAt; }
    public function getApprovedBy(): ?int { return $this->approvedBy; }
    public function getApprovedAt(): ?DateTimeImmutable { return $this->approvedAt; }
    public function getRejectionNotes(): ?string { return $this->rejectionNotes; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    /** @return MaterialRequestItem[] */
    public function getItems(): array { return $this->items; }
}