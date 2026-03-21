<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Contact;

/**
 * Mapper between Vtiger database and Contact entity
 */
class ContactMapper
{
    /**
     * Map database row to Contact entity
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
            
            // Denormalized fields from vtiger_account (via JOIN)
            'accountname' => $row->accountname ?? null,
            
            // Fields from vtiger_crmentity
            'assigned_user_id' => (int) ($row->smownerid ?? 0),
            'assigned_user_name' => $row->user_name ?? null,
            'description' => $row->description ?? null,
            'createdtime' => $row->createdtime ?? null,
            'modifiedtime' => $row->modifiedtime ?? null,
            'deleted' => (int) ($row->deleted ?? 0),
            
            // Additional fields from vtiger_contactdetails
            'mailingstreet' => $row->mailingstreet ?? null,
            'mailingcity' => $row->mailingcity ?? null,
            'mailingstate' => $row->mailingstate ?? null,
            'mailingcountry' => $row->mailingcountry ?? null,
            'mailingzip' => $row->mailingzip ?? null,
            'otherphone' => $row->otherphone ?? null,
            'fax' => $row->fax ?? null,
            'secondaryemail' => $row->secondaryemail ?? null,
            'assistant' => $row->assistant ?? null,
            'birthdate' => $row->birthdate ?? null,
            'reports_to_id' => isset($row->reports_to_id) ? (int) $row->reports_to_id : null,
            'leadsource' => $row->leadsource ?? null,
            'contact_status' => $row->contact_status ?? 'Active',
        ]);
    }

    /**
     * Prepare data for insertion into Vtiger
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
            'accountid' => (int) $contactData['accountid'],
            'firstname' => trim($contactData['firstname']),
            'lastname' => trim($contactData['lastname']),
            'email' => strtolower(trim($contactData['email'])),
            'phone' => $contactData['phone'] ?? null,
            'mobile' => $contactData['mobile'] ?? null,
            'title' => $contactData['title'] ?? null,
            'department' => $contactData['department'] ?? null,
            'mailingstreet' => $contactData['mailingstreet'] ?? null,
            'mailingcity' => $contactData['mailingcity'] ?? null,
            'mailingstate' => $contactData['mailingstate'] ?? null,
            'mailingcountry' => $contactData['mailingcountry'] ?? null,
            'mailingzip' => $contactData['mailingzip'] ?? null,
            'otherphone' => $contactData['otherphone'] ?? null,
            'fax' => $contactData['fax'] ?? null,
            'secondaryemail' => $contactData['secondaryemail'] ?? null,
            'assistant' => $contactData['assistant'] ?? null,
            'birthdate' => $contactData['birthdate'] ?? null,
            'reports_to_id' => $contactData['reports_to_id'] ?? null,
            'leadsource' => $contactData['leadsource'] ?? null,
            'contact_status' => $contactData['contact_status'] ?? 'Active',
        ];
    }
}