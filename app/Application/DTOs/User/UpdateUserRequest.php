<?php

namespace App\Application\DTOs\User;

class UpdateUserRequest
{
    public function __construct(
        public readonly ?string $user_name,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $email,
        public readonly ?bool $is_admin,     
        public readonly ?string $role_id,     
        public readonly ?string $status,
        public readonly ?string $phone_crm,
        public readonly ?string $department,
        public readonly ?int $reports_to_id,
        public readonly ?string $password = null,
    ) {}

    /**
     * Factory method para crear desde array de request
     * 
     * @param array $data Datos del request (pueden ser parciales)
     * @return self Instancia del DTO
     */
    public static function fromArray(array $data): self
    {
        return new self(
            user_name: isset($data['user_name']) ? trim($data['user_name']) : null,
            first_name: isset($data['first_name']) ? trim($data['first_name']) : null,
            last_name: isset($data['last_name']) ? trim($data['last_name']) : null,
            email: isset($data['email']) ? trim($data['email']) : null,
            is_admin: isset($data['is_admin']) ? filter_var($data['is_admin'], FILTER_VALIDATE_BOOLEAN) : null,
            role_id: isset($data['role_id']) ? trim($data['role_id']) : null,
            status: $data['status'] ?? null,
            phone_crm: $data['phone_crm'] ?? null,
            department: $data['department'] ?? null,
            reports_to_id: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
            password: $data['password'] ?? null,
        );
    }

    /**
     * Convertir a array para el UseCase/Repository
     * 
     * @return array Campos no-nulos listos para persistencia
     */
    public function toUpdatableArray(): array
    {
        return array_filter([
            'user_name' => $this->user_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email1' => $this->email,             
            'is_admin' => $this->is_admin !== null ? ($this->is_admin ? '1' : '0') : null, 
            'status' => $this->status,
            'phone_crm_extension' => $this->phone_crm,
            'department' => $this->department,
            'reports_to_id' => $this->reports_to_id,
            'password' => $this->password,
        ], fn($v) => $v !== null && $v !== '');
    }
}