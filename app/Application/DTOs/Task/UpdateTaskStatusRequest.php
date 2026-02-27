<?php

namespace App\Application\DTOs;

/**
 * DTO for updating task status
 */
class UpdateTaskStatusRequest
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $completedDate = null,
    ) {}

    /**
     * Validate status is allowed
     */
    public function isValidStatus(): bool
    {
        $validStatuses = [
            'Not Started',
            'In Progress',
            'Completed',
            'Pending Input',
            'Planned',
        ];

        return in_array($this->status, $validStatuses, true);
    }
}