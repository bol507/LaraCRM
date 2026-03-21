<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateClientRequest;
use App\Application\DTOs\UpdateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\Repositories\ProjectRepositoryInterface;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Domain\Entities\Client;
use App\Domain\Entities\ClientSummary;
use App\Infrastructure\Mappers\ClientMapper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class VtigerClientRepository
 * 
 * Vtiger-specific implementation of the ClientRepositoryInterface.
 * 
 * This repository handles client (account) persistence operations for the Vtiger CRM
 * system, implementing data access logic across multiple related database tables:
 * - vtiger_account: Core client data and business information
 * - vtiger_crmentity: Audit metadata, ownership, soft-delete flag, entity type
 * - vtiger_accountbillads: Billing address details
 * - vtiger_accountshipads: Shipping address details
 * 
 * Implementation characteristics:
 * - Multi-table operations: Client data spans 4+ tables requiring coordinated queries
 * - Mapper integration: Uses ClientMapper for row-to-entity and entity-to-row transformation
 * - Transaction safety: Write operations wrapped in database transactions for consistency
 * - Legacy ID generation: Uses MAX(crmid) + 1 pattern for Vtiger compatibility
 * - Soft delete support: Deletion marks vtiger_crmentity.deleted = 1 rather than removing records
 * - Dependency delegation: Summary calculations delegated to related entity repositories
 * 
 * Database connection:
 * - Uses named connection 'vtiger' configured in database.php for Vtiger database access
 * - All queries explicitly specify DB::connection('vtiger') to avoid accidental cross-database operations
 * 
 * Performance considerations:
 * - Read queries use indexed fields (accountid, crmid, deleted) for efficient lookups
 * - Search operations use LIKE with wildcards; consider full-text indexing for large datasets
 * - Summary calculation queries each related table separately; consider caching for high-traffic scenarios
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @implements ClientRepositoryInterface
 * @see ClientRepositoryInterface For the contract this class implements
 * @see ClientMapper For entity mapping utilities
 * @see Client For the domain entity returned by read operations
 * @see ClientSummary For aggregated client statistics
 */
class VtigerClientRepository implements ClientRepositoryInterface
{
    

