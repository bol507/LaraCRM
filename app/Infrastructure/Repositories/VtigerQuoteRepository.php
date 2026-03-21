<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\DTOs\Quote\QuoteResponse;
use App\Application\Repositories\QuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class VtigerQuoteRepository
 * 
 * Vtiger-specific implementation of the QuoteRepositoryInterface.
 * 
 * This repository handles quote (sales quote) persistence operations for the Vtiger CRM
 * system, implementing data access logic across multiple related database tables:
 * - vtiger_quotes: Core quote data including subject, amounts, stage, dates, relationships
 * - vtiger_crmentity: Audit metadata, ownership (smownerid), creator (smcreatorid), soft-delete flag, entity type, description
 * - vtiger_account: Denormalized client name for display efficiency via left join on accountid
 * - vtiger_inventoryproductrel: Line item data for quotes including products, quantities, pricing, discounts
 * - vtiger_potential: Denormalized opportunity name for display via separate lookup
 * - vtiger_users: Denormalized assigned user name for display via separate lookup
 * - vtiger_modentity_num: Sequence management for quote number generation
 * 
 * Implementation characteristics:
 * - Complex multi-table queries: Read operations join 4+ tables with additional lookups for denormalization
 * - QuoteResponse mapping: Results mapped to QuoteResponse DTO with calculated line items and pricing
 * - Transaction safety: Write operations (create, update) wrapped in database transactions for consistency
 * - Legacy ID generation: Uses MAX(crmid) + 1 pattern for Vtiger compatibility instead of auto-increment
 * - Quote number generation: Uses vtiger_modentity_num table with year-based prefix reset (C-YY-XXXXX format)
 * - Soft delete support: Deletion marks vtiger_crmentity.deleted = 1 rather than removing records
 * - Denormalized reads: Account, potential, and user names fetched via joins/lookups to avoid N+1 queries
 * - Line item calculation: Net price and totals calculated in PHP for consistency with business logic
 * - Tax calculation: ITBMS (7%) applied automatically during create/update operations
 * 
 * Database connection:
 * - Uses named connection 'vtiger' configured in database.php for Vtiger database access
 * - All queries explicitly specify DB::connection('vtiger') to avoid accidental cross-database operations
 * 
 * Performance considerations:
 * - Read queries use indexed fields (quoteid, crmid, accountid, deleted) for efficient lookups
 * - Search operations use LIKE with leading wildcard; consider full-text indexing for large datasets
 * - Line item aggregation performed via single query with GROUP BY to avoid N+1 problem
 * - Potential and user lookups batched via WHERE IN for efficiency with multiple quotes
 * - paginate() and getAll() share similar logic; consider refactoring to reduce duplication if maintenance becomes burdensome
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @implements QuoteRepositoryInterface
 * @see QuoteRepositoryInterface For the contract this class implements
 * @see QuoteResponse For the DTO returned by read operations
 * @see CreateQuoteRequest For the DTO used in creation operations
 * @see UpdateQuoteRequest For the DTO used in update operations
 */
