<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Represents a user entity with business rules and validation.
 * 
 * This entity enforces domain rules for user data integrity and provides
 * meaningful business methods instead of simple getters/setters.
 * 
 * @package App\Domain\Entities
 * @see \App\Application\UseCases\User\UpdateUserProfileUseCase
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 */
class User
{
    private int $id;
    private string $userName;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $role;
    private string $status;
    private ?string $phoneCrm;
    private ?string $department;
    private ?int $reportsToId;
    private bool $isActive;

    /**
     * Constructor with business rule validation
     * 
     * @param int $id Unique user identifier
     * @param string $userName Username for authentication
     * @param string $firstName User's first name
     * @param string $lastName User's last name
     * @param string $email User's email address
     * @param string $role User's role (Admin, Usuario, Cliente)
     * @param string $status User's status (Active, Inactive, Pending)
     * @param string|null $phoneCrm User's phone number (optional)
     * @param string|null $department User's department (optional)
     * @param int|null $reportsToId ID of supervisor user (optional)
     * @param bool $isActive Whether the user account is active
     * 
     * @throws InvalidArgumentException If data does not comply with business rules
     */
    public function __construct(
        int $id,
        string $userName,
        string $firstName,
        string $lastName,
        string $email,
        string $role,
        string $status,
        ?string $phoneCrm = null,
        ?string $department = null,
        ?int $reportsToId = null,
        bool $isActive = true
    ) {
        $this->validateId($id);
        $this->validateUserName($userName);
        $this->validateName($firstName, 'first_name');
        $this->validateName($lastName, 'last_name');
        $this->validateEmail($email);
        $this->validateRole($role);
        $this->validateStatus($status);
        
        $this->id = $id;
        $this->userName = $userName;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->role = $role;
        $this->status = $status;
        $this->phoneCrm = $this->sanitizePhone($phoneCrm);
        $this->department = $department;
        $this->reportsToId = $reportsToId;
        $this->isActive = $isActive;
    }

    
    /**
     * Get the unique user identifier
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the username for authentication
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * Get the user's first name
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Get the user's last name
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Get the user's full name (first + last)
     */
    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /**
     * Get the user's email address
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the user's role
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the user's status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the user's phone number (if set)
     */
    public function getPhoneCrm(): ?string
    {
        return $this->phoneCrm;
    }

    /**
     * Get the user's department (if set)
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * Get the ID of the supervisor user (if set)
     */
    public function getReportsToId(): ?int
    {
        return $this->reportsToId;
    }

    /**
     * Check if the user account is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }


    /**
     * Change the user's email address with validation
     * 
     * @param string $newEmail The new email address
     * @throws InvalidArgumentException If the email format is invalid
     */
    public function changeEmail(string $newEmail): void
    {
        $this->validateEmail($newEmail);
        $this->email = $newEmail;
    }

    /**
     * Change the user's phone number with sanitization
     * 
     * @param string|null $newPhone The new phone number (or null to clear)
     */
    public function changePhone(?string $newPhone): void
    {
        $this->phoneCrm = $this->sanitizePhone($newPhone);
    }

    /**
     * Activate the user account
     * 
     * Business rule: Can only activate if currently inactive
     * 
     * @throws \DomainException If user is already active
     */
    public function activate(): void
    {
        if ($this->isActive) {
            throw new \DomainException('User is already active');
        }
        $this->isActive = true;
        $this->status = 'Active';
    }

    /**
     * Deactivate the user account
     * 
     * Business rule: Cannot deactivate admin users
     * 
     * @throws \DomainException If user is an admin or already inactive
     */
    public function deactivate(): void
    {
        if ($this->role === 'Admin') {
            throw new \DomainException('Cannot deactivate admin user');
        }
        if (!$this->isActive) {
            throw new \DomainException('User is already inactive');
        }
        $this->isActive = false;
        $this->status = 'Inactive';
    }

