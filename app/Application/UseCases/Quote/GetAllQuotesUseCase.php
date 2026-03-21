<?php

namespace App\Application\UseCases\Quote;

use App\Application\Repositories\QuoteRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class GetAllQuotesUseCase
 * 
 * Use case for retrieving paginated lists of quotes with optional search and account filtering.
 * 
 * This use case orchestrates the retrieval of quote collections by delegating to the
 * QuoteRepositoryInterface implementation. It supports pagination, text search across
 * quote fields, and filtering by related account (client) for scoped quote listings.
 * 
 * Key characteristics:
 * - Single responsibility: Focuses solely on retrieving paginated quote collections
 * - Delegation pattern: Relies on repository for data access and query logic
 * - Pagination support: Returns Laravel LengthAwarePaginator with metadata
 * - Flexible filtering: Supports search term and account ID filters
 * - Dependency injection: Repository injected via constructor for testability
 * 
 * Query capabilities:
 * - Pagination: Configurable page number and items per page
 * - Search: Case-insensitive partial matching on subject, quote number, and account name
 * - Account filter: Scope results to quotes linked to specific client account
 * - Soft delete exclusion: Automatically excludes quotes with deleted = 1 flag
 * - Ordering: Results ordered by createdtime descending (most recent first)
 * 
 * Use cases:
 * - Quote list views with pagination controls
 * - Client detail page quote tabs (filtered by account)
 * - Search results pages with quote type filtering
 * - Dashboard widgets showing recent quotes
 * - Export functionality requiring paginated quote data
 * 
 * @package App\Application\UseCases\Quote
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see QuoteRepositoryInterface::getAll() For the data access implementation
 * @see \App\Application\DTOs\Quote\QuoteResponse For the DTO structure returned in paginator
 * @see \App\Http\Controllers\Api\QuoteController For HTTP endpoint integration
 * @see LengthAwarePaginator For Laravel pagination structure and metadata
 */
class GetAllQuotesUseCase
{
    

