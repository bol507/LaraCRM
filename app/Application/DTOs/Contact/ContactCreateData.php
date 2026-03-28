<?php

namespace App\Application\DTOs\Contact;

/**
 * DTO for creating a new Contact.
 * 
 * Groups fields from both vtiger_contactdetails and vtiger_crmentity tables.
 * Provides type-safe access and clear separation of concerns.
 * 
 * @package App\Application\DTOs
 */
class ContactCreateData
{
    /**
     * @param array $contactDetails Fields for vtiger_contactdetails table
     * @param array $crmentityData Fields for vtiger_crmentity table (excluding smownerid)
     * @param int $createdByUserId User who creates the contact (smcreatorid)
     * @param int|null $assignedUserId User assigned to the contact (smownerid). If null, defaults to $createdByUserId
     */
    public function __construct(
        // Fields for vtiger_contactdetails
        public readonly array $contactDetails,
        
        // Fields for vtiger_crmentity (description, etc.)
        public readonly array $crmentityData = [],
        
        // Metadata from authentication context
        public readonly int $createdByUserId,
        public readonly ?int $assignedUserId = null,
    ) {}

    /**
     * Factory method: Create from raw request array
     * 
     * @param array $input Raw input from API request
     * @param int $authenticatedUserId User ID from JWT/auth context
     * @return self
     */
    public static function fromRequest(array $input, int $authenticatedUserId): self
    {
        // ✅ Campos que pertenecen a vtiger_contactdetails
        $contactDetailsFields = [
            'firstname', 'lastname', 'email', 'phone', 'mobile', 'title',
            'department', 'accountid', 'salutation', 'fax', 'reportsto',
            'training', 'usertype', 'contacttype', 'otheremail', 'secondaryemail',
            'donotcall', 'emailoptout', 'imagename', 'reference', 'notify_owner',
            'isconvertedfromlead', 'tags'
        ];

        // ✅ Campos que pertenecen a vtiger_crmentity (excluye smownerid)
        $crmentityFields = ['description'];

        // Filtrar y sanear campos
        $contactDetails = array_filter(
            array_intersect_key($input, array_flip($contactDetailsFields)),
            fn($v) => $v !== null && $v !== ''
        );

        $crmentityData = array_filter(
            array_intersect_key($input, array_flip($crmentityFields)),
            fn($v) => $v !== null && $v !== ''
        );

        // ✅ Determinar assigned_user_id: explícito o fallback a authenticated
        $assignedUserId = isset($input['assigned_user_id']) && $input['assigned_user_id'] > 0
            ? (int) $input['assigned_user_id']
            : null; // null = usar fallback en Use Case

        return new self(
            contactDetails: $contactDetails,
            crmentityData: $crmentityData,
            createdByUserId: $authenticatedUserId,
            assignedUserId: $assignedUserId
        );
    }

    /**
     * Get the final assigned user ID (with fallback to creator)
     */
    public function getFinalAssignedUserId(): int
    {
        return $this->assignedUserId ?? $this->createdByUserId;
    }

    /**
     * Get complete data for vtiger_crmentity insert
     */
    public function getCrmentityData(): array
    {
        return array_merge(
            $this->crmentityData,
            [
                'smownerid' => $this->getFinalAssignedUserId(),
                'smcreatorid' => $this->createdByUserId,
                'setype' => 'Contacts',
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]
        );
    }
}