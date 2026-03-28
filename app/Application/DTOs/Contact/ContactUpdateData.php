<?php

namespace App\Application\DTOs\Contact;

/**
 * DTO for updating an existing Contact.
 * 
 * Groups fields from both vtiger_contactdetails and vtiger_crmentity tables.
 * Provides type-safe access and clear separation of concerns.
 * 
 * @package App\Application\DTOs
 */
class ContactUpdateData
{
    /**
     * @param int $contactId ID of the contact to update
     * @param array $contactDetails Fields for vtiger_contactdetails table (only changed fields)
     * @param array $crmentityData Fields for vtiger_crmentity table (only changed fields)
     * @param int|null $assignedUserId New assigned user ID (smownerid). Null = don't change.
     * @param int $authenticatedUserId User performing the update (for audit/fallback)
     * @param bool $shouldUpdateAssignedUser Whether to update the assigned user field
     */
    public function __construct(
        public readonly int $contactId,
        public readonly array $contactDetails,
        public readonly array $crmentityData,
        public readonly ?int $assignedUserId,
        public readonly int $authenticatedUserId,
        public readonly bool $shouldUpdateAssignedUser = false,
    ) {}

    /**
     * Factory method: Create from raw request array
     * 
     * @param int $contactId ID of contact to update
     * @param array $input Raw input from API request
     * @param int $authenticatedUserId User ID from JWT/auth context
     * @return self
     */
    public static function fromRequest(int $contactId, array $input, int $authenticatedUserId): self
    {
        // Fields that belong to vtiger_contactdetails
        $contactDetailsFields = [
            'firstname', 'lastname', 'email', 'phone', 'mobile', 'title',
            'department', 'accountid', 'salutation', 'fax', 'reportsto',
            'training', 'usertype', 'contacttype', 'otheremail', 'secondaryemail',
            'donotcall', 'emailoptout', 'imagename', 'reference', 'notify_owner',
            'isconvertedfromlead', 'tags'
        ];

        // Fields that belong to vtiger_crmentity (excludes smownerid)
        $crmentityFields = ['description'];

        // Filter and sanitize fields (only include if provided and not empty)
        $contactDetails = array_filter(
            array_intersect_key($input, array_flip($contactDetailsFields)),
            fn($v) => $v !== null && $v !== ''
        );

        $crmentityData = array_filter(
            array_intersect_key($input, array_flip($crmentityFields)),
            fn($v) => $v !== null && $v !== ''
        );

        // Determine whether to update assigned_user_id
        $shouldUpdateAssignedUser = array_key_exists('assigned_user_id', $input);
        $assignedUserId = $shouldUpdateAssignedUser && isset($input['assigned_user_id']) && $input['assigned_user_id'] > 0
            ? (int) $input['assigned_user_id']
            : null;

        return new self(
            contactId: $contactId,
            contactDetails: $contactDetails,
            crmentityData: $crmentityData,
            assignedUserId: $assignedUserId,
            authenticatedUserId: $authenticatedUserId,
            shouldUpdateAssignedUser: $shouldUpdateAssignedUser
        );
    }

    /**
     * Get complete data for vtiger_crmentity update
     * Includes modifiedtime always
     */
    public function getCrmentityData(): array
    {
        $data = array_merge(
            $this->crmentityData,
            [
                'modifiedtime' => now()->format('Y-m-d H:i:s'), // Always update timestamp
            ]
        );

        // Only include smownerid if it should be updated
        if ($this->shouldUpdateAssignedUser && $this->assignedUserId !== null) {
            $data['smownerid'] = $this->assignedUserId;
        }

        return $data;
    }

    /**
     * Check if there are any changes to persist
     */
    public function hasChanges(): bool
    {
        return !empty($this->contactDetails) || 
               !empty($this->crmentityData) || 
               ($this->shouldUpdateAssignedUser && $this->assignedUserId !== null);
    }
}