<?php

namespace App\Domain\Entities;

class Opportunity
{
    public function __construct(
        public readonly int $potentialid,
        public readonly string $potential_no,
        public readonly string $potentialname, 
        public readonly ?float $amount = null,
        public readonly ?string $closingdate = null,
        public readonly string $sales_stage,
        public readonly ?int $probability = null,
        public readonly ?int $related_to = null, 
        public readonly ?string $related_to_name = null,
        public readonly ?int $assigned_user_id = null, 
        public readonly ?string $assigned_user_name = null,
        public readonly ?string $description = null,
        public readonly bool $is_active = true
    ) {}
}