<?php

namespace App\Application\DTOs\Contact;

use App\Domain\Entities\Contact;

/**
 * DTO for Contact API responses
 *
 * Contains only the fields required for the frontend
 */
class ContactDto
{
    public function __construct(
        public readonly int $contactid,
        public readonly ?string $firstname,
        public readonly string $lastname,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $mobile = null,
        public readonly ?string $title = null,
        public readonly ?string $department = null,
        public readonly ?int $accountid = null,
        public readonly ?string $account_name = null,
        public readonly ?int $assigned_user_id = null,
        public readonly ?string $assigned_user_name = null,
        public readonly ?string $description = null,
        public readonly ?string $createdtime = null,
        public readonly ?string $contact_status = 'Active',
    ) {}

    /**
     * Create DTO from Contact entity or database row
     *
     * @param  Contact|object  $contact
     */
    public static function fromEntity(mixed $contact): self
    {
        return new self(
            contactid: (int) ($contact->contactid ?? $contact->contactid),
            firstname: $contact->firstname ?? null,
            lastname: $contact->lastname ?? '',
            email: $contact->email ?? null,
            phone: $contact->phone ?? null,
            mobile: $contact->mobile ?? null,
            title: $contact->title ?? null,
            department: $contact->department ?? null,
            accountid: ! empty($contact->accountid) ? (int) $contact->accountid : null,
            account_name: $contact->accountname ?? $contact->account_name ?? null,
            assigned_user_id: ! empty($contact->assigned_user_id) ? (int) $contact->assigned_user_id : null,
            assigned_user_name: $contact->assigned_user_name ?? $contact->user_name ?? null,
            description: $contact->description ?? null,
            createdtime: $contact->createdtime ?? null,
            contact_status: $contact->contact_status ?? 'Active',
        );
    }

    /**
     * Create simplified DTO for search/autocomplete
     */
    public static function forSearch(Contact $contact): array
    {
        return [
            'id' => $contact->contactid,
            'firstname' => $contact->firstname,
            'lastname' => $contact->lastname,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->mobile,
            'title' => $contact->title,
            'account_name' => $contact->account_name,
            'full_name' => $contact->getFullName(),
        ];
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'contactid' => $this->contactid,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'title' => $this->title,
            'department' => $this->department,
            'accountid' => $this->accountid,
            'account_name' => $this->account_name,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->assigned_user_name,
            'description' => $this->description,
            'createdtime' => $this->createdtime,
            'contact_status' => $this->contact_status,
            'full_name' => trim("{$this->firstname} {$this->lastname}"),
        ];
    }
}
