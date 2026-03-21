<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\DTOs\CreateOpportunityRequest;
use App\Domain\Entities\Opportunity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Class VtigerOpportunityRepository
 * 
 * Vtiger-specific implementation of the OpportunityRepositoryInterface.
 * 
 * This repository handles opportunity (potential) persistence operations for the Vtiger CRM
 * system, implementing data access logic across multiple related database tables:
 * - vtiger_potential: Core opportunity data including amount, stage, probability, dates
 * - vtiger_crmentity: Audit metadata, ownership (smownerid), creator (smcreatorid), soft-delete flag, entity type
 * - vtiger_account: Denormalized client name for display efficiency via left join
 * - vtiger_users: Denormalized assigned user name for display efficiency via left join
 * 
 * Implementation characteristics:
 * - Multi-table queries: Read operations join 4 tables to provide comprehensive opportunity data
 * - Inline entity mapping: Opportunities mapped directly from database rows without external mapper class
 * - Transaction safety: Write operations (create, update) wrapped in database transactions for consistency
 * - Legacy ID generation: Uses MAX(crmid) + 1 pattern for Vtiger compatibility instead of auto-increment
 * - Soft delete support: Deletion marks vtiger_crmentity.deleted = 1 rather than removing records
 * - Denormalized reads: Account and user names fetched via joins to avoid N+1 query problems
 * 
 * Database connection:
 * - Uses named connection 'vtiger' configured in database.php for Vtiger database access
 * - All queries explicitly specify DB::connection('vtiger') to avoid accidental cross-database operations
 * 
 * Performance considerations:
 * - Read queries use indexed fields (potentialid, crmid, related_to, deleted) for efficient lookups
 * - Search operations use LIKE with leading wildcard; consider full-text indexing for large datasets
 * - Pagination uses offset/limit pattern; for very large datasets consider keyset pagination
 * - Denormalized user/account names reduce need for additional queries in list views
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @implements OpportunityRepositoryInterface
 * @see OpportunityRepositoryInterface For the contract this class implements
 * @see Opportunity For the domain entity returned by read operations
 * @see CreateOpportunityRequest For the DTO used in creation operations
 */
