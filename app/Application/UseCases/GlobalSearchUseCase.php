<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\Repositories\ProjectRepositoryInterface;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Application\Repositories\TaskRepositoryInterface;

/**
 * Class GlobalSearchUseCase
 * 
 * Use case for executing global search queries across multiple entity types in the CRM system.
 * 
 * This use case orchestrates search operations across projects, clients, opportunities,
 * quotes, tasks, and contacts by delegating to their respective repository implementations.
 * It aggregates results from all entity types into a unified response structure suitable
 * for global search bar functionality in the frontend application.
 * 
 * Key characteristics:
 * - Multi-entity search: Executes parallel searches across six entity types
 * - Uniform interface: All repositories implement search(string, int): array contract
 * - Result aggregation: Combines results with metadata (total count, original query)
 * - Limit enforcement: Each entity type respects the provided limit independently
 * - Dependency injection: Repositories injected via constructor for testability
 * 
 * Search behavior:
 * - Each repository implements its own search logic and relevance ranking
 * - Results from different entity types are not merged or re-ranked across types
 * - Total count represents sum of results across all entity types, not unique entities
 * - Empty results for an entity type are included as empty arrays in response
 * 
 * @package App\Application\UseCases
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see ProjectRepositoryInterface::search() For project search implementation
 * @see ClientRepositoryInterface::search() For client search implementation
 * @see OpportunityRepositoryInterface::search() For opportunity search implementation
 * @see QuoteRepositoryInterface::search() For quote search implementation
 * @see TaskRepositoryInterface::search() For task search implementation
 * @see ContactRepositoryInterface::search() For contact search implementation
 */
class GlobalSearchUseCase
{
    

    /**
     * GlobalSearchUseCase constructor.
     * 
     * Injects all entity repository dependencies via constructor injection.
     * This enables dependency inversion, facilitating unit testing with mock
     * repositories and promoting loose coupling between the use case and
     * specific data access implementations.
     * 
     * @param ProjectRepositoryInterface $projectRepository Repository for project searches
     * @param ClientRepositoryInterface $clientRepository Repository for client searches
     * @param OpportunityRepositoryInterface $opportunityRepository Repository for opportunity searches
     * @param QuoteRepositoryInterface $quoteRepository Repository for quote searches
     * @param TaskRepositoryInterface $taskRepository Repository for task searches
     * @param ContactRepositoryInterface $contactRepository Repository for contact searches
     * 
     * @return void
     */
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private ClientRepositoryInterface $clientRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private QuoteRepositoryInterface $quoteRepository,
        private TaskRepositoryInterface $taskRepository,
        private ContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * Execute a global search query across all supported entity types.
     * 
     * This method performs parallel search operations across projects, clients,
     * opportunities, quotes, tasks, and contacts by delegating to their respective
     * repository implementations. Results are aggregated into a unified response
     * structure with metadata for frontend consumption.
     * 
     * Search execution:
     * - Each repository's search() method is called with the same query and limit
     * - Repositories implement their own relevance ranking and field matching logic
     * - Results are not re-ranked or merged across entity types; each type maintains
     *   its own ordering and limit
     * - Empty result sets are included in response to enable consistent frontend handling
     * 
     * Response structure:
     * - 'results': Associative array with entity type as key and array of results as value
     * - 'total': Integer sum of result counts across all entity types
     * - 'query': The original search query string for display or logging purposes
     * 
     * Each result item in entity arrays should include:
     * - 'id': Unique identifier for the entity
     * - 'type': Entity type discriminator (e.g., 'project', 'client', 'opportunity')
     * - 'title': Primary display name or identifier for the entity
     * - 'url': Pre-built route path for navigation to entity detail view
     * - Additional fields as defined by each repository's search implementation
     * 
     * Performance considerations:
     * - Six separate database queries executed sequentially; consider parallel execution
     *   or caching strategies for high-traffic global search scenarios
     * - Each repository should implement efficient indexing and query optimization
     * - Limit parameter applies per entity type; total results may exceed limit * 6
     * 
     * Error handling:
     * - Exceptions from individual repository searches propagate to caller
     * - No fallback or partial result handling; all searches must succeed or call fails
     * - Consider implementing try-catch per repository if graceful degradation is desired
     * 
     * @param string $query The search query string to match against entity fields.
     *                      Repositories implement their own matching logic (typically
     *                      case-insensitive partial matching with LIKE wildcards).
     * @param int $limit Maximum number of results to return per entity type.
     *                   Default is 10. Each entity type respects this limit
     *                   independently; total results may be up to limit * 6.
     * 
     * @return array<string, mixed> Associative array containing aggregated search results:
     *                              - 'results': array<string, array> Entity-type-keyed arrays
     *                                of search result items, each item containing id, type,
     *                                title, url, and entity-specific fields
     *                              - 'total': int Sum of result counts across all entity types
     *                              - 'query': string The original search query for reference
     * 
     * @throws \RuntimeException If any repository search operation fails due to
     *                           database connection issues or query errors
     * @throws \InvalidArgumentException If query is empty or limit is invalid
     *                                   (validation typically performed at controller layer)
     * 
     * @example
     * // Search for "acme" with default limit
     * $useCase = new GlobalSearchUseCase($projectRepo, $clientRepo, ...);
     * $results = $useCase->execute('acme');
     * 
     * @example
     * // Response structure
     * // Returns:
     * // [
     * //     'results' => [
     * //         'projects' => [
     * //             ['id' => 1, 'type' => 'project', 'title' => 'Acme Website', 'url' => '/projects/1', ...],
     * //             ...
     * //         ],
     * //         'clients' => [
     * //             ['id' => 5, 'type' => 'client', 'title' => 'Acme Corporation', 'url' => '/clients/5', ...],
     * //             ...
     * //         ],
     * //         'opportunities' => [...],
     * //         'quotes' => [...],
     * //         'tasks' => [...],
     * //         'contacts' => [...]
     * //     ],
     * //     'total' => 12,
     * //     'query' => 'acme'
     * // ]
     * 
     * @example
     * // Frontend usage pattern
     * const handleGlobalSearch = async (query) => {
     *     const response = await api.get(`/search?query=${query}&limit=5`);
     *     displayResults(response.data.results);
     *     updateTotalCount(response.data.total);
     * };
     * 
     * @see \App\Http\Controllers\Api\GlobalSearchController For HTTP endpoint integration
     * @see ProjectRepositoryInterface::search() For project-specific search logic
     * @see ClientRepositoryInterface::search() For client-specific search logic
     */
    public function execute(string $query, int $limit = 10): array
    {
        
        $projects = $this->projectRepository->search($query, $limit);
        $clients = $this->clientRepository->search($query, $limit);
        $opportunities = $this->opportunityRepository->search($query, $limit);
        $quotes = $this->quoteRepository->search($query, $limit);
        $tasks = $this->taskRepository->search($query, $limit);
        $contacts = $this->contactRepository->search($query, $limit);

        $results = [
            'projects' => $projects,
            'clients' => $clients,
            'opportunities' => $opportunities,
            'quotes' => $quotes,
            'tasks' => $tasks,
            'contacts' => $contacts,
        ];

        
        $total = array_sum(array_map(fn($r) => count($r), $results));

        return [
            'results' => $results,
            'total' => $total,
            'query' => $query,
        ];
    }
}