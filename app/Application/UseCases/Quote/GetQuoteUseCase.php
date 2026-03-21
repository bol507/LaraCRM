<?php

namespace App\Application\UseCases\Quote;

use App\Application\Repositories\QuoteRepositoryInterface;
use App\Application\DTOs\Quote\QuoteResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class GetQuoteUseCase
 * 
 * Use case for retrieving individual quotes and paginated quote lists.
 * 
 * This use case orchestrates quote retrieval operations by delegating to the
 * QuoteRepositoryInterface implementation. It provides two distinct methods:
 * one for retrieving a single quote by ID and another for retrieving paginated
 * quote collections with optional search functionality.
 * 
 * Key characteristics:
 * - Dual responsibility: Single quote retrieval and paginated list retrieval
 * - Delegation pattern: Relies on repository for all data access logic
 * - Type-safe returns: QuoteResponse for single quote, LengthAwarePaginator for lists
 * - Dependency injection: Repository injected via constructor for testability
 * - Immutability: Repository property marked readonly for predictable behavior
 * 
 * Note on method naming:
 * - executeById(): Clearly indicates single entity retrieval by identifier
 * - execute(): Legacy method name for paginated list; consider rename to
 *   executePaginated() or getAll() for clarity in future refactoring
 * 
 * Use cases:
 * - Quote detail page (executeById)
 * - Quote list views with pagination (execute)
 * - Quote search functionality with pagination (execute with search term)
 * - API endpoints requiring single quote or quote list responses
 * 
 * @package App\Application\UseCases\Quote
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see QuoteRepositoryInterface::findById() For single quote retrieval implementation
 * @see QuoteRepositoryInterface::paginate() For paginated list implementation
 * @see QuoteResponse For the DTO structure returned by executeById()
 * @see LengthAwarePaginator For Laravel pagination structure returned by execute()
 * @see \App\Http\Controllers\Api\QuoteController For HTTP endpoint integration
 */
class GetQuoteUseCase
{
    

    /**
     * GetQuoteUseCase constructor.
     * 
     * Injects the QuoteRepositoryInterface dependency via constructor injection.
     * This enables dependency inversion, facilitating unit testing with mock
     * repository implementations and promoting loose coupling between the use
     * case and specific data access implementations.
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
     * Retrieve a single quote by its unique identifier.
     * 
     * This method delegates to the quote repository's findById() method to retrieve
     * a complete quote record including all line items, calculated pricing, and
     * denormalized related entity information (account name, potential name, assigned
     * user name).
     * 
     * Quote data includes:
     * - Core quote fields: quoteid, quoteno, subject, quote_stage, validtill
     * - Financial fields: subtotal, discount_percent, total, with tax calculations
     * - Relationship fields: accountid, potentialid, assigned_user_id
     * - Denormalized names: account_name, potential_name, assigned_user_name
     * - Line items: Array of quote items with calculated pricing (netprice, total)
     * - Audit fields: createdtime, modifiedtime
     * 
     * Query behavior:
     * - First verifies that the provided ID corresponds to a quote entity type
     *   in vtiger_crmentity (setype = 'Quotes')
     * - Joins vtiger_quotes with vtiger_crmentity for audit data and vtiger_account
     *   for denormalized client name
     * - Fetches line items from vtiger_inventoryproductrel with pricing calculations
     * - Performs separate lookups for potential name and assigned user name if IDs present
     * - Excludes soft-deleted quotes (vtiger_crmentity.deleted = 0)
     * 
     * Error handling:
     * - Returns null if quote not found or if ID does not correspond to quote entity type
     * - Database query failures propagate as RuntimeException to caller
     * - No validation at use case layer; assume controller validates quoteid is positive integer
     * 
     * Performance considerations:
     * - Query uses primary key (quoteid) for efficient index lookup
     * - Line items fetched in single query without N+1 problem
     * - Potential and user lookups performed as separate queries; acceptable for
     *   detail view access patterns
     * - Consider caching for frequently accessed quotes if performance becomes concern
     * 
     * @param int $quoteid The unique identifier (quoteid) of the quote to retrieve.
     *                     Must be a positive integer corresponding to an existing
     *                     quote record in vtiger_quotes table.
     * 
     * @return QuoteResponse|null The mapped QuoteResponse DTO if quote is found
     *                            and active, or null if quote does not exist,
     *                            has been soft-deleted, or ID does not correspond
     *                            to a quote entity type.
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     *                           during quote retrieval
     * @throws \InvalidArgumentException If quoteid is invalid (<= 0), though
     *                                   validation typically performed at controller layer
     * 
     * @example
     * // Retrieve quote for detail page
     * $useCase = new GetQuoteUseCase($quoteRepository);
     * $quote = $useCase->executeById(456);
     * 
     * @example
     * // Handle not found case in controller
     * $quote = $useCase->executeById($quoteid);
     * if ($quote === null) {
     *     return response()->json(['error' => 'Quote not found'], 404);
     * }
     * return response()->json($quote);
     * 
     * @example
     * // Access quote data in frontend
     * const loadQuote = async (quoteid) => {
     *     const response = await api.get(`/quotes/${quoteid}`);
     *     displayQuoteDetails(response.data);
     *     displayLineItems(response.data.items);
     * };
     * 
     * @example
     * // Response structure from QuoteResponse::toArray()
     * // Returns:
     * // {
     * //     "quoteid": 456,
     * //     "quoteno": "C-26-00001",
     * //     "subject": "Enterprise License Q1",
     * //     "accountid": 123,
     * //     "account_name": "Acme Corporation",
     * //     "potentialid": 789,
     * //     "potential_name": "Enterprise Deal Q1",
     * //     "assigned_user_id": 5,
     * //     "assigned_user_name": "John Doe",
     * //     "quote_stage": "Sent",
     * //     "validtill": "2026-06-30",
     * //     "subtotal": 50000.00,
     * //     "discount_percent": 0,
     * //     "total": 53500.00,
     * //     "items": [
     * //         {
     * //             "productid": 1,
     * //             "productname": "Enterprise License",
     * //             "quantity": 10,
     * //             "listprice": 5000.00,
     * //             "discount_percent": 0,
     * //             "netprice": 5000.00,
     * //             "total": 50000.00
     * //         }
     * //     ],
     * //     "createdtime": "2026-01-15 10:00:00",
     * //     "modifiedtime": "2026-01-20 14:30:00"
     * // }
     * 
     * @see QuoteRepositoryInterface::findById() For the repository implementation
     * @see QuoteResponse For the DTO structure and available properties
     * @see \App\Http\Controllers\Api\QuoteController::show() For HTTP endpoint
     */
    public function executeById(int $quoteid): ?QuoteResponse
    {
        return $this->quoteRepository->findById($quoteid);
    }

