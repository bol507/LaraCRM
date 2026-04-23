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
    // ==================== PROPERTIES ====================
    // Core identity
    private int $id;
    private string $userName;
    private string $firstName;
    private string $lastName;
    private string $email;
    
    // Status & contact
    private string $status;
    private ?string $phoneCrm;
    private ?string $department;
    private ?int $reportsToId;
    private bool $isActive;
    //  Legacy auth (deprecated)
    /**
    * @deprecated Use role_id + rolename instead.
    * Legacy field for backward compatibility with old frontend components.
    * Will be removed in v2.0.
    */
    private string $role; // 'Admin', 'Usuario', 'Cliente' - Do not use this field
    // New hierarchical role architecture (source of truth)
    private bool $is_admin;
    private ?string $role_id;
    private ?string $rolename;
    private int $role_depth = 0;          // Depth in hierarchy: 0, 1, 2...
    private ?string $role_parent = null;  // Parent path: 'H1::H2::'
    private int $sharing_rule = 1;        // Data sharing rule: 0,1,2,3
    
    // ==================== CONSTRUCTOR ====================
    
    public function __construct(
        // Core identity (required)
        int $id,
        string $userName,
        string $firstName,
        string $lastName,
        string $email,
        
        // Status & contact
        string $status,
        ?string $phoneCrm = null,
        ?string $department = null,
        ?int $reportsToId = null,
        bool $isActive = true,
        
        // ✅ New role architecture (primary)
        bool $is_admin = false,
        ?string $role_id = null,
        ?string $rolename = null,
        int $role_depth = 0,
        ?string $role_parent = null,
        int $sharing_rule = 1,
        
        // Legacy role (fallback only, deprecated)
        ?string $role = null, // Nullable: computed if not provided
    ) {
        // Validations
        $this->validateId($id);
        $this->validateUserName($userName);
        $this->validateName($firstName, 'first_name');
        $this->validateName($lastName, 'last_name');
        $this->validateEmail($email);
        $this->validateStatus($status);

        // Assign core fields
        $this->id = $id;
        $this->userName = $userName;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->status = $status;
        $this->phoneCrm = $this->sanitizePhone($phoneCrm);
        $this->department = $department;
        $this->reportsToId = $reportsToId;
        $this->isActive = $isActive;
        
        // New role fields
        $this->is_admin = $is_admin;
        $this->role_id = $role_id;
        $this->rolename = $rolename;
        $this->role_depth = $role_depth;
        $this->role_parent = $role_parent;
        $this->sharing_rule = $sharing_rule;
        
        // Legacy role: compute fallback only if not provided
        $this->role = $role ?? $this->computeLegacyRole($is_admin, $rolename);
    }


    // ==================== GETTERS ====================

    public function getId(): int { return $this->id; }
    public function getUserName(): string { return $this->userName; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    
    public function getFullName(): string {
        return trim("{$this->firstName} {$this->lastName}");
    }
    
    public function getEmail(): string { return $this->email; }
    public function getStatus(): string { return $this->status; }
    public function getPhoneCrm(): ?string { return $this->phoneCrm; }
    public function getDepartment(): ?string { return $this->department; }
    public function getReportsToId(): ?int { return $this->reportsToId; }
    public function isActive(): bool { return $this->isActive; }


    public function getIsAdmin(): bool { return $this->is_admin; }
    public function getRoleId(): ?string { return $this->role_id; }
    public function getRoleName(): ?string { return $this->rolename; }
    public function getRoleDepth(): int { return $this->role_depth; }
    public function getRoleParent(): ?string { return $this->role_parent; }
    public function getSharingRule(): int { return $this->sharing_rule; }

    // @deprecated version
    public function getRole(): string {
        if ($this->is_admin) {
            return 'Admin';
        }
        return $this->rolename ?? $this->role ?? 'Usuario';
    }

     /**
     * Compute legacy role string for backward compatibility.
     * Private helper - not exposed as public API.
     */
    private function computeLegacyRole(bool $isAdmin, ?string $rolename): string
    {
        if ($isAdmin) {
            return 'Admin';
        }
        // If rolename is provided, use it
        return $rolename ?? 'Usuario';
    }

    /**
     * Get full hierarchical path INCLUDING current role.
     * Example: role_parent='H1::H2::', role_id='H3' → ['H1', 'H2', 'H3']
     */
    public function getRolePath(): array
    {
        $path = $this->role_parent 
            ? array_filter(explode('::', trim($this->role_parent, ':')), fn($id) => !empty($id))
            : [];
        
        // Add current role if not already included
        if ($this->role_id && !in_array($this->role_id, $path, true)) {
            $path[] = $this->role_id;
        }
        
        return $path;
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Check if user has system administrator privileges
     * 
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

     /**
     * Check if user can view sensitive data
     * Business rule: Active admins OR executive hierarchical roles
     */
    public function canViewSensitiveData(): bool
    {
        if (!$this->isActive) {
            return false;
        }
        
        //  Admin system OR roles ejecutivos configurados
        if ($this->isAdmin()) {
            return true;
        }
        
        //  Opcional: permitir roles jerárquicos ejecutivos
        $executiveRoles = ['CEO', 'Vice President', 'Director General'];
        return in_array($this->rolename, $executiveRoles, true);
    }

    /**
     * Check if user can manage other users
     * Business rule: Only system admins
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin() && $this->isActive;
    }

    /**
     * Change email with validation
     */
    public function changeEmail(string $newEmail): void
    {
        $this->validateEmail($newEmail);
        $this->email = $newEmail;
    }

    /**
     * Change phone with sanitization
     */
    public function changePhone(?string $newPhone): void
    {
        $this->phoneCrm = $this->sanitizePhone($newPhone);
    }

    /**
     * Activate user account
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
     * Deactivate user account
     *  Business rule: Cannot deactivate SYSTEM administrators (is_admin=true)
     */
    public function deactivate(): void
    {
        if ($this->is_admin) {
            throw new \DomainException('Cannot deactivate a system administrator');
        }
        if (!$this->isActive) {
            throw new \DomainException('User is already inactive');
        }
        $this->isActive = false;
        $this->status = 'Inactive';
    }

    /**
     * Assign a new hierarchical role to this user
     * For use in domain services, not direct UI calls
     */
    public function assignRole(string $roleId, string $roleName, int $depth, ?string $parent = null, int $sharingRule = 1): void
    {
        $this->role_id = $roleId;
        $this->rolename = $roleName;
        $this->role_depth = $depth;
        $this->role_parent = $parent;
        $this->sharing_rule = $sharingRule;
        $this->role = $this->computeLegacyRole($this->is_admin, $roleName);
    }

     // ==================== VALIDATION ====================

    private function validateId(int $id): void {
        if ($id <= 0) throw new InvalidArgumentException('User ID must be positive');
    }

    private function validateUserName(string $userName): void {
        if (trim($userName) === '') throw new InvalidArgumentException('Username cannot be empty');
        if (strlen($userName) < 3 || strlen($userName) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }
    }

    private function validateName(string $name, string $fieldName): void {
        if (trim($name) === '') throw new InvalidArgumentException(ucfirst($fieldName) . ' cannot be empty');
        if (strlen($name) > 100) throw new InvalidArgumentException(ucfirst($fieldName) . ' cannot exceed 100 characters');
    }

    private function validateEmail(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email format');
        if (strlen($email) > 255) throw new InvalidArgumentException('Email cannot exceed 255 characters');
    }

    private function validateStatus(string $status): void {
        $valid = ['Active', 'Inactive', 'Pending'];
        if (!in_array($status, $valid, true)) {
            throw new InvalidArgumentException("Invalid status '{$status}'. Valid: " . implode(', ', $valid));
        }
    }

    // @deprecated version
    private function validateRole(string $role): void {
        $valid = ['Admin', 'Usuario', 'Cliente'];
        if (!in_array($role, $valid, true)) {
            throw new InvalidArgumentException("Invalid role '{$role}'. Valid: " . implode(', ', $valid));
        }
    }

     private function sanitizePhone(?string $phone): ?string {
        if ($phone === null) return null;
        $sanitized = preg_replace('/[^0-9+\-\s()]/', '', trim($phone));
        return $sanitized !== '' ? $sanitized : null;
    }


    /**
     * Create User from array (repository mapping)
     * 
     */
    public static function fromArray(array $data): self
    {
        $firstName = trim($data['first_name'] ?? '') ?: ($data['user_name'] ?? 'Usuario');
        $lastName  = trim($data['last_name'] ?? '') ?: '';
        
       
        $is_admin_raw = $data['is_admin'] ?? '0';
        $is_admin = in_array($is_admin_raw, ['1', 'on', 'yes', true, 1], true);

        return new self(
            // Core identity
            id: (int) ($data['id'] ?? 0),
            userName: $data['user_name'] ?? '',
            firstName: $firstName,
            lastName: $lastName,
            email: trim($data['email'] ?? $data['email1'] ?? ''),
            
            // Status & contact
            status: $data['status'] ?? 'Active',
            phoneCrm: $data['phone_crm'] ?? $data['phone_crm_extension'] ?? null,
            department: $data['department'] ?? null,
            reportsToId: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
            isActive: ($data['is_active'] ?? true) && ($data['status'] ?? 'Active') === 'Active',
            
            //  New role architecture (from DB)
            is_admin: $is_admin,
            role_id: $data['role_id'] ?? null,
            rolename: $data['rolename'] ?? null,
            role_depth: (int) ($data['role_depth'] ?? 0),
            role_parent: $data['role_parent'] ?? null,
            sharing_rule: (int) ($data['sharing_rule'] ?? 1),
            
            // ⚠️ Legacy role: computed automatically if not provided
            role: $data['role'] ?? null, 
        );
    }

    /**
     * Convert entity to array for API response
     * Includes ALL fields needed by frontend (legacy + new)
     */
    public function toArray(): array
    {
        return [
            // Core identity
            'id' => $this->id,
            'user_name' => $this->userName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'email' => $this->email,
            
            // Status & contact
            'status' => $this->status,
            'phone_crm' => $this->phoneCrm,
            'department' => $this->department,
            'reports_to_id' => $this->reportsToId,
            'is_active' => $this->isActive,
            
            //  New role architecture (PRIMARY - frontend moderno debe usar estos)
            'is_admin' => $this->is_admin,
            'role_id' => $this->role_id,
            'rolename' => $this->rolename,
            'role_depth' => $this->role_depth,
            'role_parent' => $this->role_parent,
            'sharing_rule' => $this->sharing_rule,
            
            // ⚠️ Legacy role (fallback only, @deprecated)
            'role' => $this->getRole(),
        ];
    }
}
