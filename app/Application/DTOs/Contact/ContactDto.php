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
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $mobile = null,
        public readonly ?string $title = null,
        public readonly ?string $department = null,
        public readonly int $accountid,
        public readonly ?string $account_name = null,
        public readonly ?string $assigned_user_name = null,
        public readonly ?string $description = null,
        public readonly ?string $createdtime = null,
        public readonly ?string $contact_status = 'Active',
    ) {}

    /**
     * Create DTO from Contact entity
     */
    public static function fromEntity(Contact $contact): self
    {
        return new self(
            contactid: $contact->contactid,
            firstname: $contact->firstname,
            lastname: $contact->lastname,
            email: $contact->email,
            phone: $contact->phone,
            mobile: $contact->mobile,
            title: $contact->title,
            department: $contact->department,
            accountid: $contact->accountid,
            account_name: $contact->account_name,
            assigned_user_name: $contact->assigned_user_name,
            description: $contact->description,
            createdtime: $contact->createdtime,
            contact_status: $contact->contact_status,
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
            'assigned_user_name' => $this->assigned_user_name,
            'description' => $this->description,
            'createdtime' => $this->createdtime,
            'contact_status' => $this->contact_status,
            'full_name' => trim("{$this->firstname} {$this->lastname}"),
        ];
    }
}