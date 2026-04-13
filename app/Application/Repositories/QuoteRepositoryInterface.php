<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\DTOs\Quote\QuoteResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface QuoteRepositoryInterface
 * 
 * Defines the contract for quote (sales quote) data access operations in the CRM system.
 * 
 * This interface abstracts the data persistence layer for Quote entities,
 * allowing for interchangeable implementations (e.g., Vtiger database, external API,
 * in-memory for testing) without affecting the business logic layer that depends
 * on this contract.
 * 
 * The repository pattern provides a clean separation between domain logic and
 * data access logic, promoting testability, maintainability, and adherence to
 * the Dependency Inversion Principle. Quotes represent formal price proposals
 * sent to clients and are critical for the sales pipeline and revenue forecasting.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see QuoteResponse For the DTO returned by quote retrieval methods
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerQuoteRepository For the Vtiger-specific implementation
 * @see CreateQuoteRequest For the DTO used in creation operations
 * @see UpdateQuoteRequest For the DTO used in update operations
 */
interface QuoteRepositoryInterface
{
    /**
     * Create a new quote record in the database.
     * 
     * This method persists a new quote entity using data from the provided
     * request DTO. It handles the creation of records across multiple related
     * tables (vtiger_quotes, vtiger_crmentity, vtiger_inventoryproductrel for
     * line items) within a database transaction to ensure data consistency.
     * 
     * The method automatically generates required fields such as quote number,
     * timestamps, and audit information. It returns the newly created quote's
     * ID for subsequent operations or redirection.
     * 
     * @param CreateQuoteRequest $request The validated request DTO containing
     *                                    quote data for creation including
     *                                    subject, account ID, line items,
     *                                    pricing, and terms.
     * @param int $createdByUserId The ID of the user performing the creation
     *                             operation, used for audit tracking (smcreatorid,
     *                             smownerid fields).
     * 
     * @return int The unique identifier (quoteid) of the newly created quote.
     * 
     * @throws \InvalidArgumentException If required fields are missing or invalid
     *                                   (e.g., invalid account ID, empty line items,
     *                                   negative amounts).
     * @throws \RuntimeException If the database transaction fails or connection
     *                           is lost.
     * @throws \Exception If any error occurs during the creation process.
     * 
     * @example
     * // Create new quote with line items
     * $request = new CreateQuoteRequest(
     *     subject: 'Q1 2026 Software License',
     *     accountid: 123,
     *     items: [
     *         ['productid' => 1, 'quantity' => 10, 'listprice' => 500],
     *         ['productid' => 2, 'quantity' => 5, 'listprice' => 1000]
     *     ],
     *     validtill: '2026-06-30'
     * );
     * $newId = $repository->create($request, $currentUserId);
     * 
     * @example
     * // Redirect to new quote detail page
     * return redirect()->route('quotes.show', $newId);
     * 
     * @example
     * // Return ID for frontend reference
     * return response()->json(['quote_id' => $newId]);
     * 
     * @see CreateQuoteRequest For the expected input structure
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function create(CreateQuoteRequest $request, int $createdByUserId): int;

    /**
     * Update an existing quote record in the database.
     * 
     * This method updates a quote entity using data from the provided request
     * DTO. It handles updates across multiple related tables within a database
     * transaction to ensure data consistency.
     * 
     * The method updates audit information (modifiedtime, modifiedby) automatically
     * and preserves fields not included in the update request. Line items can be
     * added, updated, or removed as part of the update operation. It returns a
     * boolean indicating whether the update was successful.
     * 
     * @param UpdateQuoteRequest $request The validated request DTO containing
     *                                    updated quote data including fields
     *                                    to modify and updated line items.
     * @param int $modifiedByUserId The ID of the user performing the update
     *                              operation, used for audit tracking (modifiedby
     *                              field).
     * 
     * @return bool True if the update was successful, false if the quote was
     *              not found or the update failed.
     * 
     * @throws \InvalidArgumentException If the quote ID is invalid or required
     *                                   fields are missing.
     * @throws \RuntimeException If the database transaction fails or connection
     *                           is lost.
     * @throws \Exception If any error occurs during the update process.
     * 
     * @example
     * // Update quote status
     * $request = new UpdateQuoteRequest(quote_stage: 'Sent');
     * $success = $repository->update($request, $currentUserId);
     * 
     * @example
     * // Update multiple fields and line items
     * $request = new UpdateQuoteRequest(
     *     subject: 'Updated Q1 License Quote',
     *     validtill: '2026-07-31',
     *     items: [
     *         ['productid' => 1, 'quantity' => 15, 'listprice' => 500],
     *         ['productid' => 3, 'quantity' => 2, 'listprice' => 2000]
     *     ]
     * );
     * $success = $repository->update($request, $currentUserId);
     * 
     * @example
     * // Handle update result
     * if ($success) {
     *     toast()->success('Quote updated successfully');
     * } else {
     *     toast()->error('Failed to update quote');
     * }
     * 
     * @see UpdateQuoteRequest For the expected input structure
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function update(UpdateQuoteRequest $request, int $modifiedByUserId): bool;

    /**
     * Retrieve a single quote by its unique identifier.
     * 
     * This method fetches a QuoteResponse DTO by its primary key (quoteid).
     * It includes all quote fields plus denormalized data from related tables
     * (account name, potential name, assigned user name, line items with
     * calculated prices) for comprehensive display.
     * 
     * The method returns null if no quote with the specified ID exists or if
     * the quote has been soft-deleted (deleted flag set in crmentity).
     * 
     * @param int $quoteid The unique identifier (quoteid) of the quote to retrieve.
     * 
     * @return QuoteResponse|null The QuoteResponse DTO if found, or null if not
     *                            found or soft-deleted.
     * 
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Retrieve quote by ID
     * $quote = $repository->findById(456);
     * 
     * @example
     * // Handle not found case
     * if ($quote === null) {
     *     throw new QuoteNotFoundException(456);
     * }
     * 
     * @example
     * // Display quote details
     * echo "Quote: {$quote->quoteno}";
     * echo "Subject: {$quote->subject}";
     * echo "Total: {$quote->total}";
     * echo "Client: {$quote->account_name}";
     * 
     * @example
     * // Iterate through line items
     * foreach ($quote->items as $item) {
     *     echo $item['productname'];
     *     echo $item['quantity'];
     *     echo $item['total'];
     * }
     * 
     * @see QuoteResponse For the DTO structure returned
     * @see QuoteNotFoundException For custom exception handling
     */
    public function findById(int $quoteid): ?QuoteResponse;

