<?php

namespace App\Application\DTOs\Role;

class RoleDto {
    public function __construct(
        public readonly string $roleid,
        public readonly string $rolename,
        public readonly string $parentrole,
        public readonly int $depth,
        public readonly int $sharing_rule,
        public readonly int $children_count = 0,
        public readonly int $users_count = 0
    ) {}
}