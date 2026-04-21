<?php
namespace App\Application\DTOs\Role;

class RoleOptionResponse
{
    public function __construct(
        public readonly string $value,      // roleid
        public readonly string $label,      // rolename
        public readonly int $depth,
        public readonly string $parentRole, // ej: "H1::H2::"
    ) {}

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'depth' => $this->depth,
            'parent_role' => $this->parentRole,
        ];
    }

    
    public static function fromDbRow(object $row): self
    {
        return new self(
            value: $row->roleid,
            label: $row->rolename,
            depth: (int) $row->depth,
            parentRole: $row->parentrole ?? '',
        );
    }
}