    /**
     * Soft delete a quote record by marking it as deleted.
     * 
     * This method performs a soft delete by updating the deleted flag in the
     * vtiger_crmentity table rather than removing the record from the database.
     * This preserves audit history and allows for potential restoration.
     * 
     * After deletion, the quote will no longer appear in normal queries unless
     * explicitly requested with deleted records included. Related entities
     * (line items, attachments) are not automatically deleted but should be
     * handled according to business requirements.
     * 
     * @param int $quoteid The unique identifier (quoteid) of the quote to delete.
     * 
     * @return bool True if the deletion was successful, false if the quote was
     *              not found or already deleted.
     * 
     * @throws \RuntimeException If the database update fails or connection is lost.
     * 
     * @example
     * // Delete quote
     * $success = $repository->delete(456);
     * 
     * @example
     * // Confirm deletion to user
     * if ($success) {
     *     return response()->json(['message' => 'Quote deleted']);
     * }
     * 
     * @example
     * // Handle deletion in frontend
     * const handleDelete = async (id) => {
     *     const confirmed = await confirm('Delete this quote?');
     *     if (confirmed) {
     *         await api.delete(`/quotes/${id}`);
     *     }
     * };
     * 
     * @example
     * // Check if quote is deleted
     * $quote = $repository->findById(456);
     * if ($quote === null) {
     *     // Quote not found or deleted
     * }
     * 
     * @see QuoteResponse::isActive For checking if a quote is soft-deleted
     */
    public function delete(int $quoteid): bool;

    /**
     * Retrieve a paginated list of quotes with optional search.
     * 
     * This method supports pagination and text search across quote fields.
     * It returns a Laravel paginator instance containing QuoteResponse DTOs
     * for the requested page.
     * 
     * The search functionality performs case-insensitive partial matching against
     * quote number, subject, account name, and description. Line items are not
     * searched for performance reasons.
     * 
     * @param int $page The page number to retrieve (1-based index).
     * @param int $perPage The number of items per page.
     * @param string|null $search Optional search term for filtering quotes by
     *                            quote number, subject, account name, or description.
     * 
     * @return LengthAwarePaginator<QuoteResponse> A paginator instance containing
     *                                             QuoteResponse DTOs for the
     *                                             requested page, with metadata
     *                                             about total items and pages.
     * 
     * @throws \InvalidArgumentException If page or perPage parameters are invalid
     *                                   (e.g., negative numbers or zero).
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Get first page of all quotes
     * $paginator = $repository->paginate(1, 20);
     * 
     * @example
     * // Search quotes by subject
     * $paginator = $repository->paginate(1, 20, 'Q1 License');
     * 
     * @example
     * // Iterate through results
     * foreach ($paginator->items() as $quote) {
     *     echo $quote->quoteno;
     *     echo $quote->subject;
     *     echo $quote->total;
     * }
     * 
     * @example
     * // Display pagination info
     * echo "Page {$paginator->currentPage()} of {$paginator->lastPage()}";
     * echo "Total quotes: {$paginator->total()}";
     * 
     * @see QuoteResponse For the DTO structure returned
     * @see LengthAwarePaginator For Laravel pagination structure
     */
    public function paginate(int $page, int $perPage, ?string $search = null): LengthAwarePaginator;

