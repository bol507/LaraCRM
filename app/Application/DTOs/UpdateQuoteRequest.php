<?php

namespace App\Application\DTOs;

class UpdateQuoteRequest
{
    public function __construct(
        public readonly int $quoteid,
        public readonly string $subject,
        public readonly ?int $potentialid,
        public readonly int $accountid,
        public readonly int $assigned_user_id,
        public readonly string $quote_stage,
        public readonly ?string $validtill,
        public readonly ?string $closingdate,
        public readonly ?string $description,
        /** @var array<array{productid?: int, sequence_no: int, productname: string, quantity: float, listprice: float, discount_percent: float, description?: string}> */
        public readonly array $items
    ) {}
}