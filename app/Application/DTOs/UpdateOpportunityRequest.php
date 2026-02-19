<?php

namespace App\Application\DTOs;

class UpdateOpportunityRequest
{
    public function __construct(
        public readonly int $id,
        public readonly string $potentialname,
        public readonly ?float $amount = null,
        public readonly ?string $closingdate = null,
        public readonly string $sales_stage,
        public readonly ?int $probability = null,
        public readonly ?int $related_to = null,
        public readonly ?int $assigned_user_id = null,
        public readonly ?string $description = null
    ) {}
}