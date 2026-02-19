<?php

namespace App\Domain\Entities;

class QuoteItem
{
    public function __construct(
        public readonly int $quoteid,
        public readonly ?int $productid,
        public readonly int $sequence_no,
        public readonly string $productname, // ← descripción larga y personalizable
        public readonly float $quantity,
        public readonly float $listprice,
        public readonly float $discount_percent,
        public readonly float $netprice,
        public readonly float $total,
        public readonly ?string $description
    ) {}
}