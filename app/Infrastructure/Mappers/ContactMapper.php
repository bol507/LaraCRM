<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Contact;

/**
 * Mapper between Vtiger database and Contact entity
 */
class ContactMapper
{
    /**
     * Fields that belong to vtiger_contactdetails
     */
    private const CONTACT_DETAILS_FIELDS = [
        'firstname',
        'lastname',
        'email',
        'phone',
        'mobile',
        'title',
        'department',
        'accountid',
        'salutation',
        'fax',
        'reportsto',
        'training',
        'usertype',
        'contacttype',
        'otheremail',
        'secondaryemail',
        'donotcall',
        'emailoptout',
        'imagename',
        'reference',
        'notify_owner',
        'isconvertedfromlead',
        'tags'
    ];

    /**
     * Fields that belong to vtiger_crmentity
     */
    private const CRMENTITY_FIELDS = ['description', 'smownerid'];

    /**
     * Map database row to Contact entity (for reading)
     */
    public static function fromDatabaseRow(object $row): Contact
    {
        return Contact::fromArray([
            // Main fields from vtiger_contactdetails
            'contactid' => (int) $row->contactid,
            'firstname' => $row->firstname ?? '',
            'lastname' => $row->lastname ?? '',
            'email' => $row->email ?? '',
            'phone' => $row->phone ?? null,
            'mobile' => $row->mobile ?? null,
            'title' => $row->title ?? null,
            'department' => $row->department ?? null,
            'accountid' => (int) ($row->accountid ?? 0),
            'accountname' => $row->accountname ?? null,
            'assigned_user_id' => (int) ($row->smownerid ?? 0),
            'assigned_user_name' => $row->user_name ?? null,
            'description' => $row->description ?? null,
            'createdtime' => $row->createdtime ?? null,
            'modifiedtime' => $row->modifiedtime ?? null,
            'deleted' => (int) ($row->deleted ?? 0),
            // ... remaining fields ...
        ]);
    }

    /**
     * Prepare data for INSERT into Vtiger (for creation)
     */
    public static function toPersistence(array $contactData, int $createdByUserId): array
    {
        return [
            // For vtiger_crmentity
            'smownerid' => $createdByUserId,
            'smcreatorid' => $createdByUserId,
            'setype' => 'Contacts',
            'description' => $contactData['description'] ?? null,
            'createdtime' => now()->format('Y-m-d H:i:s'),
            'modifiedtime' => now()->format('Y-m-d H:i:s'),
            'deleted' => 0,
            // For vtiger_contactdetails
            'accountid' => isset($contactData['accountid']) ? (int) $contactData['accountid'] : null,
            'firstname' => isset($contactData['firstname']) ? trim($contactData['firstname']) : null,
            'lastname' => isset($contactData['lastname']) ? trim($contactData['lastname']) : null,
            'email' => isset($contactData['email']) && $contactData['email'] ? strtolower(trim($contactData['email'])) : null,
            'phone' => $contactData['phone'] ?? null,
            'mobile' => $contactData['mobile'] ?? null,
            'title' => $contactData['title'] ?? null,
            'department' => $contactData['department'] ?? null,
            // ... remaining fields ...
        ];
    }

    /**
     * Prepare data for UPDATE, grouped by table
     * 
     * @param array $contactData Raw input data from request/use case
     * @param int|null $assignedUserId Optional: new assigned user ID (for smownerid)
     * @return array{contactdetails: array, crmentity: array} Fields grouped by target table
     */
    public static function toPersistenceUpdate(array $contactData, ?int $assignedUserId = null): array
    {
        // Filter and prepare fields for vtiger_contactdetails
        $contactDetailsData = array_filter(
            array_intersect_key($contactData, array_flip(self::CONTACT_DETAILS_FIELDS)),
            fn($v) => $v !== null && $v !== ''
        );

        // Filter and prepare fields for vtiger_crmentity
        $crmentityData = array_filter(
            array_intersect_key($contactData, array_flip(self::CRMENTITY_FIELDS)),
            fn($v) => $v !== null && $v !== ''
        );

        // Always update modifiedtime
        $crmentityData['modifiedtime'] = now()->format('Y-m-d H:i:s');

        // Handle assigned_user_id → smownerid in crmentity
        if ($assignedUserId !== null) {
            $crmentityData['smownerid'] = $assignedUserId;
        }

        return [
            'contactdetails' => $contactDetailsData,
            'crmentity' => $crmentityData,
        ];
    }

    /**
     * Helper: Get list of valid fields for validation
     */
    public static function getValidFields(): array
    {
        return array_merge(self::CONTACT_DETAILS_FIELDS, self::CRMENTITY_FIELDS);
    }

    public static function toContactDetailsArray(array $contactData, int $contactId): array
    {
        $baseData = array_filter(
            array_intersect_key($contactData, array_flip(self::CONTACT_DETAILS_FIELDS)),
            fn($v) => $v !== null && $v !== ''
        );


        return array_merge([
            'contact_no' => 'CON' . str_pad($contactId, 5, '0', STR_PAD_LEFT),
        ], $baseData);
    }
}
