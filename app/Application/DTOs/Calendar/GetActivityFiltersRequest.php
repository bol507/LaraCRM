<?php

namespace App\Application\DTOs\Calendar;

use InvalidArgumentException;

class GetActivityFiltersRequest
{
    public function __construct(
        public readonly int $currentUserId,
        public readonly string $currentUserRoleId,
        public readonly ?int $requestedUserId = null,  // assignedTo
    ) {
        if ($currentUserId <= 0) {
            throw new InvalidArgumentException("Current user ID must be positive");
        }
    }
}