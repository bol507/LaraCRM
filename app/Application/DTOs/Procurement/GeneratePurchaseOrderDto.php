<?php
// app/Application/DTOs/Procurement/GeneratePurchaseOrderDto.php

namespace App\Application\DTOs\Procurement;

use InvalidArgumentException;

/**
 * DTO for generating a Purchase Order from approved items
 * 
 * Only validates formats and data presence.
 * Business rules (item status, permissions, total calculation)
 * belong in the UseCase/Repository layer.
 */
class GeneratePurchaseOrderDto
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $vendorId,
        public readonly int $createdById,
        /** @var int[] IDs of material_request_items in 'approved' or 'partially_approved' status */
        public readonly array $approvedItemIds,
        public readonly ?string $poNotes = null,
        public readonly ?string $expectedDelivery = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->projectId <= 0) {
            throw new InvalidArgumentException('projectId must be a positive integer');
        }

        if ($this->vendorId <= 0) {
            throw new InvalidArgumentException('vendorId must be a positive integer');
        }

        if ($this->createdById <= 0) {
            throw new InvalidArgumentException('createdById must be a positive integer');
        }

        if (empty($this->approvedItemIds)) {
            throw new InvalidArgumentException('approvedItemIds cannot be empty');
        }

        // Validate that all IDs are positive integers
        foreach ($this->approvedItemIds as $idx => $id) {
            if (!is_int($id) || $id <= 0) {
                throw new InvalidArgumentException("approvedItemIds[{$idx}] must be a positive integer");
            }
        }

        // Validate date format if provided
        if ($this->expectedDelivery !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->expectedDelivery)) {
            throw new InvalidArgumentException('expectedDelivery must be in Y-m-d format');
        }
    }

    /**
     * Factory helper for creating from validated Request data (Controller)
     */
    public static function fromValidatedData(array $data): self
    {
        return new self(
            projectId: (int) $data['project_id'],
            vendorId: isset($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            createdById: (int) $data['created_by_id'],
            approvedItemIds: array_map('intval', (array) ($data['approved_item_ids'] ?? [])),
            poNotes: $data['po_notes'] ?? null,
            expectedDelivery: $data['expected_delivery'] ?? null,
        );
    }
}