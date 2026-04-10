<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Client;
use App\Infrastructure\Repositories\VtigerClientRepository;
use InvalidArgumentException;

/**
 * Client Mapper
 *
 * Converts between Vtiger database representations and domain Client entities.
 * Supports both raw database rows and domain entity transformations.
 *
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 *
 * @since 1.0.0
 * @see Client
 * @see VtigerClientRepository
 */
class ClientMapper
{
    /**
     * Map raw database row to domain Client entity
     *
     * This method handles the transformation of a joined database query result
     * (from vtiger_account, vtiger_crmentity, vtiger_accountbillads, vtiger_accountshipads)
     * into a properly structured Client domain entity.
     *
     * @param  object  $row  Raw database row from joined query
     * @return Client Domain entity with all properties mapped
     *
     * @throws InvalidArgumentException If required fields are missing
     *
     * @example
     * $row = DB::connection('vtiger')
     *     ->table('vtiger_account')
     *     ->join('vtiger_crmentity', ...)
     *     ->leftJoin('vtiger_accountbillads', ...)
     *     ->leftJoin('vtiger_accountshipads', ...)
     *     ->select(...)
     *     ->first();
     * $client = ClientMapper::fromDatabaseRow($row);
     */
    public static function fromDatabaseRow(object $row): Client
    {
        // Validate required fields
        self::validateRequiredFields($row);

        return new Client(
            // Core account fields from vtiger_account
            accountid: (int) $row->accountid,
            account_no: $row->account_no,
            accountname: $row->accountname,
            parentid: $row->parentid ? (int) $row->parentid : null,
            account_type: $row->account_type,
            industry: $row->industry,
            annualrevenue: $row->annualrevenue ? (float) $row->annualrevenue : null,
            rating: $row->rating,
            ownership: $row->ownership,
            siccode: $row->siccode,
            tickersymbol: $row->tickersymbol,
            phone: $row->phone,
            otherphone: $row->otherphone,
            email1: $row->email1,
            email2: $row->email2,
            website: $row->website,
            fax: $row->fax,
            employees: $row->employees ? (int) $row->employees : null,
            emailoptout: $row->emailoptout,
            notify_owner: $row->notify_owner,
            isconvertedfromlead: $row->isconvertedfromlead,
            tags: $row->tags,
            isActive: ($row->crm_deleted ?? 0) === 0,

            // ✅ Audit fields from vtiger_crmentity
            createdtime: $row->createdtime ?? null,
            modifiedtime: $row->modifiedtime ?? null,
            smcreatorid: $row->smcreatorid ? (int) $row->smcreatorid : null,
            smownerid: $row->smownerid ? (int) $row->smownerid : null,
            modifiedby: $row->modifiedby ? (int) $row->modifiedby : null,

            // Billing address from vtiger_accountbillads
            bill_street: $row->bill_street ?? null,
            bill_city: $row->bill_city ?? null,
            bill_state: $row->bill_state ?? null,
            bill_code: $row->bill_code ?? null,
            bill_country: $row->bill_country ?? null,
            bill_pobox: $row->bill_pobox ?? null,

            // Shipping address from vtiger_accountshipads
            ship_street: $row->ship_street ?? null,
            ship_city: $row->ship_city ?? null,
            ship_state: $row->ship_state ?? null,
            ship_code: $row->ship_code ?? null,
            ship_country: $row->ship_country ?? null,
            ship_pobox: $row->ship_pobox ?? null,

            // Description from vtiger_crmentity
            description: $row->description ?? null,
        );
    }

    /**
     * Map domain Client entity to persistence arrays
     *
     * This method prepares client data for database insertion or update.
     * It separates fields that go into vtiger_account from those
     * that go into vtiger_crmentity and address tables.
     *
     * @param  Client  $client  Domain entity to map
     * @param  bool  $isUpdate  Whether this is an update operation (affects modifiedtime)
     * @return array{account: array, crmentity: array, billing: array|null, shipping: array|null}
     *                                                                                            Associative arrays for database operations
     *
     * @example
     * $mapped = ClientMapper::toPersistence($client);
     * DB::connection('vtiger')->table('vtiger_account')->insert($mapped['account']);
     * DB::connection('vtiger')->table('vtiger_crmentity')->insert($mapped['crmentity']);
     */
    public static function toPersistence(Client $client, bool $isUpdate = false): array
    {
        $now = date('Y-m-d H:i:s');

        return [
            'account' => array_filter([
                // Core account fields
                'accountid' => $client->accountid,
                'account_no' => $client->account_no,
                'accountname' => $client->accountname,
                'parentid' => $client->parentid,
                'account_type' => $client->account_type,
                'industry' => $client->industry,
                'annualrevenue' => $client->annualrevenue,
                'rating' => $client->rating,
                'ownership' => $client->ownership,
                'siccode' => $client->siccode,
                'tickersymbol' => $client->tickersymbol,
                'phone' => $client->phone,
                'otherphone' => $client->otherphone,
                'email1' => $client->email1,
                'email2' => $client->email2,
                'website' => $client->website,
                'fax' => $client->fax,
                'employees' => $client->employees,
                'emailoptout' => $client->emailoptout,
                'notify_owner' => $client->notify_owner,
                'isconvertedfromlead' => $client->isconvertedfromlead,
                'tags' => $client->tags,
            ], fn ($v) => $v !== null),

            'crmentity' => array_filter([
                'crmid' => $client->accountid,
                'smcreatorid' => $isUpdate ? null : ($client->smcreatorid ?? 1),
                'smownerid' => $client->smownerid ?? 1,
                'modifiedby' => $client->smownerid ?? 1,
                'setype' => 'Accounts',
                'description' => $client->description,
                'createdtime' => $isUpdate ? null : $now,
                'modifiedtime' => $now,
                'deleted' => 0,
                'version' => 0,
                'presence' => 1,
            ], fn ($v) => $v !== null),

            'billing' => $client->bill_street || $client->bill_city ? array_filter([
                'accountaddressid' => $client->accountid,
                'bill_street' => $client->bill_street,
                'bill_city' => $client->bill_city,
                'bill_state' => $client->bill_state,
                'bill_code' => $client->bill_code,
                'bill_country' => $client->bill_country,
                'bill_pobox' => $client->bill_pobox,
            ], fn ($v) => $v !== null) : null,

            'shipping' => $client->ship_street || $client->ship_city ? array_filter([
                'accountaddressid' => $client->accountid,
                'ship_street' => $client->ship_street,
                'ship_city' => $client->ship_city,
                'ship_state' => $client->ship_state,
                'ship_code' => $client->ship_code,
                'ship_country' => $client->ship_country,
                'ship_pobox' => $client->ship_pobox,
            ], fn ($v) => $v !== null) : null,
        ];
    }

    /**
     * Map collection of database rows to Client entities
     *
     * @param  array<object>  $rows  Array of raw database rows
     * @return array<Client> Array of mapped Client entities
     */
    public static function fromDatabaseRows(array $rows): array
    {
        return array_map(fn ($row) => self::fromDatabaseRow($row), $rows);
    }

    /**
     * Validate that all required fields are present in the database row
     *
     * @param  object  $row  Database row to validate
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    private static function validateRequiredFields(object $row): void
    {
        $requiredFields = [
            'accountid',
            'account_no',
            'accountname',
            'createdtime',
            'modifiedtime',
        ];

        foreach ($requiredFields as $field) {
            if (! isset($row->$field)) {
                throw new InvalidArgumentException(
                    "Required field '{$field}' is missing from database row"
                );
            }
        }
    }
}
