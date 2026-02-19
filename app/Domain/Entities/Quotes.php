<?php

namespace App\Domain\Entities;

class Quote
{
    public function __construct(
        public readonly int $quoteid,
        public readonly string $quoteno,
        public readonly string $subject,
        public readonly ?int $potentialid,
        public readonly int $accountid,
        public readonly int $assigned_user_id,
        public readonly string $quote_stage,
        public readonly ?string $validtill, 
        public readonly ?string $description,
        public readonly float $subtotal,
        public readonly ?float $discount_percent, 
        public readonly float $total,
        public readonly string $createdtime,
        public readonly string $modifiedtime,
        public readonly array $items
    ) {}
}
