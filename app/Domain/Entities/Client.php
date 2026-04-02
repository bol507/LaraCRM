<?php

namespace App\Domain\Entities;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Infrastructure\Mappers\ClientMapper;

/**
 * Class Client
 *
 * Domain entity representing an account (client) in Vtiger CRM.
 *
 * This entity encapsulates all data related to a business client or account
 * in the CRM system. It maps to the vtiger_account table joined with
 * vtiger_crmentity for audit information and address tables (vtiger_accountbillads,
 * vtiger_accountshipads) for billing and shipping addresses.
 *
 * The entity follows the immutability pattern using readonly properties,
 * ensuring that once created, client data cannot be modified. This promotes
 * safer data handling, predictable behavior, and easier reasoning about
 * data flow throughout the application.
 *
 * Key characteristics:
 * - Immutable: All properties are readonly after instantiation
 * - Nullable fields: Optional database fields are marked as nullable with default null
 * - Boolean flags: Vtiger boolean fields stored as "0"/"1" strings for compatibility
 * - Address separation: Billing and shipping addresses stored as separate properties
 * - Audit tracking: Creation/modification timestamps and user IDs from crmentity
 * - Soft delete support: isActive flag derived from vtiger_crmentity.deleted
 *
 * Database mapping:
 * - Primary table: vtiger_account (accountid is primary key)
 * - Audit join: vtiger_crmentity ON accountid = crmid
 * - Billing address: vtiger_accountbillads ON accountid = accountaddressid
 * - Shipping address: vtiger_accountshipads ON accountid = accountaddressid
 *
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 *
 * @version 1.0.0
 *
 * @see ClientRepositoryInterface For persistence operations
 * @see ClientMapper For database row to entity mapping
 * @see ClientSummary For aggregated client statistics
 */
class Client
{
    /**
     * Client constructor.
     *
     * Creates an immutable Client entity instance with all account data
     * from the Vtiger CRM database. All properties are marked as readonly
     * to ensure immutability after instantiation, promoting safer data
     * handling and preventing accidental modifications during request
     * processing.
     *
     * The entity is designed to be created by the repository layer after
     * fetching and joining data from vtiger_account, vtiger_crmentity,
     * and address tables. Application code should not instantiate this
     * class directly but should use repository methods instead.
     *
     * @param  int  $accountid  The unique identifier of the client account.
     * @param  string  $account_no  The account number as displayed in the CRM system.
     * @param  string  $accountname  The name of the client account.
     * @param  int|null  $parentid  The ID of the parent account, if applicable.
     * @param  string|null  $account_type  The type classification of the account.
     * @param  string|null  $industry  The industry sector of the client organization.
     * @param  float|null  $annualrevenue  The annual revenue of the client organization.
     * @param  string|null  $rating  The rating or status classification of the account.
     * @param  string|null  $ownership  The ownership type of the client organization.
     * @param  string|null  $siccode  The Standard Industrial Classification (SIC) code.
     * @param  string|null  $tickersymbol  The stock ticker symbol for publicly traded clients.
     * @param  string|null  $phone  The primary phone number of the client organization.
     * @param  string|null  $otherphone  An alternative or secondary phone number.
     * @param  string|null  $email1  The primary email address of the client organization.
     * @param  string|null  $email2  An alternative or secondary email address.
     * @param  string|null  $website  The website URL of the client organization.
     * @param  string|null  $fax  The fax number of the client organization.
     * @param  int|null  $employees  The number of employees at the client organization.
     * @param  string  $emailoptout  Flag for email marketing opt-out ("0" or "1").
     * @param  string  $notify_owner  Flag for notifying owner of changes ("0" or "1").
     * @param  string  $isconvertedfromlead  Flag for lead conversion origin ("0" or "1").
     * @param  string|null  $tags  Tags or keywords associated with the account.
     * @param  bool  $isActive  Flag indicating whether the account is active (not deleted).
     * @param  string|null  $createdtime  Timestamp when the account was created.
     * @param  string|null  $modifiedtime  Timestamp when the account was last modified.
     * @param  int|null  $smcreatorid  User ID of the user who created the account.
     * @param  int|null  $smownerid  User ID of the user who owns the account.
     * @param  int|null  $modifiedby  User ID of the user who last modified the account.
     * @param  string|null  $bill_street  Street address for billing.
     * @param  string|null  $bill_city  City for billing.
     * @param  string|null  $bill_state  State/province for billing.
     * @param  string|null  $bill_code  Postal/ZIP code for billing.
     * @param  string|null  $bill_country  Country for billing.
     * @param  string|null  $bill_pobox  P.O. Box for billing.
     * @param  string|null  $ship_street  Street address for shipping.
     * @param  string|null  $ship_city  City for shipping.
     * @param  string|null  $ship_state  State/province for shipping.
     * @param  string|null  $ship_code  Postal/ZIP code for shipping.
     * @param  string|null  $ship_country  Country for shipping.
     * @param  string|null  $ship_pobox  P.O. Box for shipping.
     * @return void
     */
    public function __construct(
        // Required fields (never null in database)
        public readonly int $accountid,
        public readonly string $account_no,
        public readonly string $accountname,

        // Optional fields: nullable + default value = null
        public readonly ?int $parentid = null,
        public readonly ?string $account_type = null,
        public readonly ?string $industry = null,
        public readonly ?float $annualrevenue = null,
        public readonly ?string $rating = null,
        public readonly ?string $ownership = null,
        public readonly ?string $siccode = null,
        public readonly ?string $tickersymbol = null,
        public readonly ?string $phone = null,
        public readonly ?string $otherphone = null,
        public readonly ?string $email1 = null,
        public readonly ?string $email2 = null,
        public readonly ?string $website = null,
        public readonly ?string $fax = null,
        public readonly ?int $employees = null,

        // Vtiger boolean flags stored as "0" or "1" strings (required)
        public readonly string $emailoptout = '0',
        public readonly string $notify_owner = '0',
        public readonly string $isconvertedfromlead = '0',

        public readonly ?string $tags = null,
        public readonly bool $isActive = true,

        // Audit fields from vtiger_crmentity (optional, consistent with readonly)
        public readonly ?string $createdtime = null,
        public readonly ?string $modifiedtime = null,
        public readonly ?int $smcreatorid = null,
        public readonly ?int $smownerid = null,
        public readonly ?int $modifiedby = null,

        // Billing address fields (all optional)
        public readonly ?string $bill_street = null,
        public readonly ?string $bill_city = null,
        public readonly ?string $bill_state = null,
        public readonly ?string $bill_code = null,
        public readonly ?string $bill_country = null,
        public readonly ?string $bill_pobox = null,

        // Shipping address fields (all optional)
        public readonly ?string $ship_street = null,
        public readonly ?string $ship_city = null,
        public readonly ?string $ship_state = null,
        public readonly ?string $ship_code = null,
        public readonly ?string $ship_country = null,
        public readonly ?string $ship_pobox = null,

        // Description from vtiger_crmentity
        public readonly ?string $description = null,
    ) {}
}