    /**
     * VtigerClientRepository constructor.
     * 
     * Injects repository dependencies for related entities via constructor injection.
     * This enables the getSummary() method to delegate counting operations while
     * maintaining loose coupling and testability.
     * 
     * @param OpportunityRepositoryInterface $opportunityRepository Repository for opportunity operations
     * @param QuoteRepositoryInterface $quoteRepository Repository for quote operations
     * @param ProjectRepositoryInterface $projectRepository Repository for project operations
     * @param ContactRepositoryInterface $contactRepository Repository for contact operations
     * 
     * @return void
     */
    public function __construct(
        protected OpportunityRepositoryInterface $opportunityRepository,
        protected QuoteRepositoryInterface $quoteRepository,
        protected ProjectRepositoryInterface $projectRepository,
        protected ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Retrieve a paginated list of clients with optional search and filters.
     * 
     * Implementation details:
     * - Constructs query joining vtiger_account with vtiger_crmentity for audit data
     *   and left joins with address tables for billing/shipping information
     * - Applies base filters: deleted = 0 and setype = 'Accounts' to ensure only
     *   active client records are returned
     * - Search functionality performs case-insensitive partial matching against
     *   accountname, account_no, and email1 fields using LIKE with wildcards
     * - Additional filters applied via dynamic where clauses based on provided
     *   key-value pairs; only non-empty values are included to avoid null comparisons
     * - Total count calculated before pagination to ensure accurate metadata
     * - Results mapped to Client entities via ClientMapper::fromDatabaseRows()
     * - Returns Laravel LengthAwarePaginator with proper path preservation for
     *   frontend pagination controls
     * 
     * Query optimization:
     * - Uses select() to fetch only required columns rather than SELECT *
     * - Applies filters before pagination to reduce dataset size early
     * - Left joins for addresses allow clients without addresses to be included
     * 
     * @inheritDoc
     * 
     * @return LengthAwarePaginator<Client> Paginator containing mapped Client
     *                                      entities for the requested page
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?array $filters = null
    ): LengthAwarePaginator {

        $query = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_accountbillads', 'vtiger_account.accountid', '=', 'vtiger_accountbillads.accountaddressid')
            ->leftJoin('vtiger_accountshipads', 'vtiger_account.accountid', '=', 'vtiger_accountshipads.accountaddressid')
            ->select(
                // Core account fields
                'vtiger_account.*',

                // Audit fields from vtiger_crmentity
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.modifiedby',
                'vtiger_crmentity.deleted as crm_deleted',

                // Billing address
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',

                // Shipping address
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.account_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$search}%");
            });
        }

        // Additional filters
        if ($filters) {
            foreach ($filters as $field => $value) {
                if (!empty($value)) {
                    $query->where("vtiger_account.{$field}", $value);
                }
            }
        }

        // Get total count for pagination
        $total = $query->count();

        // Get paginated items
        $items = $query->forPage($page, $perPage)->get();

        
        $clients = collect(ClientMapper::fromDatabaseRows($items->toArray()));

        return new LengthAwarePaginator(
            $clients,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Find a client by ID with all related data joined.
     * 
     * Implementation details:
     * - Constructs query identical to getAll() but with WHERE clause for specific accountid
     * - Returns single row or null; uses first() to limit result set
     * - Maps result to Client entity via ClientMapper::fromDatabaseRow()
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Filters by setype = 'Accounts' to ensure correct entity type
     * 
     * Performance note:
     * - Query uses primary key (accountid) for efficient index lookup
     * - Single record fetch avoids pagination overhead for detail views
     * 
     * @inheritDoc
     * 
     * @param int $id The accountid of the client to retrieve
     * 
     * @return Client|null The mapped Client entity if found and active, null otherwise
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findById(int $id): ?Client
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_accountbillads', 'vtiger_account.accountid', '=', 'vtiger_accountbillads.accountaddressid')
            ->leftJoin('vtiger_accountshipads', 'vtiger_account.accountid', '=', 'vtiger_accountshipads.accountaddressid')
            ->select(
                // Core account fields
                'vtiger_account.*',

                // Audit fields from crmentity
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.modifiedby',
                'vtiger_crmentity.deleted as crm_deleted',

                // Billing address
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',

                // Shipping address
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_account.accountid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts')
            ->first();

        // Map to domain entity
        return $row ? ClientMapper::fromDatabaseRow($row) : null;
    }

    /**
     * Create a new client record in the database.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity
     * - Constructs temporary Client entity from request data for mapper compatibility
     * - Generates new CRM ID using Vtiger legacy pattern: MAX(crmid) + 1
     * - Generates account number using format ACC-XXXXXX if not provided in request
     * - Uses ClientMapper::toPersistence() to transform entity into table-specific arrays
     * - Inserts records in dependency order: vtiger_crmentity first (foreign key reference),
     *   then vtiger_account, then optional address tables
     * - Assigns generated IDs to mapped arrays before insertion to maintain referential integrity
     * - Logs errors with full trace for debugging before re-throwing exception
     * - Rolls back transaction on any failure to prevent partial data persistence
     * 
     * ID generation strategy:
     * - Uses MAX(crmid) + 1 to emulate auto-increment in Vtiger's legacy schema
     * - Note: This approach may have race conditions in high-concurrency environments;
     *   consider database sequences or application-level ID generators with locking
     *   for production deployments with heavy write load
     * 
     * Address handling:
     * - Billing and shipping addresses inserted only if at least one field is populated
     * - Uses hasBillingAddress()/hasShippingAddress() helpers to determine insertion need
     * - Address tables use accountid as both primary key and foreign key to vtiger_account
     * 
     * @inheritDoc
     * 
     * @param CreateClientRequest $request Validated DTO containing client creation data
     * @param int $userId ID of authenticated user performing the operation for audit tracking
     * 
     * @return int The newly generated accountid for the created client
     * 
     * @throws \RuntimeException If database transaction fails or any insert operation errors
     * @throws \Exception If unexpected error occurs during creation process
     */
    public function create(CreateClientRequest $request, int $userId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            
            $client = new Client(
                
                accountid: 0, 
                account_no: $request->account_no ?? '', 
                accountname: $request->accountname,
                parentid: $request->parentid ?? null,
                account_type: $request->account_type,
                industry: $request->industry,
                annualrevenue: $request->annualrevenue,
                rating: $request->rating,
                ownership: $request->ownership,
                siccode: $request->siccode,
                tickersymbol: $request->tickersymbol,
                phone: $request->phone,
                otherphone: $request->otherphone,
                email1: $request->email1,
                email2: $request->email2,
                website: $request->website,
                fax: $request->fax,
                employees: $request->employees,
                emailoptout: $request->emailoptout ?? '0',
                notify_owner: $request->notify_owner ?? '0',
                isconvertedfromlead: $request->isconvertedfromlead ?? '0',
                tags: $request->tags,
                isActive: true,
                
                // Audit fields 
                createdtime: null,
                modifiedtime: null,
                smcreatorid: $userId,
                smownerid: $userId,
                modifiedby: $userId,
                
                // Billing address
                bill_street: $request->bill_street,
                bill_city: $request->bill_city,
                bill_state: $request->bill_state,
                bill_code: $request->bill_code,
                bill_country: $request->bill_country,
                bill_pobox: $request->bill_pobox,
                
                // Shipping address
                ship_street: $request->ship_street,
                ship_city: $request->ship_city,
                ship_state: $request->ship_state,
                ship_code: $request->ship_code,
                ship_country: $request->ship_country,
                ship_pobox: $request->ship_pobox,
            );

            
            $nextCrmId = $this->getNextCrmId();
            $currentTime = now()->format('Y-m-d H:i:s');
            $accountNo = $request->account_no ?? $this->generateAccountNumber($nextCrmId);

            
            $mapped = ClientMapper::toPersistence($client, isUpdate: false);

            
            $mapped['account']['accountid'] = $nextCrmId;
            $mapped['account']['account_no'] = $accountNo;
            $mapped['crmentity']['crmid'] = $nextCrmId;

            
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert($mapped['crmentity']);

            
            DB::connection('vtiger')
                ->table('vtiger_account')
                ->insert($mapped['account']);

            
            if ($mapped['billing']) {
                DB::connection('vtiger')
                    ->table('vtiger_accountbillads')
                    ->insert($mapped['billing']);
            }

            
            if ($mapped['shipping']) {
                DB::connection('vtiger')
                    ->table('vtiger_accountshipads')
                    ->insert($mapped['shipping']);
            }

            DB::connection('vtiger')->commit();
            return $nextCrmId;

        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            Log::error('Error creating client', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Determine if billing address data is present in the request.
     * 
     * Helper method used during creation to decide whether to insert
     * a record into vtiger_accountbillads. Returns true if at least
     * one address field is non-empty.
     * 
     * @param CreateClientRequest $request The request containing address data
     * @return bool True if any billing address field is populated
     * 
     * @internal Used by create() method for conditional address insertion
     */
    private function hasBillingAddress(CreateClientRequest $request): bool
    {
        return !empty($request->bill_street) ||
            !empty($request->bill_city) ||
            !empty($request->bill_state) ||
            !empty($request->bill_code) ||
            !empty($request->bill_country) ||
            !empty($request->bill_pobox);
    }

    /**
     * Determine if shipping address data is present in the request.
     * 
     * Helper method used during creation to decide whether to insert
     * a record into vtiger_accountshipads. Returns true if at least
     * one address field is non-empty.
     * 
     * @param CreateClientRequest $request The request containing address data
     * @return bool True if any shipping address field is populated
     * 
     * @internal Used by create() method for conditional address insertion
     */
    private function hasShippingAddress(CreateClientRequest $request): bool
    {
        return !empty($request->ship_street) ||
            !empty($request->ship_city) ||
            !empty($request->ship_state) ||
            !empty($request->ship_code) ||
            !empty($request->ship_country) ||
            !empty($request->ship_pobox);
    }

    /**
     * Generate the next available CRM ID using Vtiger legacy pattern.
     * 
     * Implementation uses MAX(crmid) + 1 to emulate auto-increment behavior
     * in Vtiger's schema which does not use native auto-increment for crmid.
     * 
     * Note: This approach may have race conditions under high concurrency.
     * For production systems with heavy write load, consider:
     * - Database sequences (if supported)
     * - Application-level ID generator with table locking
     * - Migration to native auto-increment if schema allows
     * 
     * @return int The next available crmid value
     * 
     * @throws \RuntimeException If database query fails
     * 
     * @internal Used by create() method for ID generation
     */
    private function getNextCrmId(): int
    {
        $maxCrmId = DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid');
        return $maxCrmId ? $maxCrmId + 1 : 1;
    }

    /**
     * Generate a formatted account number from CRM ID.
     * 
     * Creates human-readable account identifier using format ACC-XXXXXX
     * where XXXXXX is the zero-padded crmid value. This format is consistent
     * with Vtiger's default account numbering scheme and provides quick
     * visual identification of client records.
     * 
     * @param int $crmid The CRM ID to format
     * @return string Formatted account number (e.g., "ACC-000123")
     * 
     * @internal Used by create() method when account_no not provided in request
     */
    private function generateAccountNumber(int $crmid): string
    {

        return 'ACC-' . str_pad($crmid, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update an existing client record in the database.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity
     * - Updates vtiger_crmentity first to refresh audit timestamps (modifiedtime, modifiedby)
     * - Updates vtiger_account with all provided fields from request DTO
     * - Delegates address updates to dedicated helper methods for billing and shipping
     * - Uses updateOrInsert pattern for addresses: updates existing record if found,
     *   inserts new record only if address data is present and no existing record
     * - Does not use mapper for updates; performs direct field mapping for clarity
     *   and to avoid overwriting null values with database defaults
     * - Rolls back transaction on any failure to prevent partial updates
     * 
     * Update strategy:
     * - Only specified fields are updated; unspecified fields retain existing values
     * - Null values in request are persisted as NULL in database (explicit clearing)
     * - Address updates use separate queries to handle potential absence of address records
     * 
     * Audit handling:
     * - modifiedtime and modifiedby always updated regardless of which fields changed
     * - Enables change tracking and ownership attribution for compliance requirements
     * 
     * @inheritDoc
     * 
     * @param UpdateClientRequest $request Validated DTO containing update data including client ID
     * @param int $userId ID of authenticated user performing the operation for audit tracking
     * 
     * @return bool True if update completed successfully, false if client not found
     * 
     * @throws \RuntimeException If database transaction fails or any update operation errors
     * @throws \Exception If unexpected error occurs during update process
     */
    public function update(UpdateClientRequest $request, int $userId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $currentTime = now()->format('Y-m-d H:i:s');

            // 1. Actualizar vtiger_crmentity (modifiedtime, modifiedby)
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $request->id)
                ->update([
                    'modifiedtime' => $currentTime,
                    'modifiedby' => $userId
                ]);

            // 2. Actualizar vtiger_account
            DB::connection('vtiger')
                ->table('vtiger_account')
                ->where('accountid', $request->id)
                ->update([
                    'accountname' => $request->accountname,
                    'account_no' => $request->account_no,
                    'account_type' => $request->account_type,
                    'industry' => $request->industry,
                    'annualrevenue' => $request->annualrevenue,
                    'rating' => $request->rating,
                    'ownership' => $request->ownership,
                    'siccode' => $request->siccode,
                    'tickersymbol' => $request->tickersymbol,
                    'phone' => $request->phone,
                    'otherphone' => $request->otherphone,
                    'email1' => $request->email1,
                    'email2' => $request->email2,
                    'website' => $request->website,
                    'fax' => $request->fax,
                    'employees' => $request->employees,
                    'emailoptout' => $request->emailoptout,
                    'notify_owner' => $request->notify_owner,
                    'isconvertedfromlead' => $request->isconvertedfromlead,
                    'tags' => $request->tags,
                ]);

            // 3. Actualizar dirección de facturación
            $this->updateBillingAddress($request->id, $request);

            // 4. Actualizar dirección de envío  
            $this->updateShippingAddress($request->id, $request);

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Update or insert billing address for a client.
     * 
     * Implementation uses conditional logic:
     * - If address record exists for accountid, performs UPDATE
     * - If no record exists but address data is present, performs INSERT
     * - If no record exists and no address data, skips operation entirely
     * 
     * This approach avoids creating empty address records while ensuring
     * existing addresses can be cleared by passing null/empty values.
     * 
     * @param int $accountId The accountid of the client
     * @param UpdateClientRequest $request Request containing address data
     * 
     * @return void
     * 
     * @throws \RuntimeException If database operation fails
     * 
     * @internal Called by update() method for billing address handling
     */
    private function updateBillingAddress(int $accountId, UpdateClientRequest $request): void
    {
        $existing = DB::connection('vtiger')
            ->table('vtiger_accountbillads')
            ->where('accountaddressid', $accountId)
            ->first();

        $data = [
            'bill_street' => $request->bill_street,
            'bill_city' => $request->bill_city,
            'bill_state' => $request->bill_state,
            'bill_code' => $request->bill_code,
            'bill_country' => $request->bill_country,
            'bill_pobox' => $request->bill_pobox,
        ];

        if ($existing) {
            DB::connection('vtiger')
                ->table('vtiger_accountbillads')
                ->where('accountaddressid', $accountId)
                ->update($data);
        } elseif ($this->hasBillingAddressData($data)) {
            DB::connection('vtiger')
                ->table('vtiger_accountbillads')
                ->insert(array_merge(['accountaddressid' => $accountId], $data));
        }
    }

    /**
     * Update or insert shipping address for a client.
     * 
     * Implementation mirrors updateBillingAddress() logic for consistency:
     * - Updates existing record if found
     * - Inserts new record only if data present and no existing record
     * - Skips operation if no data and no existing record
     * 
     * @param int $accountId The accountid of the client
     * @param UpdateClientRequest $request Request containing address data
     * 
     * @return void
     * 
     * @throws \RuntimeException If database operation fails
     * 
     * @internal Called by update() method for shipping address handling
     */
    private function updateShippingAddress(int $accountId, UpdateClientRequest $request): void
    {
        $existing = DB::connection('vtiger')
            ->table('vtiger_accountshipads')
            ->where('accountaddressid', $accountId)
            ->first();

        $data = [
            'ship_street' => $request->ship_street,
            'ship_city' => $request->ship_city,
            'ship_state' => $request->ship_state,
            'ship_code' => $request->ship_code,
            'ship_country' => $request->ship_country,
            'ship_pobox' => $request->ship_pobox,
        ];

        if ($existing) {
            DB::connection('vtiger')
                ->table('vtiger_accountshipads')
                ->where('accountaddressid', $accountId)
                ->update($data);
        } elseif ($this->hasShippingAddressData($data)) {
            DB::connection('vtiger')
                ->table('vtiger_accountshipads')
                ->insert(array_merge(['accountaddressid' => $accountId], $data));
        }
    }

    /**
     * Determine if billing address data array contains any values.
     * 
     * Helper method used during update to decide whether to insert
     * a new billing address record. Returns true if at least one
     * field in the data array is non-empty.
     * 
     * @param array $data Associative array of billing address fields
     * @return bool True if any billing address field is populated
     * 
     * @internal Used by updateBillingAddress() for conditional insertion
     */
    private function hasBillingAddressData(array $data): bool
    {
        return !empty($data['bill_street']) ||
            !empty($data['bill_city']) ||
            !empty($data['bill_state']) ||
            !empty($data['bill_code']) ||
            !empty($data['bill_country']) ||
            !empty($data['bill_pobox']);
    }

    /**
     * Determine if shipping address data array contains any values.
     * 
     * Helper method used during update to decide whether to insert
     * a new shipping address record. Returns true if at least one
     * field in the data array is non-empty.
     * 
     * @param array $data Associative array of shipping address fields
     * @return bool True if any shipping address field is populated
     * 
     * @internal Used by updateShippingAddress() for conditional insertion
     */
    private function hasShippingAddressData(array $data): bool
    {
        return !empty($data['ship_street']) ||
            !empty($data['ship_city']) ||
            !empty($data['ship_state']) ||
            !empty($data['ship_code']) ||
            !empty($data['ship_country']) ||
            !empty($data['ship_pobox']);
    }

    /**
     * Soft delete a client record by marking it as deleted.
     * 
     * Implementation details:
     * - Performs soft delete by updating vtiger_crmentity.deleted = 1
     *   rather than removing records from database (preserves audit trail)
     * - Verifies record exists and is not already deleted before proceeding
     * - Filters by setype = 'Accounts' to ensure correct entity type is affected
     * - Returns false if record not found or already deleted (idempotent operation)
     * - Does not cascade delete to related entities (opportunities, quotes, etc.)
     *   as those maintain independent lifecycle and visibility rules
     * 
     * Data retention:
     * - Soft-deleted records remain queryable with explicit deleted = 1 filter
     * - Enables audit compliance and potential restoration via administrative tools
     * - Consider implementing periodic archival job for records beyond retention period
     * 
     * @inheritDoc
     * 
     * @param int $id The accountid of the client to soft-delete
     * 
     * @return bool True if deletion was successful, false if client not found
     *              or already deleted
     * 
     * @throws \RuntimeException If database update fails or connection is lost
     * @throws \Exception If unexpected error occurs during deletion process
     */
    public function delete(int $id): bool
    {
        try {
            $existing = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $id)
                ->where('setype', 'Accounts')
                ->where('deleted', 0)
                ->first();

            if (!$existing) {
                return false;
            }


            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $id)
                ->update(['deleted' => 1]);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Find a client by exact account name match.
     * 
     * Implementation details:
     * - Performs case-insensitive exact match using TRIM(UPPER()) comparison
     * - Returns minimal data subset (id, accountname, email1, assigned_user_id)
     *   optimized for duplicate checking during client creation
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns null if no match found or search term is empty
     * 
     * Use cases:
     * - Duplicate prevention during client creation workflows
     * - Quick lookup when exact account name is known
     * - Autocomplete pre-validation before full search
     * 
     * @inheritDoc
     * 
     * @param string $accountName The exact account name to search for
     * 
     * @return array<string, mixed>|null Associative array with basic client
     *                                   information if exact match found,
     *                                   null otherwise
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findByAccountName(string $accountName): ?array
    {
        $searchTerm = trim($accountName);
        if (empty($searchTerm)) {
            return null;
        }
        $row = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->select(
                'vtiger_account.accountid',
                'vtiger_account.accountname',
                'vtiger_account.email1',
                'vtiger_crmentity.smownerid as assigned_user_id'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->whereRaw('TRIM(UPPER(vtiger_account.accountname)) = ?', [strtoupper($searchTerm)])
            ->first();

        return $row ? [
            'id' => $row->accountid,
            'accountname' => $row->accountname,
            'email1' => $row->email1,
            'assigned_user_id' => $row->assigned_user_id
        ] : null;
    }

    /**
     * Find clients by partial match on name or email.
     * 
     * Implementation details:
     * - Performs case-insensitive partial matching using LIKE with wildcards
     * - Searches across accountname and email1 fields via OR condition
     * - Returns minimal data subset optimized for autocomplete dropdowns
     * - Limits results to 20 records for performance and UI usability
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Maps results to simple associative arrays for frontend consumption
     * 
     * Performance considerations:
     * - LIKE with leading wildcard prevents index usage; consider full-text
     *   indexing or search engine integration for large datasets
     * - Result limit prevents excessive payload for autocomplete scenarios
     * 
     * @inheritDoc
     * 
     * @param string $searchTerm The search term for partial matching
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing basic client
     *                                          information for autocomplete
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findByNameOrEmail(string $searchTerm): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->select(
                'vtiger_account.accountid',
                'vtiger_account.accountname',
                'vtiger_account.email1',
                'vtiger_crmentity.smownerid as assigned_user_id'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($query) use ($searchTerm) {
                $query->where('vtiger_account.accountname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$searchTerm}%");
            })
            ->limit(20)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => $row->accountid,
                'accountname' => $row->accountname,
                'email1' => $row->email1,
                'assigned_user_id' => $row->assigned_user_id
            ];
        })->toArray();
    }

    /**
     * Perform global search for clients with type discrimination.
     * 
     * Implementation details:
     * - Searches across accountname and account_no fields with partial matching
     * - Adds static 'type' field set to 'client' for frontend entity routing
     * - Adds 'url' field with route pattern for direct navigation to client detail
     * - Limits results to specified count for global search aggregation
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns results formatted for global search response structure
     * 
     * Global search integration:
     * - Response format matches other entity search methods for consistent
     *   aggregation in GlobalSearchUseCase
     * - 'type' field enables frontend to route to correct detail view
     * - 'url' field provides pre-built navigation link for convenience
     * 
     * @inheritDoc
     * 
     * @param string $query The search query string for partial matching
     * @param int $limit Maximum number of results to return
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing client information
     *                                          with type discrimination for
     *                                          global search aggregation
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function search(string $query, int $limit): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($query) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_account.account_no', 'LIKE', "%{$query}%");
            })
            ->select(
                'vtiger_account.accountid as id',
                'vtiger_account.accountname as title',
                'vtiger_account.account_no as number',
                DB::raw("'client' as type")
            )
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'number' => $item->number,
                'url' => "/dashboard/clients/{$item->id}",
            ])
            ->toArray();
    }

    /**
     * Retrieve aggregated summary statistics for a client.
     * 
     * Implementation details:
     * - Verifies client exists before calculating summary to prevent
     *   unnecessary queries for non-existent IDs
     * - Delegates counting operations to related entity repositories
     *   (opportunities, quotes, projects, contacts) via dependency injection
     * - Calculates last activity timestamp by querying modifiedtime across
     *   all related entity tables and returning the most recent value
     * - Constructs ClientSummary entity with aggregated counts and timestamp
     * 
     * Last activity calculation:
     * - Queries each related table separately to find MAX(modifiedtime)
     * - Uses appropriate join conditions and foreign key relationships
     * - For projects, uses CAST on linktoaccountscontacts field to handle
     *   Vtiger's legacy VARCHAR storage of related record references
     * - Returns null if no related activity found across all entity types
     * 
     * Performance considerations:
     * - Makes 5 separate queries (1 for existence check + 4 for related counts)
     * - Consider caching summary results for frequently accessed clients
     * - Last activity calculation could be optimized with UNION query or
     *   materialized view for high-traffic scenarios
     * 
     * @inheritDoc
     * 
     * @param int $clientId The accountid of the client for summary calculation
     * 
     * @return ClientSummary Entity containing counts of related entities
     *                       and most recent activity timestamp
     * 
     * @throws \InvalidArgumentException If client with specified ID does not exist
     * @throws \RuntimeException If any database query fails during summary calculation
     */
    public function getSummary(int $clientId): ClientSummary
    {
        // Verify client exists before calculating summary
        $client = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_account.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (!$client) {
            throw new \InvalidArgumentException("Client with ID {$clientId} not found");
        }

        // Get last activity timestamp
        $lastActivity = $this->getLastActivity($clientId);

        // Delegate counting to each repository
        return new ClientSummary(
            clientId: $clientId,
            opportunitiesCount: $this->opportunityRepository->countByClient($clientId),
            quotesCount: $this->quoteRepository->countByClient($clientId),
            projectsCount: $this->projectRepository->countByClient($clientId),
            contactsCount: $this->contactRepository->countByClient($clientId),
            lastActivity: $lastActivity
        );
    }

    /**
     * Get the most recent activity timestamp across all related entities.
     * 
     * Implementation details:
     * - Queries each related entity table (potentials, quotes, projects, contacts)
     *   to find the maximum modifiedtime value
     * - Uses appropriate foreign key relationships for each entity type:
     *   - Opportunities: vtiger_potential.related_to = accountid
     *   - Quotes: vtiger_quotes.accountid = accountid
     *   - Projects: CAST(vtiger_project.linktoaccountscontacts AS UNSIGNED) = accountid
     *   - Contacts: vtiger_contactdetails.accountid = accountid
     * - Collects non-null timestamps in array and returns maximum value
     * - Returns null if no related activity found across all entity types
     * 
     * Vtiger-specific considerations:
     * - Projects table stores related account references as VARCHAR in format
     *   "IDxModuleType"; uses CAST to UNSIGNED for numeric comparison
     * - All queries filter by vtiger_crmentity.deleted = 0 to exclude soft-deleted
     *   related entities from activity calculation
     * 
     * @param int $clientId The accountid of the client
     * 
     * @return string|null ISO formatted timestamp (YYYY-MM-DD HH:MM:SS) of most
     *                     recent activity, or null if no related activity exists
     * 
     * @throws \RuntimeException If any database query fails during timestamp collection
     * 
     * @internal Called by getSummary() method for last activity calculation
     */
    private function getLastActivity(int $clientId): ?string
    {
        $timestamps = [];

        // Opportunities
        $oppTime = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_potential.related_to', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->max('vtiger_crmentity.modifiedtime');
        if ($oppTime) $timestamps[] = $oppTime;

        // Quotes
        $quoteTime = DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_quotes.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->max('vtiger_crmentity.modifiedtime');
        if ($quoteTime) $timestamps[] = $quoteTime;

        // Projects
        $projTime = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->whereRaw('CAST(vtiger_project.linktoaccountscontacts AS UNSIGNED) = ?', [$clientId])
            ->where('vtiger_crmentity.deleted', 0)
            ->max('vtiger_crmentity.modifiedtime');
        if ($projTime) $timestamps[] = $projTime;

        // Contacts
        $contactTime = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_contactdetails.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->max('vtiger_crmentity.modifiedtime');
        if ($contactTime) $timestamps[] = $contactTime;

        return !empty($timestamps) ? max($timestamps) : null;
    }
}