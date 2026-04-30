<?php

namespace App\Application\DTOs\Task;

use InvalidArgumentException;

class GetTaskFiltersRequest
{
    public function __construct(
        public readonly int $currentUserId,
        public readonly ?int $requestedUserId = null,  // assignedTo
        public readonly string $currentUserRoleId,
    ) {
        if ($currentUserId <= 0) {
            throw new InvalidArgumentException("Current user ID must be positive");
        }
    }
}