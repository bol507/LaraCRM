<?php

namespace App\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialRequestApproved
{
    use Dispatchable, SerializesModels;

    /**
     * @param int $requestId ID of the material request
     * @param int $projectId Project ID
     * @param int $approvedBy ID of the user who approved/rejected
     * @param string $status Final status: 'approved', 'partially_approved', 'rejected'
     * @param array $requestData Contextual data
     */
    public function __construct(
        public readonly int $requestId,
        public readonly int $projectId,
        public readonly int $approvedBy,
        public readonly string $status,
        public readonly array $requestData,
    ) {}
}