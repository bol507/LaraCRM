<?php

namespace App\Application\DTOs;

class CreateQuoteRequest
{
    public function __construct(
        public readonly string $subject,
        public readonly ?int $potentialid,
        public readonly int $accountid,
        public readonly int $assigned_user_id,
        public readonly ?string $validtill,
        public readonly ?string $description,
        /** @var array<array{productid?: int, sequence_no: int, productname: string, quantity: float, listprice: float, discount_percent: float, description?: string}> */
        public readonly array $items
    ) {}
}