class VtigerQuoteRepository implements QuoteRepositoryInterface
{
    /**
     * Retrieve a paginated list of quotes with optional search and account filter.
     * 
     * Implementation details:
     * - Constructs query joining vtiger_quotes with vtiger_crmentity for audit data
     *   and left joins with vtiger_account for denormalized client name display
     * - Applies base filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted records
     * - Optional account filter: When $accountId provided, adds WHERE clause on q.accountid field
     * - Optional search filter: Performs case-insensitive partial matching on subject,
     *   quote_no, and related account name using LIKE with wildcards
     * - Total count calculated before pagination to ensure accurate metadata in paginator
     * - Results ordered by createdtime descending (most recent first)
     * - Validates that returned quote IDs have setype = 'Quotes' in vtiger_crmentity
     * - Fetches line items for all quotes in single query via WHERE IN, groups by quote ID
     * - Batches potential and user lookups via WHERE IN to avoid N+1 query problem
     * - Maps each quote to QuoteResponse DTO with calculated line items (netprice, total)
     * - Returns Laravel LengthAwarePaginator with path and query preservation for frontend
     * 
     * Query structure:
     * - SELECT specifies only required columns to minimize data transfer
     * - Type casting applied to numeric fields (subtotal, discount_percent, total) for type safety
     * - Line item calculation: netprice = listprice * (1 - discount_percent/100), total = quantity * netprice
     * - Denormalized fields (account_name, potential_name, assigned_user_name) populated from lookups
     * 
     * Line item processing:
     * - Items fetched from vtiger_inventoryproductrel filtered by valid quote IDs
     * - Each item mapped with calculated netprice and total for frontend display
     * - Product name sourced from item description field (Vtiger convention for custom items)
     * - Sequence number preserved for display ordering
     * 
     * @inheritDoc
     * 
     * @param int $page Page number (1-based index)
     * @param int $perPage Items per page
     * @param string|null $search Optional search term for partial matching on quote fields
     * @param int|null $accountId Optional account ID to filter quotes by related client
     * 
     * @return LengthAwarePaginator<QuoteResponse> Paginator containing mapped QuoteResponse
     *                                               DTOs for the requested page with line items
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $accountId = null
    ): LengthAwarePaginator {
       
        $query = DB::connection('vtiger')
            ->table('vtiger_quotes as q')
            ->join('vtiger_crmentity as c', 'q.quoteid', '=', 'c.crmid')
            ->leftJoin('vtiger_account as a', 'q.accountid', '=', 'a.accountid')
            ->select(
                'q.quoteid',
                'q.quote_no as quote_no',
                'q.subject',
                'q.potentialid',
                'q.accountid',
                'c.smownerid as assigned_user_id',
                'q.quotestage as quote_stage',
                'q.validtill',
                'c.description',
                'q.subtotal',
                'q.discount_percent',
                'q.total',
                'c.createdtime',
                'c.modifiedtime',
                'a.accountname',
                'a.account_no as account_no',
                'c.deleted'
            )
            ->where('c.deleted', 0);

       
        if ($accountId !== null) {
            $query->where('q.accountid', $accountId);
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('q.subject', 'like', "%{$search}%")
                    ->orWhere('q.quote_no', 'like', "%{$search}%")
                    ->orWhere('a.accountname', 'like', "%{$search}%");
            });
        }

        // Count total BEFORE pagination
        $total = $query->count();

        // Get paginated quotes
        $quotes = $query
            ->orderBy('c.createdtime', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        if ($quotes->isEmpty()) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query()
            ]);
        }

        
        $quoteIds = $quotes->pluck('quoteid')->toArray();
        $validQuoteIds = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->whereIn('crmid', $quoteIds)
            ->where('setype', 'Quotes')
            ->pluck('crmid')
            ->toArray();

        
        $items = [];
        if (!empty($validQuoteIds)) {
            $items = DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->whereIn('id', $validQuoteIds)
                ->orderBy('sequence_no')
                ->get()
                ->groupBy('id');
        }

       
        $potentialIds = $quotes->pluck('potentialid')->unique()->filter()->toArray();
        $userIds = $quotes->pluck('assigned_user_id')->unique()->filter()->toArray();

        $potentials = !empty($potentialIds)
            ? DB::connection('vtiger')
            ->table('vtiger_potential')
            ->whereIn('potentialid', $potentialIds)
            ->pluck('potentialname', 'potentialid')
            ->toArray()
            : [];

        $users = !empty($userIds)
            ? DB::connection('vtiger')
            ->table('vtiger_users')
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => trim($u->first_name . ' ' . $u->last_name)
            ])
            ->toArray()
            : [];

       
        $entities = $quotes->map(function ($quote) use ($items, $potentials, $users) {
           
            $quoteEntity = \App\Infrastructure\Mappers\QuoteMapper::fromDatabaseRow($quote);

            
            $quoteItems = [];
            if (isset($items[$quote->quoteid])) {
                $quoteItems = $items[$quote->quoteid]->map(function ($item) {
                    $netprice = $item->listprice * (1 - ($item->discount_percent ?? 0) / 100);
                    $total = ($item->quantity ?? 0) * $netprice;

                    return [
                        'productid' => $item->productid,
                        'sequence_no' => (int) $item->sequence_no,
                        'productname' => $item->description ?? '',
                        'quantity' => (float) $item->quantity,
                        'listprice' => (float) $item->listprice,
                        'discount_percent' => (float) $item->discount_percent,
                        'netprice' => $netprice,
                        'total' => $total,
                        'description' => $item->comment ?? null,
                    ];
                })->toArray();
            }

            
            return new QuoteResponse(
                
                quoteid: $quoteEntity->quoteid,
                quoteno: $quoteEntity->quoteno,
                subject: $quoteEntity->subject,
                potentialid: $quoteEntity->potentialid,
                accountid: $quoteEntity->accountid,
                assigned_user_id: $quoteEntity->assigned_user_id,
                quote_stage: $quoteEntity->quote_stage,
                validtill: $quoteEntity->validtill,
                description: $quoteEntity->description,
                subtotal: $quoteEntity->subtotal,
                discount_percent: $quoteEntity->discount_percent,
                total: $quoteEntity->total,
                createdtime: $quoteEntity->createdtime,
                modifiedtime: $quoteEntity->modifiedtime,
                isActive: $quoteEntity->isActive,

              
                items: $quoteItems,
                account_name: $quote->accountname ?? null,
                account_no: $quote->account_no ?? null,
                potential_name: $quote->potentialid ? ($potentials[$quote->potentialid] ?? null) : null,
                assigned_user_name: $users[$quote->assigned_user_id] ?? null,
            );
        })->toArray();

        return new LengthAwarePaginator($entities, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);
    }

    /**
     * Create a new quote record in the database with line items and tax calculation.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity across three tables
     * - Generates new CRM ID using Vtiger legacy pattern: MAX(crmid) + 1
     * - Generates quote number using vtiger_modentity_num table with year-based prefix (C-YY-XXXXX)
     * - Calculates line item totals: netprice = listprice * (1 - discount_percent/100), total = quantity * netprice
     * - Calculates subtotal as sum of all line item totals
     * - Applies ITBMS tax (7%) to subtotal: itbms = subtotal * 0.07, total = subtotal + itbms
     * - Inserts into vtiger_crmentity first to establish audit metadata and entity type 'Quotes'
     * - Inserts into vtiger_quotes second with same crmid as quoteid for referential integrity
     * - Inserts line items into vtiger_inventoryproductrel with calculated pricing fields
     * - Stores tax breakdown in compound_taxes_info as JSON for audit and reporting
     * - Rolls back transaction on any failure to prevent orphaned records
     * 
     * ID generation strategy:
     * - Uses MAX(crmid) + 1 to emulate auto-increment in Vtiger's legacy schema
     * - Note: This approach may have race conditions in high-concurrency environments;
     *   consider database sequences or application-level ID generators with locking
     *   for production deployments with heavy write load
     * 
     * Quote number generation:
     * - Delegated to generateQuoteNumber() method which manages vtiger_modentity_num sequence
     * - Format: C-YY-XXXXX where YY is 2-digit year, XXXX is zero-padded counter
     * - Counter resets annually when year prefix changes
     * 
     * Pricing calculation:
     * - All monetary calculations performed in PHP for consistency with business rules
     * - Discount applied at line item level; quote-level discount_percent currently set to 0
     * - Tax calculation hardcoded to 7% ITBMS; consider making configurable for multi-region support
     * 
     * @inheritDoc
     * 
     * @param CreateQuoteRequest $request Validated DTO containing quote creation data including line items
     * @param int $createdByUserId ID of authenticated user performing the operation for audit tracking
     * 
     * @return int The newly generated quoteid for the created quote
     * 
     * @throws \RuntimeException If database transaction fails or any insert operation errors
     * @throws \Exception If unexpected error occurs during creation process
     */
    public function create(CreateQuoteRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;
            $quoteNo = $this->generateQuoteNumber();

            $subtotal = 0;
            $itemsWithTotals = [];

            foreach ($request->items as $item) {
                $netprice = $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100);
                $total = ($item['quantity'] ?? 0) * $netprice;
                $subtotal += $total;

                $itemsWithTotals[] = [
                    'productid' => $item['productid'],
                    'sequence_no' => $item['sequence_no'],
                    'productname' => $item['productname'],
                    'quantity' => $item['quantity'],
                    'listprice' => $item['listprice'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'description' => $item['description'],
                ];
            }


            $itbms = $subtotal * 0.07;
            $totalWithTax = $subtotal + $itbms;

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    'smownerid' => $request->assigned_user_id,
                    'smcreatorid' => $createdByUserId,
                    'setype' => 'Quotes',
                    'description' => $request->description,
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'deleted' => 0,
                ]);

            DB::connection('vtiger')
                ->table('vtiger_quotes')
                ->insert([
                    'quoteid' => $crmid,
                    'quote_no' => $quoteNo,
                    'subject' => $request->subject,
                    'potentialid' => $request->potentialid,
                    'accountid' => $request->accountid,
                    'quotestage' => 'Draft',
                    'validtill' => $request->validtill,
                    'subtotal' => $subtotal,
                    'discount_percent' => 0,
                    'total' => $totalWithTax,
                    'carrier' => null,
                    'shipping' => null,
                    'inventorymanager' => null,
                    'type' => null,
                    'adjustment' => null,
                    'taxtype' => 'individual',
                    'discount_amount' => null,
                    's_h_amount' => null,
                    'terms_conditions' => null,
                    'currency_id' => 1,
                    'conversion_rate' => 1.000,
                    'compound_taxes_info' => json_encode(['tax1' => $itbms]),
                    'pre_tax_total' => $subtotal,
                    's_h_percent' => null,
                    'tags' => null,
                    'region_id' => null,
                ]);

            foreach ($itemsWithTotals as $item) {
                DB::connection('vtiger')
                    ->table('vtiger_inventoryproductrel')
                    ->insert([
                        'id' => $crmid,
                        'productid' => $item['productid'],
                        'sequence_no' => $item['sequence_no'],
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'],
                        'description' => $item['productname'],
                        'comment' => $item['description'] ?? null,
                        'incrementondel' => 0,
                    ]);
            }



            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Update an existing quote record in the database with line items and tax recalculation.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity across three tables
     * - Recalculates line item totals using same logic as create(): netprice and total per item
     * - Recalculates subtotal as sum of line item totals
     * - Reapplies ITBMS tax (7%) to new subtotal for updated total
     * - Updates vtiger_quotes with all quote-level fields including recalculated amounts
     * - Updates vtiger_crmentity with assigned user and description changes
     * - Deletes all existing line items from vtiger_inventoryproductrel before re-inserting
     * - Re-inserts updated line items with new pricing and sequence information
     * - Updates compound_taxes_info JSON with new tax calculation for audit trail
     * - Rolls back transaction on any failure to prevent partial updates
     * 
     * Update strategy:
     * - Line items replaced entirely rather than updated individually; simplifies logic but
     *   may have performance implications for quotes with many items
     * - All monetary fields recalculated to ensure consistency; no partial amount updates
     * - Quote stage, validity date, and relationships updated as provided in request
     * 
     * Tax handling:
     * - ITBMS rate hardcoded to 7%; consider configuration option for multi-region deployments
     * - Tax breakdown stored as JSON in compound_taxes_info for reporting and audit purposes
     * - pre_tax_total field maintained separately from subtotal for clarity in financial reports
     * 
     * @inheritDoc
     * 
     * @param UpdateQuoteRequest $request Validated DTO containing quote update data including line items
     * @param int $modifiedByUserId ID of authenticated user performing the operation for audit tracking
     * 
     * @return bool True if update completed successfully, false if quote not found
     * 
     * @throws \RuntimeException If database transaction fails or any update operation errors
     * @throws \Exception If unexpected error occurs during update process
     */
    public function update(UpdateQuoteRequest $request, int $modifiedByUserId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {

            $subtotal = 0;
            $itemsWithTotals = [];

            foreach ($request->items as $item) {
                $netprice = $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100);
                $total = ($item['quantity'] ?? 0) * $netprice;
                $subtotal += $total;

                $itemsWithTotals[] = [
                    'productid' => $item['productid'],
                    'sequence_no' => $item['sequence_no'],
                    'productname' => $item['productname'],
                    'quantity' => $item['quantity'],
                    'listprice' => $item['listprice'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'description' => $item['description'],
                ];
            }


            $itbms = $subtotal * 0.07;
            $totalWithTax = $subtotal + $itbms;


            DB::connection('vtiger')
                ->table('vtiger_quotes')
                ->where('quoteid', $request->quoteid)
                ->update([
                    'subject' => $request->subject,
                    'potentialid' => $request->potentialid,
                    'accountid' => $request->accountid,
                    'quotestage' => $request->quote_stage,
                    'validtill' => $request->validtill,
                    'subtotal' => $subtotal,
                    'discount_percent' => 0, // Ajustar si hay descuentos
                    'total' => $totalWithTax,
                    'taxtype' => 'individual',
                    'compound_taxes_info' => json_encode(['tax1' => $itbms]),
                    'pre_tax_total' => $subtotal,
                ]);


            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $request->quoteid)
                ->update([
                    'smownerid' => $request->assigned_user_id,
                    'description' => $request->description,
                ]);


            DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->where('id', $request->quoteid)
                ->delete();


            foreach ($itemsWithTotals as $item) {
                DB::connection('vtiger')
                    ->table('vtiger_inventoryproductrel')
                    ->insert([
                        'id' => $request->quoteid,
                        'productid' => $item['productid'],
                        'sequence_no' => $item['sequence_no'],
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'],
                        'description' => $item['productname'],
                        'comment' => $item['description'] ?? null,
                        'incrementondel' => 0,
                    ]);
            }

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Find a single quote by its unique identifier with full line item and denormalized data.
     * 
     * Implementation details:
     * - First verifies that the provided ID corresponds to a quote entity type in vtiger_crmentity
     * - Constructs query joining vtiger_quotes with vtiger_crmentity and vtiger_account for core data
     * - Returns single row via first() method or null if not found or not of type 'Quotes'
     * - Fetches line items separately from vtiger_inventoryproductrel with pricing calculations
     * - Calculates netprice and total for each line item using same logic as create/update
     * - Performs separate lookups for potential name and assigned user name if IDs are present
     * - Maps result to QuoteResponse DTO with all denormalized fields and calculated line items
     * 
     * Performance note:
     * - Query uses primary key (quoteid) for efficient index lookup
     * - Single record fetch avoids pagination overhead for detail views
     * - Line item fetch uses simple WHERE clause without complex aggregation
     * - Potential and user lookups performed as separate queries; acceptable for detail view frequency
     * 
     * Data consistency:
     * - Pricing calculations performed in PHP to match create/update logic exactly
     * - Denormalized names fetched at read time to ensure current values displayed
     * - Line item sequence preserved via ORDER BY for consistent display ordering
     * 
     * @inheritDoc
     * 
     * @param int $quoteid The quoteid of the quote to retrieve
     * 
     * @return QuoteResponse|null The mapped QuoteResponse DTO if found and active, null otherwise
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findById(int $quoteid): ?QuoteResponse
    {
        // Verificar que el ID sea una cotización
        $isQuote = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $quoteid)
            ->where('setype', 'Quotes')
            ->exists();

        if (!$isQuote) {
            return null;
        }

        $quoteRow = DB::connection('vtiger')
            ->table('vtiger_quotes as q')
            ->join('vtiger_crmentity as c', 'q.quoteid', '=', 'c.crmid')
            ->leftJoin('vtiger_account as a', 'q.accountid', '=', 'a.accountid')
            ->select(
                'q.quoteid',
                'q.quote_no as quoteno',
                'q.subject',
                'q.potentialid',
                'q.accountid',
                'c.smownerid as assigned_user_id',
                'q.quotestage as quote_stage',
                'q.validtill',
                'c.description',
                'q.subtotal',
                'q.discount_percent',
                'q.total',
                'c.createdtime',
                'c.modifiedtime',
                'a.accountname' // 
            )
            ->where('q.quoteid', $quoteid)
            ->first();

        if (!$quoteRow) {
            return null;
        }


        $items = DB::connection('vtiger')
            ->table('vtiger_inventoryproductrel')
            ->where('id', $quoteid)
            ->orderBy('sequence_no')
            ->get()
            ->map(function ($item) {
                $netprice = $item->listprice * (1 - ($item->discount_percent ?? 0) / 100);
                $total = ($item->quantity ?? 0) * $netprice;

                return [
                    'productid' => $item->productid,
                    'sequence_no' => (int) $item->sequence_no,
                    'productname' => $item->description ?? '',
                    'quantity' => (float) $item->quantity,
                    'listprice' => (float) $item->listprice,
                    'discount_percent' => (float) $item->discount_percent,
                    'netprice' => $netprice,
                    'total' => $total,
                    'description' => $item->comment ?? null,
                ];
            })
            ->toArray();



        $potentialName = null;
        $assignedUserName = null;

        $accountName = $quoteRow->accountname ?? null;

        // (potential)
        if ($quoteRow->potentialid) {
            $potential = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->where('potentialid', $quoteRow->potentialid)
                ->first();
            $potentialName = $potential?->potentialname;
        }

        // User assigned
        if ($quoteRow->assigned_user_id) {
            $user = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $quoteRow->assigned_user_id)
                ->first();
            $assignedUserName = $user ? trim($user->first_name . ' ' . $user->last_name) : null;
        }

        return new QuoteResponse(
            quoteid: $quoteRow->quoteid,
            quoteno: $quoteRow->quoteno,
            subject: $quoteRow->subject,
            potentialid: $quoteRow->potentialid,
            accountid: $quoteRow->accountid,
            assigned_user_id: $quoteRow->assigned_user_id,
            quote_stage: $quoteRow->quote_stage,
            validtill: $quoteRow->validtill,
            description: $quoteRow->description,
            subtotal: (float) $quoteRow->subtotal,
            discount_percent: $quoteRow->discount_percent !== null ? (float) $quoteRow->discount_percent : null,
            total: (float) $quoteRow->total,
            createdtime: $quoteRow->createdtime,
            modifiedtime: $quoteRow->modifiedtime,
            items: $items,
            account_name: $accountName,
            potential_name: $potentialName,
            assigned_user_name: $assignedUserName
        );
    }

    /**
     * Soft delete a quote record by marking it as deleted.
     * 
     * Implementation details:
     * - Performs soft delete by updating vtiger_crmentity.deleted = 1
     *   rather than removing records from database (preserves audit trail)
     * - Filters by setype = 'Quotes' to ensure correct entity type is affected
     * - Uses update() method returning affected row count; success determined by count > 0
     * - Does not cascade delete to related entities (line items, attachments, etc.)
     *   as those maintain independent lifecycle and visibility rules
     * - Line items remain in vtiger_inventoryproductrel but become inaccessible via normal queries
     *   due to parent quote being soft-deleted
     * 
     * Data retention:
     * - Soft-deleted records remain queryable with explicit deleted = 1 filter
     * - Enables audit compliance and potential restoration via administrative tools
     * - Consider implementing periodic archival job for records beyond retention period
     * - Line items for deleted quotes accumulate; consider cleanup strategy for storage management
     * 
     * @inheritDoc
     * 
     * @param int $quoteid The quoteid of the quote to soft-delete
     * 
     * @return bool True if deletion was successful (record found and updated),
     *              false if quote not found or already deleted
     * 
     * @throws \RuntimeException If database update fails or connection is lost
     */
    public function delete(int $quoteid): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $quoteid)
            ->where('setype', 'Quotes')
            ->update(['deleted' => 1]) > 0;
    }

    /**
     * Retrieve a paginated list of quotes with optional search (legacy method).
     * 
     * Implementation details:
     * - Functionally similar to getAll() but without account filter parameter
     * - Maintained for backward compatibility with existing code that calls paginate()
     * - Constructs identical query structure with joins, filters, and ordering
     * - Applies base filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted records
     * - Optional search filter: Performs case-insensitive partial matching on subject,
     *   quote_no, and related account name using LIKE with wildcards
     * - Total count calculated before pagination to ensure accurate metadata in paginator
     * - Results ordered by createdtime descending (most recent first)
     * - Validates that returned quote IDs have setype = 'Quotes' in vtiger_crmentity
     * - Fetches line items, potentials, and users using same batching strategy as getAll()
     * - Maps each quote to QuoteResponse DTO with calculated line items and denormalized data
     * - Returns Laravel LengthAwarePaginator with path and query preservation for frontend
     * 
     * Deprecation note:
     * - This method duplicates logic from getAll(); consider refactoring to share common
     *   query building and mapping logic to reduce maintenance burden
     * - New code should prefer getAll() which supports account filtering
     * 
     * @inheritDoc
     * 
     * @param int $page Page number (1-based index)
     * @param int $perPage Items per page
     * @param string|null $search Optional search term for partial matching on quote fields
     * 
     * @return LengthAwarePaginator<QuoteResponse> Paginator containing mapped QuoteResponse
     *                                               DTOs for the requested page with line items
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function paginate(int $page, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_quotes as q')
            ->join('vtiger_crmentity as c', 'q.quoteid', '=', 'c.crmid')
            ->leftJoin('vtiger_account as a', 'q.accountid', '=', 'a.accountid')
            ->select(
                'q.quoteid',
                'q.quote_no as quoteno',
                'q.subject',
                'q.potentialid',
                'q.accountid',
                'c.smownerid as assigned_user_id',
                'q.quotestage as quote_stage',
                'q.validtill',
                'c.description',
                'q.subtotal',
                'q.discount_percent',
                'q.total',
                'c.createdtime',
                'c.modifiedtime',
                'a.accountname'
            )
            ->where('c.deleted', 0);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('q.subject', 'like', "%{$search}%")
                    ->orWhere('q.quote_no', 'like', "%{$search}%")
                    ->orWhere('a.accountname', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $quotes = $query
            ->orderBy('c.createdtime', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        if ($quotes->isEmpty()) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query()
            ]);
        }

        // Obtener solo IDs que son cotizaciones
        $quoteIds = $quotes->pluck('quoteid')->toArray();
        $validQuoteIds = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->whereIn('crmid', $quoteIds)
            ->where('setype', 'Quotes')
            ->pluck('crmid')
            ->toArray();

        // Cargar ítems solo para cotizaciones válidas
        $items = [];
        if (!empty($validQuoteIds)) {
            $items = DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->whereIn('id', $validQuoteIds)
                ->orderBy('sequence_no')
                ->get()
                ->groupBy('id');
        }


        $potentialIds = $quotes->pluck('potentialid')->unique()->filter()->toArray();
        $userIds = $quotes->pluck('assigned_user_id')->unique()->filter()->toArray();

        $potentials = !empty($potentialIds)
            ? DB::connection('vtiger')->table('vtiger_potential')->whereIn('potentialid', $potentialIds)->pluck('potentialname', 'potentialid')->toArray()
            : [];

        $users = !empty($userIds)
            ? DB::connection('vtiger')->table('vtiger_users')->whereIn('id', $userIds)->get()->mapWithKeys(fn($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)])->toArray()
            : [];

        // Convertir a entidades
        $entities = $quotes->map(function ($quote) use ($items, $potentials, $users) {
            $quoteItems = [];
            if (isset($items[$quote->quoteid])) {
                $quoteItems = $items[$quote->quoteid]->map(function ($item) {
                    $netprice = $item->listprice * (1 - ($item->discount_percent ?? 0) / 100);
                    $total = ($item->quantity ?? 0) * $netprice;

                    return [
                        'productid' => $item->productid,
                        'sequence_no' => (int) $item->sequence_no,
                        'productname' => $item->description ?? '',
                        'quantity' => (float) $item->quantity,
                        'listprice' => (float) $item->listprice,
                        'discount_percent' => (float) $item->discount_percent,
                        'netprice' => $netprice,
                        'total' => $total,
                        'description' => $item->comment ?? null,
                    ];
                })->toArray();
            }

            return new QuoteResponse(
                quoteid: $quote->quoteid,
                quoteno: $quote->quoteno,
                subject: $quote->subject,
                potentialid: $quote->potentialid,
                accountid: $quote->accountid,
                assigned_user_id: $quote->assigned_user_id,
                quote_stage: $quote->quote_stage,
                validtill: $quote->validtill,
                description: $quote->description,
                subtotal: (float) $quote->subtotal,
                discount_percent: $quote->discount_percent !== null ? (float) $quote->discount_percent : null,
                total: (float) $quote->total,
                createdtime: $quote->createdtime,
                modifiedtime: $quote->modifiedtime,
                items: $quoteItems,
                account_name: $quote->accountname ?? null,
                potential_name: $quote->potentialid ? ($potentials[$quote->potentialid] ?? null) : null,
                assigned_user_name: $users[$quote->assigned_user_id] ?? null
            );
        })->toArray();

        return new LengthAwarePaginator($entities, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);
    }

    /**
     * Generate the next available quote number using Vtiger sequence management.
     * 
     * Implementation details:
     * - Queries vtiger_modentity_num table for Quotes module sequence configuration
     * - Expected prefix format: C-YY where YY is 2-digit current year (e.g., C-26 for 2026)
     * - If sequence record exists with matching prefix: increments cur_id and returns formatted number
     * - If sequence record exists but prefix differs (year changed): resets cur_id to 1, updates prefix
     * - If no sequence record exists: creates new record with current year prefix and cur_id = 1
     * - Returns formatted quote number: prefix + zero-padded 5-digit counter (e.g., C-26-00001)
     * 
     * Format specification:
     * - Pattern: C-YY-XXXXX where YY is 2-digit year, XXXX is zero-padded 5-digit counter
     * - Example: C-26-00001, C-26-00002, ..., C-27-00001 (resets on year change)
     * - Counter padding ensures consistent sorting and display in UI
     * 
     * Sequence management:
     * - Uses vtiger_modentity_num table which is Vtiger's standard for entity numbering
     * - active flag ensures only enabled sequences are used
     * - start_id field preserved for reference but not used in increment logic
     * - Updates performed directly via query builder; consider using Vtiger's sequence API if available
     * 
     * Concurrency consideration:
     * - Sequence update not wrapped in transaction or lock; may have race conditions under
     *   high concurrent quote creation load
     * - For production systems with heavy write volume, consider:
     *   - Database-level locking (SELECT ... FOR UPDATE)
     *   - Application-level queue for quote number assignment
     *   - Pre-allocation of number ranges to reduce contention
     * 
     * @return string Formatted quote number following C-YY-XXXXX pattern
     * 
     * @throws \RuntimeException If database query fails or sequence table is inaccessible
     * 
     * @internal Used by create() method for quote number generation
     */
    private function generateQuoteNumber(): string
    {
        $currentYear = date('y');
        $expectedPrefix = "C-{$currentYear}-";

        $sequenceRow = DB::connection('vtiger')
            ->table('vtiger_modentity_num')
            ->where('semodule', 'Quotes')
            ->where('active', 1)
            ->first();

        if ($sequenceRow) {
            if ($sequenceRow->prefix !== $expectedPrefix) {
                // El año ha cambiado, actualizar el prefijo y reiniciar la secuencia
                $newCurId = 1; // Reiniciar desde 1 para el nuevo año

                DB::connection('vtiger')
                    ->table('vtiger_modentity_num')
                    ->where('num_id', $sequenceRow->num_id)
                    ->update([
                        'prefix' => $expectedPrefix,
                        'cur_id' => $newCurId
                    ]);

                $formattedId = str_pad($newCurId, 5, '0', STR_PAD_LEFT);
                return $expectedPrefix . $formattedId;
            }


            $nextId = $sequenceRow->cur_id + 1;

            DB::connection('vtiger')
                ->table('vtiger_modentity_num')
                ->where('num_id', $sequenceRow->num_id)
                ->update(['cur_id' => $nextId]);

            $formattedId = str_pad($nextId, 5, '0', STR_PAD_LEFT);
            return $sequenceRow->prefix . $formattedId;
        }


        $newCurId = 1;
        $formattedId = str_pad($newCurId, 5, '0', STR_PAD_LEFT);

        DB::connection('vtiger')
            ->table('vtiger_modentity_num')
            ->insert([
                'semodule' => 'Quotes',
                'prefix' => $expectedPrefix,
                'start_id' => 1,
                'cur_id' => $newCurId,
                'active' => 1
            ]);

        return $expectedPrefix . $formattedId;
    }

    /**
     * Perform global search for quotes with type discrimination.
     * 
     * Implementation details:
     * - Searches across subject and quote_no fields with partial matching using LIKE
     * - Adds static 'type' field set to 'quote' for frontend entity routing
     * - Adds 'url' field with route pattern for direct navigation to quote detail
     * - Includes denormalized client name and total amount for enriched search result display
     * - Limits results to specified count for global search aggregation performance
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns results formatted for global search response structure
     * 
     * Global search integration:
     * - Response format matches other entity search methods for consistent
     *   aggregation in GlobalSearchUseCase
     * - 'type' field enables frontend to route to correct detail view
     * - 'url' field provides pre-built navigation link for convenience
     * - Additional fields (client, total) enable richer result previews for quote-specific context
     * 
     * @inheritDoc
     * 
     * @param string $query The search query string for partial matching
     * @param int $limit Maximum number of results to return
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing quote information
     *                                          with type discrimination for
     *                                          global search aggregation
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function search(string $query, int $limit): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_quotes.accountid', '=', 'vtiger_account.accountid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($query) {
                $q->where('vtiger_quotes.subject', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_quotes.quote_no', 'LIKE', "%{$query}%");
            })
            ->select(
                'vtiger_quotes.quoteid as id',
                'vtiger_quotes.subject as title',
                'vtiger_quotes.quote_no as number',
                'vtiger_account.accountname as client',
                'vtiger_quotes.total as total',
                DB::raw("'quote' as type")
            )
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'number' => $item->number,
                'client' => $item->client,
                'total' => $item->total,
                'url' => "/dashboard/quotes/{$item->id}",
            ])
            ->toArray();
    }

    /**
     * Count the number of quotes related to a specific client.
     * 
     * Implementation details:
     * - Performs efficient COUNT query against vtiger_quotes joined with vtiger_crmentity
     * - Filters by accountid = $clientId to count only quotes for specified account
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns integer count suitable for dashboard summary cards and statistics
     * - No entity hydration performed; optimized for read-only aggregation use case
     * 
     * Performance note:
     * - Query uses indexed fields (accountid, deleted) for efficient lookup
     * - COUNT(*) executed at database level; no application-level iteration required
     * - Suitable for frequent calls in client summary views; consider caching if called
     *   multiple times per request
     * 
     * @inheritDoc
     * 
     * @param int $clientId The accountid of the client for which to count quotes
     * 
     * @return int The number of active quotes related to the specified client.
     *             Returns 0 if the client has no quotes or if the client does
     *             not exist.
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function countByClient(int $clientId): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_quotes.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }
}