    /**
     * Perform a global search for quotes with ranking and limits.
     * 
     * This method provides advanced search functionality with relevance ranking,
     * result limiting, and matching across multiple quote fields. It is designed
     * for global search features that aggregate results from multiple entity types.
     * 
     * The search supports partial matching and returns results ordered by relevance.
     * Each result includes a type identifier for frontend routing and display purposes.
     * 
     * @param string $query The search query string. Supports partial matching across
     *                      quoteno, subject, account name, and description fields.
     * @param int $limit The maximum number of results to return.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing quote information with
     *                                          a 'type' field set to 'quote' for
     *                                          frontend identification.
     * 
     * @throws \InvalidArgumentException If the query is empty or limit is invalid
     *                                   (e.g., negative or zero).
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Global search with limit
     * $results = $repository->search('enterprise license', 5);
     * 
     * @example
     * // Format for global search response
     * return [
     *     'quotes' => $results,
     *     'total' => count($results)
     * ];
     * 
     * @example
     * // Response structure
     * // Returns: [
     * //     ['id' => 456, 'quoteno' => 'QT-2026-001', 'subject' => 'Enterprise License', 'type' => 'quote', ...],
     * //     ...
     * // ]
     * 
     * @example
     * // Use in global search bar
     * const searchQuotes = async (query) => {
     *     const response = await api.get(`/search?query=${query}&limit=10`);
     *     displayResults(response.data.quotes);
     * };
     * 
     * @see \App\Application\UseCases\GlobalSearchUseCase For the use case that
     *                                                    aggregates results from
     *                                                    multiple repositories
     */
    public function search(string $query, int $limit): array;

    /**
     * Count the number of quotes related to a specific client.
     * 
     * This method performs an efficient COUNT query to determine how many quotes
     * are linked to a specific account (client). It is optimized for performance
     * and is typically used for displaying summary statistics on client detail pages.
     * 
     * The count excludes soft-deleted quotes and only includes active records in
     * the CRM system.
     * 
     * @param int $clientId The unique identifier (accountid) of the client for
     *                      which to count quotes.
     * 
     * @return int The number of active quotes related to the specified client.
     *             Returns 0 if the client has no quotes or if the client does
     *             not exist.
     * 
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Get quote count for client dashboard
     * $count = $repository->countByClient(123);
     * 
     * @example
     * // Display in frontend
     * echo "Quotes: {$count}";
     * 
     * @example
     * // Use in summary card
     * <div class="stat-card">
     *     <span class="label">Quotes</span>
     *     <span class="value"><?= $count ?></span>
     * </div>
     * 
     * @example
     * // Check if client has quotes
     * if ($count > 0) {
     *     echo 'Client has active quotes';
     * } else {
     *     echo 'No quotes for this client';
     * }
     * 
     * @see \App\Application\UseCases\GetClientSummaryUseCase For typical usage
     *                                                          in client summaries
     */
    public function countByClient(int $clientId): int;

    /**
     * Retrieve a paginated list of quotes with optional search and account filter.
     * 
     * This method supports pagination, text search across quote fields, and
     * filtering by related account (client). It returns a Laravel paginator
     * instance containing QuoteResponse DTOs for the requested page.
     * 
     * The search functionality performs case-insensitive partial matching against
     * quote number, subject, description, and related account name. The account
     * filter enables viewing all quotes for a specific client.
     * 
     * @param int $page The page number to retrieve (1-based index). Default is 1.
     * @param int $perPage The number of items per page. Default is 20.
     * @param string|null $search Optional search term for filtering quotes by
     *                            quote number, subject, description, or account name.
     * @param int|null $accountId Optional account (client) ID to filter quotes by
     *                            related client. When provided, only quotes linked
     *                            to this account are returned.
     * 
     * @return LengthAwarePaginator<QuoteResponse> A paginator instance containing
     *                                             QuoteResponse DTOs for the
     *                                             requested page, with metadata
     *                                             about total items and pages.
     * 
     * @throws \InvalidArgumentException If page or perPage parameters are invalid.
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Get first page of all quotes
     * $paginator = $repository->getAll(1, 20);
     * 
     * @example
     * // Search quotes by subject
     * $paginator = $repository->getAll(1, 20, 'Q1 License');
     * 
     * @example
     * // Get quotes for a specific client
     * $paginator = $repository->getAll(1, 20, null, 123);
     * 
     * @example
     * // Combine search and account filter
     * $paginator = $repository->getAll(1, 20, 'Enterprise', 123);
     * 
     * @example
     * // Iterate through results
     * foreach ($paginator->items() as $quote) {
     *     echo $quote->quoteno;
     *     echo $quote->subject;
     *     echo $quote->total;
     *     echo $quote->account_name;
     * }
     * 
     * @see QuoteResponse For the DTO structure returned
     * @see LengthAwarePaginator For Laravel pagination structure
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $accountId = null
    ): LengthAwarePaginator;

    /**
     * Get next available quote ID
     * 
     * @return int Next quote ID
     */
    public function generateQuoteId(): int;

    /**
     * Duplicate an existing quote with new ID and quote number
     * 
     * @param array $baseData   
     * @param array $itemsData   
     * @param int $createdByUserId User ID 
     * @return int|null new quote ID
     */
    public function duplicate(array $baseData, array $itemsData, int $createdByUserId): ?int;
}