    /**
     * Check if the user has administrator privileges
     */
    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    /**
     * Check if the user can view sensitive data
     * 
     * Business rule: Only active administrators can view sensitive data
     */
    public function canViewSensitiveData(): bool
    {
        return $this->isAdmin() && $this->isActive;
    }



    /**
     * Validate that the user ID is positive
     * 
     * @param int $id The user ID to validate
     * @throws InvalidArgumentException If ID is not positive
     */
    private function validateId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }
    }

    /**
     * Validate the username format and length
     * 
     * @param string $userName The username to validate
     * @throws InvalidArgumentException If username is empty or invalid length
     */
    private function validateUserName(string $userName): void
    {
        if (trim($userName) === '') {
            throw new InvalidArgumentException('Username cannot be empty');
        }
        if (strlen($userName) < 3 || strlen($userName) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }
    }

    /**
     * Validate a name field (first name or last name)
     * 
     * @param string $name The name to validate
     * @param string $fieldName The field name for error messages
     * @throws InvalidArgumentException If name is empty or too long
     */
    private function validateName(string $name, string $fieldName): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException(ucfirst($fieldName) . ' cannot be empty');
        }
        if (strlen($name) > 100) {
            throw new InvalidArgumentException(ucfirst($fieldName) . ' cannot exceed 100 characters');
        }
    }

    /**
     * Validate email format and length
     * 
     * @param string $email The email to validate
     * @throws InvalidArgumentException If email format is invalid or too long
     */
    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        if (strlen($email) > 255) {
            throw new InvalidArgumentException('Email cannot exceed 255 characters');
        }
    }

    /**
     * Validate that the role is one of the allowed values
     * 
     * @param string $role The role to validate
     * @throws InvalidArgumentException If role is not valid
     */
    private function validateRole(string $role): void
    {
        $validRoles = ['Admin', 'Usuario', 'Cliente'];
        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException(
                "Invalid role '{$role}'. Valid roles are: " . implode(', ', $validRoles)
            );
        }
    }

    /**
     * Validate that the status is one of the allowed values
     * 
     * @param string $status The status to validate
     * @throws InvalidArgumentException If status is not valid
     */
    private function validateStatus(string $status): void
    {
        $validStatuses = ['Active', 'Inactive', 'Pending'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Invalid status '{$status}'. Valid statuses are: " . implode(', ', $validStatuses)
            );
        }
    }

    /**
     * Sanitize phone number by removing non-numeric characters
     * 
     * @param string|null $phone The phone number to sanitize
     * @return string|null The sanitized phone number or null
     */
    private function sanitizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        // Remove non-numeric characters except + and -
        return preg_replace('/[^0-9+\-]/', '', trim($phone)) ?: null;
    }


    /**
     * Create a new User instance from an array
     * 
     * Useful for repositories when mapping database rows to entities.
     * 
     * @internal Used only by infrastructure layer (repositories)
     * @param array $data Associative array with user data
     * @return self New User instance
     * @throws InvalidArgumentException If data is invalid
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            userName: $data['user_name'] ?? '',
            firstName: $data['first_name'] ?? '',
            lastName: $data['last_name'] ?? '',
            email: $data['email'] ?? '',
            role: $data['role'] ?? 'Usuario',
            status: $data['status'] ?? 'Active',
            phoneCrm: $data['phone_crm'] ?? null,
            department: $data['department'] ?? null,
            reportsToId: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
            isActive: ($data['is_active'] ?? true) && ($data['status'] ?? 'Active') === 'Active'
        );
    }

    /**
     * Convert entity to array for persistence
     * 
     * Useful for repositories when mapping entities to database rows.
     * 
     * @internal Used only by infrastructure layer (repositories)
     * @return array Associative array with user data
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->userName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'phone_crm' => $this->phoneCrm,
            'department' => $this->department,
            'reports_to_id' => $this->reportsToId,
            'is_active' => $this->isActive,
        ];
    }
}