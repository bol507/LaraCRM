<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Contact Entity - Individual person associated with an Account
 * 
 * @package App\Domain\Entities
 */
class Contact
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
        public readonly int $assigned_user_id = 0,
        public readonly ?string $assigned_user_name = null,
        public readonly ?string $description = null,
        public readonly ?string $createdtime = null,
        public readonly ?string $modifiedtime = null,
        public readonly int $deleted = 0,
        // Fields added by Vtiger
        public readonly ?string $mailingstreet = null,
        public readonly ?string $mailingcity = null,
        public readonly ?string $mailingstate = null,
        public readonly ?string $mailingcountry = null,
        public readonly ?string $mailingzip = null,
        public readonly ?string $otherphone = null,
        public readonly ?string $fax = null,
        public readonly ?string $secondaryemail = null,
        public readonly ?string $assistant = null,
        public readonly ?string $birthdate = null,
        public readonly ?int $reports_to_id = null,
        public readonly ?string $leadsource = null,
        public readonly ?string $contact_status = 'Active',
    ) {}

    /**
     * Create instance from array (for mappers/repositories)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contactid: (int) ($data['contactid'] ?? $data['id'] ?? 0),
            firstname: (string) ($data['firstname'] ?? ''),
            lastname: (string) ($data['lastname'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: $data['phone'] ?? null,
            mobile: $data['mobile'] ?? null,
            title: $data['title'] ?? null,
            department: $data['department'] ?? null,
            accountid: (int) ($data['accountid'] ?? 0),
            account_name: $data['accountname'] ?? $data['account_name'] ?? null,
            assigned_user_id: (int) ($data['assigned_user_id'] ?? 0),
            assigned_user_name: $data['assigned_user_name'] ?? null,
            description: $data['description'] ?? null,
            createdtime: $data['createdtime'] ?? null,
            modifiedtime: $data['modifiedtime'] ?? null,
            deleted: (int) ($data['deleted'] ?? 0),
            mailingstreet: $data['mailingstreet'] ?? null,
            mailingcity: $data['mailingcity'] ?? null,
            mailingstate: $data['mailingstate'] ?? null,
            mailingcountry: $data['mailingcountry'] ?? null,
            mailingzip: $data['mailingzip'] ?? null,
            otherphone: $data['otherphone'] ?? null,
            fax: $data['fax'] ?? null,
            secondaryemail: $data['secondaryemail'] ?? null,
            assistant: $data['assistant'] ?? null,
            birthdate: $data['birthdate'] ?? null,
            reports_to_id: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
            leadsource: $data['leadsource'] ?? null,
            contact_status: $data['contact_status'] ?? 'Active',
        );
    }

    /**
     * Transform to array for serialization/API
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
            'modifiedtime' => $this->modifiedtime,
            'deleted' => $this->deleted,
            'mailingstreet' => $this->mailingstreet,
            'mailingcity' => $this->mailingcity,
            'mailingstate' => $this->mailingstate,
            'mailingcountry' => $this->mailingcountry,
            'mailingzip' => $this->mailingzip,
            'otherphone' => $this->otherphone,
            'fax' => $this->fax,
            'secondaryemail' => $this->secondaryemail,
            'assistant' => $this->assistant,
            'birthdate' => $this->birthdate,
            'reports_to_id' => $this->reports_to_id,
            'leadsource' => $this->leadsource,
            'contact_status' => $this->contact_status,
            // Calculated field for display
            'full_name' => trim("{$this->firstname} {$this->lastname}"),
        ];
    }

    /**
     * Validate basic data of the contact
     * 
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (empty($this->firstname) || empty($this->lastname)) {
            throw new InvalidArgumentException('First name and last name are required');
        }
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        if ($this->accountid <= 0) {
            throw new InvalidArgumentException('The contact must be associated with a valid client');
        }
    }

    /**
     * Check if the contact is active (not deleted)
     */
    public function isActive(): bool
    {
        return $this->deleted === 0 && $this->contact_status === 'Active';
    }

    /**
     * Get formatted full name
     */
    public function getFullName(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}