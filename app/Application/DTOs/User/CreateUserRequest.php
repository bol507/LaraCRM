<?php
// app/Application/DTOs/User/CreateUserRequest.php

namespace App\Application\DTOs\User;

use InvalidArgumentException;

/**
 * DTO for creating a new user with hierarchical role support.
 * 
 * Enforces domain rules for user creation:
 * - Password strength requirements
 * - Role ID format validation (Vtiger: 'H' + number)
 * - Status enum validation
 * - Email/username uniqueness checks (at controller level)
 * 
 * @package App\Application\DTOs\User
 */
class CreateUserRequest
{
    public function __construct(
        // Required identity fields
        public readonly string $user_name,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly string $password,
        
        // ✅ New architecture: System admin flag
        public readonly bool $is_admin = false,
        
        // ✅ New architecture: Hierarchical role ID (nullable for root users)
        public readonly ?string $role_id = null,
        
        // Optional fields with defaults
        public readonly string $status = 'Active',
        public readonly ?string $phone_crm = null,
        public readonly ?string $department = null,
        public readonly ?int $reports_to_id = null,
    ) {
        $this->validate();
    }

    /**
     * Validate DTO properties at instantiation.
     * 
     * @throws InvalidArgumentException If any validation fails
     */
    private function validate(): void
    {
        // === Identity Fields ===
        
        if (trim($this->user_name) === '') {
            throw new InvalidArgumentException('Username cannot be empty');
        }
        if (strlen($this->user_name) < 3 || strlen($this->user_name) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $this->user_name)) {
            throw new InvalidArgumentException('Username can only contain letters, numbers, dots, underscores and hyphens');
        }

        if (trim($this->first_name) === '') {
            throw new InvalidArgumentException('First name cannot be empty');
        }
        if (strlen($this->first_name) > 100) {
            throw new InvalidArgumentException('First name cannot exceed 100 characters');
        }

        if (trim($this->last_name) === '') {
            throw new InvalidArgumentException('Last name cannot be empty');
        }
        if (strlen($this->last_name) > 100) {
            throw new InvalidArgumentException('Last name cannot exceed 100 characters');
        }

        // === Email ===
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        if (strlen($this->email) > 255) {
            throw new InvalidArgumentException('Email cannot exceed 255 characters');
        }

        // === Password Strength ===
        
        if (strlen($this->password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }
        if (!preg_match('/[A-Z]/', $this->password)) {
            throw new InvalidArgumentException('Password must contain at least one uppercase letter');
        }
        if (!preg_match('/[0-9]/', $this->password)) {
            throw new InvalidArgumentException('Password must contain at least one number');
        }

        // === Role ID Format (Vtiger convention: H + number) ===
        
        if ($this->role_id !== null && $this->role_id !== '') {
            if (!preg_match('/^H\d+$/', $this->role_id)) {
                throw new InvalidArgumentException(
                    "Invalid role_id format. Expected 'H' + number (e.g., 'H3'), got '{$this->role_id}'"
                );
            }
        }

        // === Status Enum ===
        
        $validStatuses = ['Active', 'Inactive', 'Pending'];
        if (!in_array($this->status, $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Invalid status '{$this->status}'. Valid values: " . implode(', ', $validStatuses)
            );
        }

        // === Phone Sanitization Check ===
        
        if ($this->phone_crm !== null && $this->phone_crm !== '') {
            $sanitized = preg_replace('/[^0-9+\-\s()]/', '', $this->phone_crm);
            if ($sanitized === '') {
                throw new InvalidArgumentException('Invalid phone number format');
            }
        }

        // === Reports To ID ===
        
        if ($this->reports_to_id !== null && $this->reports_to_id <= 0) {
            throw new InvalidArgumentException('reports_to_id must be a positive integer or null');
        }
    }

    /**
     * Factory method to create DTO from controller request data.
     * 
     * Handles type casting, trimming, and nullable conversion.
     * 
     * @param array<string, mixed> $data Raw request data (snake_case)
     * @return self
     * @throws InvalidArgumentException If validation fails
     */
    public static function fromArray(array $data): self
    {
        // ✅ Helper: convertir string vacío a null para campos opcionales
        $nullIfEmpty = fn(?string $val): ?string => $val !== null && trim($val) === '' ? null : trim($val ?? '');
        
        // ✅ Role ID: empty string → null
        $role_id = $nullIfEmpty($data['role_id'] ?? null);
        
        // ✅ Status: default to 'Active' if empty
        $status = trim($data['status'] ?? 'Active');
        if ($status === '') {
            $status = 'Active';
        }

        // ✅ is_admin: manejar múltiples formatos de booleano
        $is_admin_raw = $data['is_admin'] ?? false;
        $is_admin = is_bool($is_admin_raw) 
            ? $is_admin_raw 
            : in_array(strtolower((string) $is_admin_raw), ['1', 'true', 'yes', 'on'], true);

        return new self(
            // Required identity fields
            user_name: trim((string) ($data['user_name'] ?? '')),
            first_name: trim((string) ($data['first_name'] ?? '')),
            last_name: trim((string) ($data['last_name'] ?? '')),
            email: trim((string) ($data['email'] ?? '')),
            password: (string) ($data['password'] ?? ''), // La validación de fuerza está en validate()
            
            // ✅ New architecture fields
            is_admin: $is_admin,
            role_id: $role_id,
            
            // Optional fields
            status: $status,
            phone_crm: $nullIfEmpty($data['phone_crm'] ?? null),
            department: $nullIfEmpty($data['department'] ?? null),
            reports_to_id: isset($data['reports_to_id']) && is_numeric($data['reports_to_id']) 
                ? (int) $data['reports_to_id'] 
                : null,
        );
    }

    // ==================== Helper Methods ====================

    /**
     * Get sanitized phone number (for persistence layer).
     */
    public function getSanitizedPhone(): ?string
    {
        if ($this->phone_crm === null) {
            return null;
        }
        $sanitized = preg_replace('/[^0-9+\-\s()]/', '', $this->phone_crm);
        return $sanitized !== '' ? $sanitized : null;
    }

    /**
     * Check if hierarchical role should be assigned.
     */
    public function hasHierarchicalRole(): bool
    {
        return $this->role_id !== null && $this->role_id !== '';
    }

    /**
     * Get effective status (ensures valid value).
     */
    public function getEffectiveStatus(): string
    {
        return in_array($this->status, ['Active', 'Inactive', 'Pending'], true) 
            ? $this->status 
            : 'Active';
    }
}