    /**
     * GetAllQuotesUseCase constructor.
     * 
     * Injects the QuoteRepositoryInterface dependency via constructor injection.
     * This enables dependency inversion, facilitating unit testing with mock
     * repository implementations and promoting loose coupling between the use
     * case and specific data access implementations.
     * 
     * The readonly modifier ensures the repository dependency cannot be modified
     * after instantiation, promoting immutability and predictable behavior.
     * 
     * @param QuoteRepositoryInterface $quoteRepository The quote repository
     *                                                  instance for data access
     * 
     * @return void
     */
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    /**
     * Execute the use case to retrieve a paginated list of quotes.
     * 
     * This method delegates to the quote repository's getAll() method to retrieve
     * a paginated collection of quotes with optional search and account filtering.
     * The returned paginator includes metadata for frontend pagination controls.
     * 
     * Query behavior:
     * - Base query joins vtiger_quotes with vtiger_crmentity for audit data
     *   and vtiger_account for denormalized client name display
     * - Applies filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted quotes
     * - Search filter: Case-insensitive partial matching on subject, quote_no,
     *   and related account name using LIKE with wildcards
     * - Account filter: When $accountId provided, adds WHERE clause on q.accountid
     * - Ordering: Results ordered by createdtime descending (most recent first)
     * - Pagination: Uses offset/limit pattern with total count for metadata
     * 
     * Response structure (LengthAwarePaginator):
     * - 'data': Array of QuoteResponse DTOs for current page
     * - 'meta': Pagination metadata including:
     *   - 'current_page': Current page number (1-based)
     *   - 'last_page': Total number of pages
     *   - 'per_page': Items per page
     *   - 'total': Total number of matching records across all pages
     * - 'links': Pagination navigation links (first, last, prev, next)
     * 
     * Each QuoteResponse DTO includes:
     * - Core quote fields: quoteid, quoteno, subject, quote_stage, validtill, etc.
     * - Financial fields: subtotal, discount_percent, total, currency_id
     * - Relationship fields: accountid, potentialid, assigned_user_id
     * - Denormalized names: account_name, potential_name, assigned_user_name
     * - Line items: Array of quote items with calculated pricing (netprice, total)
     * - Audit fields: createdtime, modifiedtime, isActive
     * 
     * Performance considerations:
     * - Repository should use indexed fields (quoteid, accountid, deleted) for
     *   efficient lookups and filtering
     * - Search operations use LIKE with leading wildcard; consider full-text
     *   indexing for large datasets or frequent search usage
     * - Line items fetched via batch query to avoid N+1 problem
     * - Consider caching for frequently accessed quote lists with same filters
     * 
     * Error handling:
     * - Database query failures propagate as RuntimeException to caller
     * - Invalid page/perPage values handled by repository or Laravel paginator
     * - No validation at use case layer; assume controller/request validation
     * 
     * @param int $page Page number to retrieve (1-based index). Default is 1.
     *                  Must be positive integer; values <= 0 may be normalized
     *                  by repository or paginator implementation.
     * @param int $perPage Number of items to return per page. Default is 20.
     *                     Should be positive integer; typical values range
     *                     from 10 to 100 depending on UI requirements.
     * @param string|null $search Optional search term for filtering quotes by
     *                            subject, quote number, or related account name.
     *                            Uses case-insensitive partial matching. Default
     *                            is null (no search filter applied).
     * @param int|null $accountId Optional account (client) ID to filter quotes
     *                            by related client. When provided, only quotes
     *                            linked to this account are returned. Default
     *                            is null (no account filter applied).
     * 
     * @return LengthAwarePaginator<QuoteResponse> Paginator instance containing
     *                                             QuoteResponse DTOs for the
     *                                             requested page with metadata
     *                                             for frontend pagination controls.
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     *                           during quote retrieval
     * @throws \InvalidArgumentException If page or perPage parameters are invalid
     *                                   (e.g., negative numbers or zero)
     * 
     * @example
     * // Get first page of all quotes with default pagination
     * $useCase = new GetAllQuotesUseCase($quoteRepository);
     * $paginator = $useCase->execute(page: 1, perPage: 20);
     * 
     * @example
     * // Search quotes by subject
     * $paginator = $useCase->execute(
     *     page: 1,
     *     perPage: 20,
     *     search: 'Enterprise License'
     * );
     * 
     * @example
     * // Get quotes for specific client account
     * $paginator = $useCase->execute(
     *     page: 1,
     *     perPage: 20,
     *     accountId: 123
     * );
     * 
     * @example
     * // Combine search and account filter
     * $paginator = $useCase->execute(
     *     page: 1,
     *     perPage: 20,
     *     search: 'Q1',
     *     accountId: 123
     * );
     * 
     * @example
     * // Access paginator data in controller
     * return response()->json([
     *     'data' => $paginator->items(),
     *     'meta' => [
     *         'current_page' => $paginator->currentPage(),
     *         'last_page' => $paginator->lastPage(),
     *         'per_page' => $paginator->perPage(),
     *         'total' => $paginator->total(),
     *     ],
     *     'links' => [
     *         'first' => $paginator->url(1),
     *         'last' => $paginator->url($paginator->lastPage()),
     *         'prev' => $paginator->previousPageUrl(),
     *         'next' => $paginator->nextPageUrl(),
     *     ]
     * ]);
     * 
     * @example
     * // Frontend usage pattern
     * const loadQuotes = async (page, search, accountId) => {
     *     const response = await api.get('/quotes', {
     *         params: { page, per_page: 20, search, account_id: accountId }
     *     });
     *     displayQuotes(response.data.data);
     *     updatePagination(response.data.meta);
     * };
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
     * @see QuoteRepositoryInterface::getAll() For the repository implementation
     * @see \App\Application\DTOs\Quote\QuoteResponse For the DTO structure
     * @see LengthAwarePaginator For Laravel pagination structure and methods
     * @see \App\Http\Controllers\Api\QuoteController::index() For HTTP endpoint
     */
    public function execute(
        int $page = 1, 
        int $perPage = 20, 
        ?string $search = null,
        ?int $accountId = null  
    ): LengthAwarePaginator
    {
        return $this->quoteRepository->getAll($page, $perPage, $search, $accountId);
    }
}