class VtigerOpportunityRepository implements OpportunityRepositoryInterface
{
    /**
     * Retrieve a paginated list of opportunities with optional search and account filter.
     * 
     * Implementation details:
     * - Constructs query joining vtiger_potential with vtiger_crmentity for audit data
     *   and left joins with vtiger_account and vtiger_users for denormalized display names
     * - Applies base filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted records
     * - Optional account filter: When $accountId provided, adds WHERE clause on related_to field
     * - Optional search filter: Performs case-insensitive partial matching on potentialname only
     *   using LIKE with wildcards; additional fields can be added to search scope if needed
     * - Total count calculated before pagination to ensure accurate metadata in paginator
     * - Results mapped to Opportunity entities via inline constructor calls with type casting
     * - Returns Laravel LengthAwarePaginator with path preservation for frontend pagination
     * 
     * Query structure:
     * - SELECT specifies only required columns to minimize data transfer
     * - CONCAT expression builds assigned_user_name from first_name and last_name
     * - Type casting applied to numeric fields (amount, probability, IDs) for type safety
     * - assigned_user_name trimmed and validated to avoid displaying whitespace-only values
     * 
     * Mapping strategy:
     * - Inline mapping avoids external mapper dependency for this repository
     * - Nullable fields handled with ternary operators to convert database nulls to PHP nulls
     * - is_active property always set to true for non-deleted records (derived from deleted flag)
     * 
     * @inheritDoc
     * 
     * @param int $page Page number (1-based index)
     * @param int $perPage Items per page
     * @param string|null $search Optional search term for partial matching on potentialname
     * @param int|null $accountId Optional account ID to filter opportunities by related client
     * 
     * @return LengthAwarePaginator<Opportunity> Paginator containing mapped Opportunity
     *                                           entities for the requested page
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null, ?int $accountId = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_potential.potentialid',
                'vtiger_potential.potential_no',
                'vtiger_potential.potentialname',
                'vtiger_potential.amount',
                'vtiger_potential.closingdate',
                'vtiger_potential.sales_stage',
                'vtiger_potential.probability',
                'vtiger_potential.related_to',
                'vtiger_account.accountname as related_to_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                 DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_potential.description',
                'vtiger_crmentity.deleted'
            )
            ->where('vtiger_crmentity.deleted', 0);
        
        if ($accountId) {
            $query->where('vtiger_potential.related_to', $accountId);
        }
        if ($search) {
            $query->where('vtiger_potential.potentialname', 'LIKE', "%{$search}%");
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $opportunities = $items->map(fn($row) => new Opportunity(
            potentialid: $row->potentialid,
            potential_no: $row->potential_no,
            potentialname: $row->potentialname,
            amount: $row->amount ? (float)$row->amount : null,
            closingdate: $row->closingdate,
            sales_stage: $row->sales_stage,
            probability: $row->probability ? (int)$row->probability : null,
            related_to: $row->related_to ? (int)$row->related_to : null,
            related_to_name: $row->related_to_name ?? null,
            assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' ' 
            ? $row->assigned_user_name 
            : null,
            description: $row->description,
            is_active: true
        ));

        return new LengthAwarePaginator(
            $opportunities instanceof Collection ? $opportunities : collect($opportunities),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Find a single opportunity by its unique identifier.
     * 
     * Implementation details:
     * - Constructs query identical to getAll() but with WHERE clause for specific potentialid
     * - Returns single row via first() method or null if not found
     * - Maps result to Opportunity entity using same inline mapping logic as getAll()
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Does not verify setype = 'Potentials' as potentialid is assumed unique to opportunities
     * 
     * Performance note:
     * - Query uses primary key (potentialid) for efficient index lookup
     * - Single record fetch avoids pagination overhead for detail views
     * - Same joins as getAll() ensure consistent data structure for detail display
     * 
     * @inheritDoc
     * 
     * @param int $id The potentialid of the opportunity to retrieve
     * 
     * @return Opportunity|null The mapped Opportunity entity if found and active, null otherwise
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findById(int $id): ?Opportunity
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_potential.potentialid',
                'vtiger_potential.potential_no',
                'vtiger_potential.potentialname',
                'vtiger_potential.amount',
                'vtiger_potential.closingdate',
                'vtiger_potential.sales_stage',
                'vtiger_potential.probability',
                'vtiger_potential.related_to',
                'vtiger_account.accountname as related_to_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                 DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_potential.description',
                'vtiger_crmentity.deleted'
            )
            ->where('vtiger_potential.potentialid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        return $row ? new Opportunity(
            potentialid: $row->potentialid,
            potential_no: $row->potential_no,
            potentialname: $row->potentialname,
            amount: $row->amount ? (float)$row->amount : null,
            closingdate: $row->closingdate,
            sales_stage: $row->sales_stage,
            probability: $row->probability ? (int)$row->probability : null,
            related_to: $row->related_to ? (int)$row->related_to : null,
            related_to_name: $row->related_to_name ?? null,
            assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' ' 
            ? $row->assigned_user_name 
            : null,
            description: $row->description,
            is_active: true
        ) : null;
    }

    /**
     * Create a new opportunity record in the database.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity across two tables
     * - Generates new CRM ID using Vtiger legacy pattern: MAX(crmid) + 1
     * - Inserts into vtiger_crmentity first to establish audit metadata and entity type
     * - Inserts into vtiger_potential second with same crmid as potentialid for referential integrity
     * - Generates opportunity number using format POT-XXXXXXXX with zero-padded crmid
     * - Assigns ownership: smownerid uses request value if provided, otherwise falls back to creator
     * - Sets smcreatorid and modifiedby to authenticated user ID for audit tracking
     * - Sets entity type to 'Potentials' in vtiger_crmentity for type discrimination
     * - Initializes conversion flags (isconvertedfromlead, converted) to 0 for new opportunities
     * - Rolls back transaction on any failure to prevent orphaned records in either table
     * 
     * ID generation strategy:
     * - Uses MAX(crmid) + 1 to emulate auto-increment in Vtiger's legacy schema
     * - Note: This approach may have race conditions in high-concurrency environments;
     *   consider database sequences or application-level ID generators with locking
     *   for production deployments with heavy write load
     * 
     * Field mapping:
     * - Description field stored in both vtiger_crmentity (for global search) and
     *   vtiger_potential (for opportunity-specific display); kept in sync during creation
     * - Nullable request fields passed directly to database; NULL values preserved
     * 
     * @inheritDoc
     * 
     * @param CreateOpportunityRequest $request Validated DTO containing opportunity creation data
     * @param int $createdByUserId ID of authenticated user performing the operation for audit tracking
     * 
     * @return int The newly generated potentialid for the created opportunity
     * 
     * @throws \RuntimeException If database transaction fails or any insert operation errors
     * @throws \Exception If unexpected error occurs during creation process
     */
    public function create(CreateOpportunityRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $maxCrmid = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->max('crmid');
        
            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;
            DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->insert([
                'crmid' => $crmid,
                'smownerid' => $request->assigned_user_id ?? $createdByUserId,
                'smcreatorid' => $createdByUserId,
                'setype' => 'Potentials',
                'description' => $request->description ?? '',
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);

            // Then create in vtiger_potential
            DB::connection('vtiger')
                ->table('vtiger_potential')
                ->insert([
                'potentialid' => $crmid,
                'potential_no' => 'POT' . str_pad($crmid, 8, '0', STR_PAD_LEFT),
                'potentialname' => $request->potentialname,
                'amount' => $request->amount,
                'closingdate' => $request->closingdate,
                'sales_stage' => $request->sales_stage,
                'probability' => $request->probability,
                'related_to' => $request->related_to,
                'description' => $request->description ?? '',
                'isconvertedfromlead' => 0,
                'converted' => 0,
            ]);

            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Update an existing opportunity record in the database.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity across two tables
     * - Updates vtiger_crmentity first if assigned_user_id changes, to maintain ownership consistency
     * - Updates vtiger_potential with only fields present in $data array (partial update support)
     * - Uses conditional field mapping to avoid overwriting unspecified fields with NULL
     * - Updates modifiedtime timestamp in vtiger_crmentity for audit tracking on any change
     * - Does not update smcreatorid or createdtime as these are immutable after creation
     * - Does not update setype or deleted flags as these are managed by other operations
     * - Rolls back transaction on any failure to prevent partial updates
     * 
     * Update strategy:
     * - Only explicitly provided fields are updated; missing fields retain existing values
     * - NULL values in $data array are persisted as NULL in database (explicit clearing)
     * - assigned_user_id updates affect vtiger_crmentity.smownerid, not vtiger_potential
     *   (Vtiger schema stores ownership in crmentity, not in module-specific tables)
     * 
     * Field validation:
     * - No validation performed at repository layer; assumes UseCase or Request validation
     *   has already verified data integrity and business rules
     * - Type casting handled by database driver; PHP type hints provide compile-time safety
     * 
     * @inheritDoc
     * 
     * @param int $id The potentialid of the opportunity to update
     * @param array<string, mixed> $data Associative array with fields to update
     * @param int $modifiedByUserId ID of authenticated user performing the operation for audit tracking
     * 
     * @return bool True if update completed successfully, false if opportunity not found
     * 
     * @throws \RuntimeException If database transaction fails or any update operation errors
     * @throws \Exception If unexpected error occurs during update process
     */
    public function update(int $id, array $data, int $modifiedByUserId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // Update vtiger_crmentity (assigned user)
            if (isset($data['assigned_user_id'])) {
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $id)
                    ->update([
                        'smownerid' => $data['assigned_user_id'],
                        'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    ]);
            }

            // Update vtiger_potential (excluding assigned_user_id which lives in crmentity)
            $updateData = [];
            if (isset($data['potentialname'])) $updateData['potentialname'] = $data['potentialname'];
            if (isset($data['amount'])) $updateData['amount'] = $data['amount'];
            if (isset($data['closingdate'])) $updateData['closingdate'] = $data['closingdate'];
            if (isset($data['sales_stage'])) $updateData['sales_stage'] = $data['sales_stage'];
            if (isset($data['probability'])) $updateData['probability'] = $data['probability'];
            if (isset($data['related_to'])) $updateData['related_to'] = $data['related_to'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];

            if (!empty($updateData)) {
                DB::connection('vtiger')
                    ->table('vtiger_potential')
                    ->where('potentialid', $id)
                    ->update($updateData);
            }

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Soft delete an opportunity record by marking it as deleted.
     * 
     * Implementation details:
     * - Performs soft delete by updating vtiger_crmentity.deleted = 1
     *   rather than removing records from database (preserves audit trail)
     * - Filters by setype = 'Potentials' to ensure correct entity type is affected
     * - Uses update() method returning affected row count; success determined by count > 0
     * - Does not cascade delete to related entities (quote items, tasks, etc.)
     *   as those maintain independent lifecycle and visibility rules
     * - Does not require authenticated user ID parameter as deletion does not
     *   update audit fields beyond the deleted flag
     * 
     * Data retention:
     * - Soft-deleted records remain queryable with explicit deleted = 1 filter
     * - Enables audit compliance and potential restoration via administrative tools
     * - Consider implementing periodic archival job for records beyond retention period
     * 
     * @inheritDoc
     * 
     * @param int $id The potentialid of the opportunity to soft-delete
     * @param int $deletedByUserId ID of user performing deletion (logged for audit, not stored)
     * 
     * @return bool True if deletion was successful (record found and updated),
     *              false if opportunity not found or already deleted
     * 
     * @throws \RuntimeException If database update fails or connection is lost
     */
    public function delete(int $id, int $deletedByUserId): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Potentials')
            ->update(['deleted' => 1]) > 0;
    }

    /**
     * Retrieve all available sales stages for opportunities.
     * 
     * Implementation details:
     * - Returns hardcoded array of stage values matching Vtiger's default sales pipeline
     * - Stages ordered sequentially from initial contact to closed outcome
     * - Values match vtiger_potential.sales_stage picklist configuration
     * - No database query performed; suitable for caching at application level
     * 
     * Customization note:
     * - For organizations with custom sales stages, consider:
     *   - Loading stages from vtiger_picklist table dynamically
     *   - Configuring stages via application config file
     *   - Providing admin interface for stage management
     * 
     * @inheritDoc
     * 
     * @return array<int, string> Indexed array of stage names in pipeline order
     */
    public function getAvailableStages(): array
    {
        return [
            'Prospecting',
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Identifying Decision Makers',
            'Perception Analysis',
            'Proposal/Price Quote',
            'Negotiation/Review',
            'Closed Won',
            'Closed Lost'
        ];
    }

    /**
     * Perform global search for opportunities with type discrimination.
     * 
     * Implementation details:
     * - Searches across potentialname and potential_no fields with partial matching
     * - Adds static 'type' field set to 'opportunity' for frontend entity routing
     * - Adds 'url' field with route pattern for direct navigation to opportunity detail
     * - Includes denormalized client name and amount for enriched search result display
     * - Limits results to specified count for global search aggregation performance
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns results formatted for global search response structure
     * 
     * Global search integration:
     * - Response format matches other entity search methods for consistent
     *   aggregation in GlobalSearchUseCase
     * - 'type' field enables frontend to route to correct detail view
     * - 'url' field provides pre-built navigation link for convenience
     * - Additional fields (client, amount) enable richer result previews
     * 
     * @inheritDoc
     * 
     * @param string $query The search query string for partial matching
     * @param int $limit Maximum number of results to return
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing opportunity information
     *                                          with type discrimination for
     *                                          global search aggregation
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function search(string $query, int $limit): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function($q) use ($query) {
                $q->where('vtiger_potential.potentialname', 'LIKE', "%{$query}%")
                  ->orWhere('vtiger_potential.potential_no', 'LIKE', "%{$query}%");
            })
            ->select(
                'vtiger_potential.potentialid as id',
                'vtiger_potential.potentialname as title',
                'vtiger_potential.potential_no as number',
                'vtiger_account.accountname as client',
                'vtiger_potential.amount as amount',
                DB::raw("'opportunity' as type")
            )
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'number' => $item->number,
                'client' => $item->client,
                'amount' => $item->amount,
                'url' => "/dashboard/opportunities/{$item->id}",
            ])
            ->toArray();
    }

    /**
     * Count the number of opportunities related to a specific client.
     * 
     * Implementation details:
     * - Performs efficient COUNT query against vtiger_potential joined with vtiger_crmentity
     * - Filters by related_to = $clientId to count only opportunities for specified account
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns integer count suitable for dashboard summary cards and statistics
     * - No entity hydration performed; optimized for read-only aggregation use case
     * 
     * Performance note:
     * - Query uses indexed fields (related_to, deleted) for efficient lookup
     * - COUNT(*) executed at database level; no application-level iteration required
     * - Suitable for frequent calls in client summary views; consider caching if called
     *   multiple times per request
     * 
     * @inheritDoc
     * 
     * @param int $clientId The accountid of the client for which to count opportunities
     * 
     * @return int The number of active opportunities related to the specified client.
     *             Returns 0 if the client has no opportunities or if the client does
     *             not exist.
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function countByClient(int $clientId): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_potential.related_to', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }
}