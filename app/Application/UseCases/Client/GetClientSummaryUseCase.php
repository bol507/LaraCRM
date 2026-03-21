<?php

namespace App\Application\UseCases\Client;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\ClientSummary;

/**
 * Class GetClientSummaryUseCase
 * 
 * Use case for retrieving aggregated summary statistics about a specific client.
 * 
 * This use case orchestrates the retrieval of client summary data by delegating
 * to the ClientRepositoryInterface implementation. The summary includes counts
 * of related entities (opportunities, quotes, projects, contacts) and the most
 * recent activity timestamp across all related records.
 * 
 * Key characteristics:
 * - Single responsibility: Focuses solely on retrieving client summary data
 * - Delegation pattern: Relies on repository for data access logic
 * - Immutable result: Returns ClientSummary entity with readonly properties
 * - Dependency injection: Repository injected via constructor for testability
 * 
 * Summary data includes:
 * - opportunitiesCount: Number of active opportunities linked to the client
 * - quotesCount: Number of active quotes linked to the client
 * - projectsCount: Number of active projects linked to the client
 * - contactsCount: Number of active contacts linked to the client
 * - lastActivity: Most recent modification timestamp across all related entities
 * 
 * Use cases:
 * - Client detail page dashboard widgets
 * - Client list view summary cards
 * - Reporting and analytics dashboards
 * - Client health scoring and engagement metrics
 * 
 * @package App\Application\UseCases\Client
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see ClientRepositoryInterface::getSummary() For the data access implementation
 * @see ClientSummary For the entity returned by this use case
 * @see \App\Http\Controllers\Api\ClientController For HTTP endpoint integration
 */
class GetClientSummaryUseCase
{
    
    /**
     * GetClientSummaryUseCase constructor.
     * 
     * Injects the ClientRepositoryInterface dependency via constructor injection.
     * This enables dependency inversion, facilitating unit testing with mock
     * repository implementations and promoting loose coupling between the use
     * case and specific data access implementations.
     * 
     * @param ClientRepositoryInterface $clientRepository The client repository
     *                                                    instance for data access
     * 
     * @return void
     */
    public function __construct(
        protected ClientRepositoryInterface $clientRepository
    ) {}

    /**
     * Execute the use case to retrieve summary statistics for a specific client.
     * 
     * This method delegates to the client repository's getSummary() method to
     * retrieve aggregated statistics about the specified client. The summary
     * includes counts of related entities and the most recent activity timestamp.
     * 
     * Summary calculation details:
     * - opportunitiesCount: COUNT of vtiger_potential records where related_to = clientId
     * - quotesCount: COUNT of vtiger_quotes records where accountid = clientId
     * - projectsCount: COUNT of vtiger_project records where linktoaccountscontacts = clientId
     * - contactsCount: COUNT of vtiger_contactdetails records where accountid = clientId
     * - lastActivity: MAX of modifiedtime across all related entity tables, or null if none
     * 
     * All counts exclude soft-deleted records (vtiger_crmentity.deleted = 0).
     * 
     * Error handling:
     * - If client with specified ID does not exist, repository should throw
     *   InvalidArgumentException which propagates to caller
     * - Database query failures propagate as RuntimeException to caller
     * - No caching performed at use case layer; consider application-level
     *   caching if summary is frequently accessed for same client
     * 
     * Performance considerations:
     * - Repository implementation should use efficient COUNT queries with
     *   proper indexing on foreign key fields (related_to, accountid, etc.)
     * - Last activity calculation may involve multiple subqueries; consider
     *   materialized view or caching for high-traffic scenarios
     * - Summary data is typically accessed once per client detail view load;
     *   consider eager loading if multiple summaries fetched in batch operations
     * 
     * @param int $clientId The unique identifier (accountid) of the client
     *                      for which to retrieve summary statistics.
     * 
     * @return ClientSummary Entity containing aggregated counts of related
     *                       entities and most recent activity timestamp.
     * 
     * @throws \InvalidArgumentException If client with specified ID does not
     *                                   exist or ID is invalid (<= 0)
     * @throws \RuntimeException If database query fails or connection is lost
     *                           during summary calculation
     * 
     * @example
     * // Retrieve summary for client detail page
     * $useCase = new GetClientSummaryUseCase($clientRepository);
     * $summary = $useCase->execute(123);
     * 
     * @example
     * // Access summary data in controller
     * return response()->json([
     *     'clientId' => $summary->clientId,
     *     'opportunities' => $summary->opportunitiesCount,
     *     'quotes' => $summary->quotesCount,
     *     'projects' => $summary->projectsCount,
     *     'contacts' => $summary->contactsCount,
     *     'lastActivity' => $summary->lastActivity
     * ]);
     * 
     * @example
     * // Frontend usage pattern
     * const loadClientSummary = async (clientId) => {
     *     const response = await api.get(`/clients/${clientId}/summary`);
     *     updateDashboardWidgets(response.data);
     * };
     * 
     * @example
     * // Response structure from ClientSummary::toArray()
     * // Returns:
     * // {
     * //     "clientId": 123,
     * //     "opportunitiesCount": 5,
     * //     "quotesCount": 12,
     * //     "projectsCount": 3,
     * //     "contactsCount": 8,
     * //     "lastActivity": "2026-03-18 14:30:00"
     * // }
     * 
     * @see ClientRepositoryInterface::getSummary() For the repository implementation
     * @see ClientSummary For the entity structure and available properties
     * @see \App\Domain\Entities\ClientSummary::toArray() For JSON serialization
     */
    public function execute(int $clientId): ClientSummary
    {
        return $this->clientRepository->getSummary($clientId);
    }
}