    /**
     * Retrieve a paginated list of quotes with optional search.
     * 
     * This method delegates to the quote repository's paginate() method to retrieve
     * a paginated collection of quotes with optional search functionality. The returned
     * paginator includes metadata for frontend pagination controls.
     * 
     * Note: This method name (execute) is ambiguous as the class also has executeById().
     * Consider refactoring to executePaginated() or getAll() for clarity in future versions.
     * 
     * Query behavior:
     * - Base query joins vtiger_quotes with vtiger_crmentity for audit data
     *   and vtiger_account for denormalized client name display
     * - Applies filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted quotes
     * - Search filter: Case-insensitive partial matching on subject, quote_no,
     *   and related account name using LIKE with wildcards
     * - Ordering: Results ordered by createdtime descending (most recent first)
     * - Pagination: Uses offset/limit pattern with total count for metadata
     * - Validates that returned quote IDs have setype = 'Quotes' in vtiger_crmentity
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
     * - Financial fields: subtotal, discount_percent, total
     * - Relationship fields: accountid, potentialid, assigned_user_id
     * - Denormalized names: account_name, potential_name, assigned_user_name
     * - Line items: Array of quote items with calculated pricing (netprice, total)
     * - Audit fields: createdtime, modifiedtime
     * 
     * Performance considerations:
     * - Repository should use indexed fields (quoteid, deleted) for efficient lookups
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
     * @param int $page Page number to retrieve (1-based index).
     *                  Must be positive integer; values <= 0 may be normalized
     *                  by repository or paginator implementation.
     * @param int $perPage Number of items to return per page.
     *                     Should be positive integer; typical values range
     *                     from 10 to 100 depending on UI requirements.
     * @param string|null $search Optional search term for filtering quotes by
     *                            subject, quote number, or related account name.
     *                            Uses case-insensitive partial matching. Default
     *                            is null (no search filter applied).
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
     * $useCase = new GetQuoteUseCase($quoteRepository);
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
     * const loadQuotes = async (page, search) => {
     *     const response = await api.get('/quotes', {
     *         params: { page, per_page: 20, search }
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
     * @see QuoteRepositoryInterface::paginate() For the repository implementation
     * @see QuoteResponse For the DTO structure
     * @see LengthAwarePaginator For Laravel pagination structure and methods
     * @see \App\Http\Controllers\Api\QuoteController::index() For HTTP endpoint
     * @see GetAllQuotesUseCase For alternative use case with account filtering support
     */
    public function execute(int $page, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        return $this->quoteRepository->paginate($page, $perPage, $search);
    }
}