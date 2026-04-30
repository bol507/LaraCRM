<?php

namespace App\Application\DTOs\Calendar;

class GetActivityFiltersResponse
{
    public function __construct(
        public readonly ?int $requestedUserId,    // null = user is the creator
        public readonly ?array $subordinateIds,    // null = admin ,array IDs  of users who are subordinates
        public readonly bool $canViewAllUsers,    // is admin
        public readonly bool $canViewOtherUsers, // is admin or subordinate
    ) {}
}