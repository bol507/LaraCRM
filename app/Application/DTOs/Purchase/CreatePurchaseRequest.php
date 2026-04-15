<?php
// app/Application/DTOs/CreatePurchaseRequest.php

namespace App\Application\DTOs\Purchase;

class CreatePurchaseRequest
{
    public function __construct(
        public readonly string $subject,
        public readonly int $projectid,
        public readonly int $vendorid,
        public readonly ?string $podate,
        public readonly ?string $validtill,
        public readonly string $postatus,
        public readonly ?string $description,
        public readonly int $assigned_user_id,
        public readonly array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            subject: $data['subject'],
            projectid: (int) $data['projectid'],
            vendorid: (int) $data['vendorid'],
            podate: $data['podate'] ?? null,
            validtill: $data['validtill'] ?? null,
            postatus: $data['postatus'] ?? 'Draft',
            description: $data['description'] ?? null,
            assigned_user_id: (int) ($data['assigned_user_id'] ?? 1),
            items: $data['items'] ?? [],
        );
    }
}