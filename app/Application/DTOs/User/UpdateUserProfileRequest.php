<?php
// app/Application/DTOs/User/UpdateUserProfileRequest.php

namespace App\Application\DTOs\User;

use InvalidArgumentException;

/**
 * DTO for updating a user's profile information.
 * 
 * Supports both legacy and new role architecture:
 * - Legacy: $role ('Admin', 'Usuario', 'Cliente') - @deprecated
 * - New: $is_admin (bool) + $role_id (string, e.g., 'H3')
 * 
 * @package App\Application\DTOs\User
 */
class UpdateUserProfileRequest
{
    public function __construct(
        // Required fields
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $user_name,
        public readonly string $email,
        
        //  NEW: System admin flag (source of truth)
        public readonly bool $is_admin = false,
        
        //  NEW: Hierarchical role ID (e.g., 'H3', 'H8')
        public readonly ?string $role_id = null,
        
        // Optional fields
        public readonly ?string $department = null,
        public readonly ?string $phone_crm = null,
        public readonly ?string $reports_to_id = null,
        
        // Optional: user status update
        public readonly ?string $status = null,
        
        //  LEGACY: For backward compatibility only - @deprecated
        // If provided, will be ignored in favor of is_admin + role_id
        public readonly ?string $role = null,
    ) {
        $this->validate();
    }

    /**
     * Validate DTO properties.
     * 
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        // ID validation
        if ($this->id <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        // Name validations
        if (trim($this->first_name) === '') {
            throw new InvalidArgumentException('First name cannot be empty');
        }
        if (trim($this->last_name) === '') {
            throw new InvalidArgumentException('Last name cannot be empty');
        }
        if (strlen($this->first_name) > 100 || strlen($this->last_name) > 100) {
            throw new InvalidArgumentException('Names cannot exceed 100 characters');
        }

        // Username validation
        $userName = trim($this->user_name);
        if ($userName === '') {
            throw new InvalidArgumentException('Username cannot be empty');
        }
        if (strlen($userName) < 3 || strlen($userName) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }

        // Email validation
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        if (strlen($this->email) > 255) {
            throw new InvalidArgumentException('Email cannot exceed 255 characters');
        }

        // Role ID validation (if provided)
        if ($this->role_id !== null && $this->role_id !== '') {
            if (!preg_match('/^H\d+$/', $this->role_id)) {
                throw new InvalidArgumentException(
                    "Invalid role_id format. Expected 'H' + number (e.g., 'H3'), got '{$this->role_id}'"
                );
            }
        }

        // Status validation (if provided)
        if ($this->status !== null && $this->status !== '') {
            $validStatuses = ['Active', 'Inactive', 'Pending'];
            if (!in_array($this->status, $validStatuses, true)) {
                throw new InvalidArgumentException(
                    "Invalid status '{$this->status}'. Valid values: " . implode(', ', $validStatuses)
                );
            }
        }

        // Phone sanitization check (basic)
        if ($this->phone_crm !== null && $this->phone_crm !== '') {
            $sanitized = preg_replace('/[^0-9+\-\s()]/', '', $this->phone_crm);
            if ($sanitized === '') {
                throw new InvalidArgumentException('Invalid phone number format');
            }
        }
    }

    /**
     * Factory method to create DTO from controller request data.
     * 
     * Handles both legacy and new field formats:
     * - If `is_admin` is provided, use it (new architecture)
     * - If only `role` is provided, map it to is_admin (legacy fallback)
     * - If `role_id` is provided, use it for hierarchical role
     * 
     * @param array<string, mixed> $data Raw request data (snake_case)
     * @return self
     * @throws InvalidArgumentException If validation fails
     */
    public static function fromArray(array $data): self
    {
        //  Handle legacy role → is_admin mapping (for backward compatibility)
        $is_admin = false;
        
        if (isset($data['is_admin'])) {
            // New architecture: use direct boolean
            $is_admin = in_array($data['is_admin'], [true, 1, '1', 'on', 'yes'], true);
        } elseif (isset($data['role'])) {
            // Legacy fallback: map role string to is_admin
            $role = trim((string) $data['role']);
            $is_admin = ($role === 'Admin');
        }

        //  Handle role_id: ensure empty string becomes null
        $role_id = isset($data['role_id']) ? trim((string) $data['role_id']) : null;
        if ($role_id === '') {
            $role_id = null;
        }

        //  Handle status: ensure empty string becomes null
        $status = isset($data['status']) ? trim((string) $data['status']) : null;
        if ($status === '') {
            $status = null;
        }

        return new self(
            // Required fields
            id: (int) ($data['id'] ?? 0),
            first_name: trim((string) ($data['first_name'] ?? '')),
            last_name: trim((string) ($data['last_name'] ?? '')),
            user_name: trim((string) ($data['user_name'] ?? '')),
            email: trim((string) ($data['email'] ?? '')),
            
            //  New architecture fields
            is_admin: $is_admin,
            role_id: $role_id,
            
            // Optional fields
            department: isset($data['department']) ? trim((string) $data['department']) : null,
            phone_crm: isset($data['phone_crm']) ? trim((string) $data['phone_crm']) : null,
            reports_to_id: isset($data['reports_to_id']) 
                ? (is_numeric($data['reports_to_id']) ? (int) $data['reports_to_id'] : null) 
                : null,
            status: $status,
            
            //  Legacy field (for backward compatibility only)
            role: isset($data['role']) ? trim((string) $data['role']) : null,
        );
    }

    /**
     * Helper: Get effective is_admin value (new architecture preferred).
     */
    public function getEffectiveIsAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Helper: Get effective role_id (new architecture).
     */
    public function getEffectiveRoleId(): ?string
    {
        return $this->role_id;
    }

    /**
     * Helper: Check if hierarchical role should be updated.
     */
    public function shouldUpdateHierarchicalRole(): bool
    {
        return $this->role_id !== null;
    }

    /**
     * Helper: Check if status should be updated.
     */
    public function shouldUpdateStatus(): bool
    {
        return $this->status !== null;
    }
}
