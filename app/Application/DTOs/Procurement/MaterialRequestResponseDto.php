<?php
namespace App\Application\DTOs\Procurement;

class MaterialRequestResponseDto
{
    public static function fromEntity(\App\Domain\Entities\MaterialRequest $entity): self
    {
        return new self(
            id: $entity->getId(),
            projectId: $entity->getProjectId(),
            requestedBy: $entity->getRequestedBy(),
            status: $entity->getStatus(),
            submittedAt: $entity->getSubmittedAt()?->format('Y-m-d H:i:s'),
            approvedAt: $entity->getApprovedAt()?->format('Y-m-d H:i:s'),
            rejectionNotes: $entity->getRejectionNotes(),
            createdAt: $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            items: array_map(fn($i) => [
                'id' => $i->getId(),
                'name' => $i->getItemName(),
                'type' => $i->getCatalogItemType(),
                'reason' => $i->getCatalogReasonType(),
                'quantity' => $i->getQuantity(),
                'unit' => $i->getUnit(),
                'priority' => $i->getPriority(),
                'status' => $i->getItemStatus(),
                'approvedQty' => $i->getApprovedQuantity(),
            ], $entity->getItems()),
        );
    }

    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $requestedBy,
        public readonly string $status,
        public readonly ?string $submittedAt,
        public readonly ?string $approvedAt,
        public readonly ?string $rejectionNotes,
        public readonly string $createdAt,
        public readonly array $items,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}