<?php
// app/Domain/Events/MaterialRequestCreated.php

namespace App\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $materialRequestId,
        public readonly int $projectId,
        public readonly int $createdBy,
        public readonly array $requestData, // { id, status, items_count, total_estimated, etc. }
    ) {}
}