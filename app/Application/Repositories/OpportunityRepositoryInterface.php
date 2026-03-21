<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Domain\Entities\Opportunity;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface OpportunityRepositoryInterface
 * 
 * Defines the contract for opportunity (potential) data access operations in the CRM system.
 * 
 * This interface abstracts the data persistence layer for Opportunity entities,
 * allowing for interchangeable implementations (e.g., Vtiger database, external API,
 * in-memory for testing) without affecting the business logic layer that depends
 * on this contract.
 * 
 * The repository pattern provides a clean separation between domain logic and
 * data access logic, promoting testability, maintainability, and adherence to
 * the Dependency Inversion Principle. Opportunities represent potential sales
 * deals in the CRM pipeline and are critical for revenue tracking and forecasting.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see Opportunity For the domain entity this repository manages
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerOpportunityRepository For the Vtiger-specific implementation
 * @see CreateOpportunityRequest For the DTO used in creation operations
 */
interface OpportunityRepositoryInterface
{
    /**
     * Retrieve a paginated list of opportunities with optional search and account filter.
     * 
     * This method supports pagination, text search across opportunity fields, and
     * filtering by related account (client). It returns a Laravel paginator instance
     * containing Opportunity entities for the requested page.
     * 
     * The search functionality performs case-insensitive partial matching against
     * opportunity name (potentialname), description, and related account name.
     * The account filter enables viewing all opportunities for a specific client.
     * 
     * @param int $page The page number to retrieve (1-based index). Default is 1.
     * @param int $perPage The number of items per page. Default is 20.
     * @param string|null $search Optional search term for filtering opportunities
     *                            by name, description, or related account name.
     * @param int|null $accountId Optional account (client) ID to filter opportunities
     *                            by related client. When provided, only opportunities
     *                            linked to this account are returned.
     * 
     * @return LengthAwarePaginator<Opportunity> A paginator instance containing
     *                                           Opportunity entities for the
     *                                           requested page, with metadata
     *                                           about total items and pages.
     * 
     * @throws \InvalidArgumentException If page or perPage parameters are invalid.
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Get first page of all opportunities
     * $paginator = $repository->getAll(1, 20);
     * 
     * @example
     * // Search opportunities by name
     * $paginator = $repository->getAll(1, 20, 'Enterprise Deal');
     * 
     * @example
     * // Get opportunities for a specific client
     * $paginator = $repository->getAll(1, 20, null, 123);
     * 
     * @example
     * // Combine search and account filter
     * $paginator = $repository->getAll(1, 20, 'Q1', 123);
     * 
     * @example
     * // Iterate through results
     * foreach ($paginator->items() as $opportunity) {
     *     echo $opportunity->potentialname;
     *     echo $opportunity->amount;
     * }
     * 
     * @see Opportunity::class For the entity structure returned
     * @see LengthAwarePaginator For Laravel pagination structure
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $accountId = null
    ): LengthAwarePaginator;

    /**
     * Retrieve a single opportunity by its unique identifier.
     * 
     * This method fetches an Opportunity entity by its primary key (potentialid).
     * It includes all opportunity fields plus denormalized data from related
     * tables (account name, assigned user name) for comprehensive display.
     * 
     * The method returns null if no opportunity with the specified ID exists
     * or if the opportunity has been soft-deleted (deleted flag set in crmentity).
     * 
     * @param int $id The unique identifier (potentialid) of the opportunity
     *                to retrieve.
     * 
     * @return Opportunity|null The Opportunity entity if found, or null if
     *                          not found or soft-deleted.
     * 
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Retrieve opportunity by ID
     * $opportunity = $repository->findById(456);
     * 
     * @example
     * // Handle not found case
     * if ($opportunity === null) {
     *     throw new OpportunityNotFoundException(456);
     * }
     * 
     * @example
     * // Display opportunity details
     * echo "Deal: {$opportunity->potentialname}";
     * echo "Amount: {$opportunity->amount}";
     * echo "Stage: {$opportunity->sales_stage}";
     * 
     * @see Opportunity::class For the entity structure returned
     * @see OpportunityNotFoundException For custom exception handling
     */
    public function findById(int $id): ?Opportunity;

    /**
     * Create a new opportunity record in the database.
     * 
     * This method persists a new opportunity entity using data from the provided
     * request DTO. It handles the creation of records across multiple related
     * tables (vtiger_potential, vtiger_crmentity) within a database transaction
     * to ensure data consistency.
     * 
     * The method automatically generates required fields such as opportunity number,
     * timestamps, and audit information. It returns the newly created opportunity's
     * ID for subsequent operations or redirection.
     * 
     * @param CreateOpportunityRequest $request The validated request DTO containing
     *                                          opportunity data for creation.
     * @param int $createdByUserId The ID of the user performing the creation
     *                             operation, used for audit tracking (smcreatorid,
     *                             smownerid fields).
     * 
     * @return int The unique identifier (potentialid) of the newly created
     *             opportunity.
     * 
     * @throws \InvalidArgumentException If required fields are missing or invalid.
     * @throws \RuntimeException If the database transaction fails.
     * @throws \Exception If any error occurs during the creation process.
     * 
     * @example
     * // Create new opportunity
     * $request = new CreateOpportunityRequest(
     *     potentialname: 'Enterprise Deal Q1',
     *     amount: 50000,
     *     sales_stage: 'Prospecting',
     *     related_to: 123
     * );
     * $newId = $repository->create($request, $currentUserId);
     * 
     * @example
     * // Redirect to new opportunity detail page
     * return redirect()->route('opportunities.show', $newId);
     * 
     * @example
     * // Return ID for frontend reference
     * return response()->json(['opportunity_id' => $newId]);
     * 
     * @see CreateOpportunityRequest For the expected input structure
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function create(CreateOpportunityRequest $request, int $createdByUserId): int;

    /**
     * Update an existing opportunity record in the database.
     * 
     * This method updates an opportunity entity using data from the provided
     * array. It handles updates across multiple related tables within a database
     * transaction to ensure data consistency.
     * 
     * The method updates audit information (modifiedtime, modifiedby) automatically
     * and preserves fields not included in the update data. It returns a boolean
     * indicating whether the update was successful.
     * 
     * @param int $id The unique identifier (potentialid) of the opportunity
     *                to update.
     * @param array<string, mixed> $data Associative array containing the fields
     *                                    to update with their new values. Only
     *                                    provided fields will be updated.
     * @param int $modifiedByUserId The ID of the user performing the update
     *                              operation, used for audit tracking (modifiedby
     *                              field).
     * 
     * @return bool True if the update was successful, false if the opportunity
     *              was not found or the update failed.
     * 
     * @throws \InvalidArgumentException If the opportunity ID is invalid or
     *                                   required fields are missing.
     * @throws \RuntimeException If the database transaction fails.
     * @throws \Exception If any error occurs during the update process.
     * 
     * @example
     * // Update opportunity stage
     * $success = $repository->update(
     *     id: 456,
     *     data: ['sales_stage' => 'Proposal'],
     *     modifiedByUserId: $currentUserId
     * );
     * 
     * @example
     * // Update multiple fields
     * $success = $repository->update(456, [
     *     'amount' => 75000,
     *     'sales_stage' => 'Negotiation',
     *     'closingdate' => '2026-06-30',
     *     'probability' => 75
     * ], $currentUserId);
     * 
     * @example
     * // Handle update result
     * if ($success) {
     *     toast()->success('Opportunity updated successfully');
     * }
     * 
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function update(int $id, array $data, int $modifiedByUserId): bool;

    /**
     * Soft delete an opportunity record by marking it as deleted.
     * 
     * This method performs a soft delete by updating the deleted flag in the
     * vtiger_crmentity table rather than removing the record from the database.
     * This preserves audit history and allows for potential restoration.
     * 
     * After deletion, the opportunity will no longer appear in normal queries
     * unless explicitly requested with deleted records included. The method
     * also tracks which user performed the deletion for audit purposes.
     * 
     * @param int $id The unique identifier (potentialid) of the opportunity
     *                to delete.
     * @param int $deletedByUserId The ID of the user performing the deletion
     *                             operation, used for audit tracking.
     * 
     * @return bool True if the deletion was successful, false if the opportunity
     *              was not found or already deleted.
     * 
     * @throws \RuntimeException If the database update fails.
     * 
     * @example
     * // Delete opportunity
     * $success = $repository->delete(456, $currentUserId);
     * 
     * @example
     * // Confirm deletion to user
     * if ($success) {
     *     return response()->json(['message' => 'Opportunity deleted']);
     * }
     * 
     * @example
     * // Handle deletion in frontend
     * const handleDelete = async (id) => {
     *     const confirmed = await confirm('Delete this opportunity?');
     *     if (confirmed) {
     *         await api.delete(`/opportunities/${id}`);
     *     }
     * };
     * 
     * @see Opportunity::isActive For checking if an opportunity is soft-deleted
     */
    public function delete(int $id, int $deletedByUserId): bool;

    /**
     * Retrieve all available sales stages for opportunities.
     * 
     * This method fetches the configured sales pipeline stages from the CRM
     * system. These stages represent the progression of an opportunity through
     * the sales process, from initial contact to closed deal.
     * 
     * The stages are typically configured in the CRM administration panel and
     * may be customized per organization. This method returns them in the order
     * they should appear in the sales pipeline.
     * 
     * @return array<int, string> An indexed array of stage names in the order
     *                            they should appear in the sales pipeline.
     *                            Common values include: "Prospecting",
     *                            "Qualification", "Proposal", "Negotiation",
     *                            "Closed Won", "Closed Lost".
     * 
     * @throws \RuntimeException If the stage configuration cannot be retrieved.
     * 
     * @example
     * // Get stages for dropdown in form
     * $stages = $repository->getAvailableStages();
     * 
     * @example
     * // Populate select element
     * <select name="sales_stage">
     *     <?php foreach ($stages as $stage): ?>
     *         <option value="<?= $stage ?>"><?= $stage ?></option>
     *     <?php endforeach; ?>
     * </select>
     * 
     * @example
     * // Response structure
     * // Returns: ["Prospecting", "Qualification", "Proposal", "Negotiation", "Closed Won", "Closed Lost"]
     * 
     * @see \App\Http\Controllers\Api\OpportunityController For typical usage in forms
     */
    public function getAvailableStages(): array;

    /**
     * Perform a global search for opportunities with ranking and limits.
     * 
     * This method provides advanced search functionality with relevance ranking,
     * result limiting, and matching across multiple opportunity fields. It is
     * designed for global search features that aggregate results from multiple
     * entity types.
     * 
     * The search supports partial matching and returns results ordered by
     * relevance. Each result includes a type identifier for frontend routing
     * and display purposes.
     * 
     * @param string $query The search query string. Supports partial matching
     *                      across potentialname, description, account name,
     *                      and other fields.
     * @param int $limit The maximum number of results to return. Default is 10.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing opportunity information
     *                                          with a 'type' field set to
     *                                          'opportunity' for frontend
     *                                          identification.
     * 
     * @throws \InvalidArgumentException If the query is empty or limit is invalid.
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Global search with limit
     * $results = $repository->search('enterprise deal', 5);
     * 
     * @example
     * // Format for global search response
     * return [
     *     'opportunities' => $results,
     *     'total' => count($results)
     * ];
     * 
     * @example
     * // Response structure
     * // Returns: [
     * //     ['id' => 456, 'name' => 'Enterprise Deal', 'type' => 'opportunity', ...],
     * //     ...
     * // ]
     * 
     * @see \App\Application\UseCases\GlobalSearchUseCase For the use case that
     *                                                    aggregates results from
     *                                                    multiple repositories
     */
    public function search(string $query, int $limit): array;

    /**
     * Count the number of opportunities related to a specific client.
     * 
     * This method performs an efficient COUNT query to determine how many
     * opportunities are linked to a specific account (client). It is optimized
     * for performance and is typically used for displaying summary statistics
     * on client detail pages.
     * 
     * The count excludes soft-deleted opportunities and only includes active
     * records in the CRM system.
     * 
     * @param int $clientId The unique identifier (accountid) of the client
     *                      for which to count opportunities.
     * 
     * @return int The number of active opportunities related to the specified
     *             client. Returns 0 if the client has no opportunities or
     *             if the client does not exist.
     * 
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Get opportunity count for client dashboard
     * $count = $repository->countByClient(123);
     * 
     * @example
     * // Display in frontend
     * echo "Opportunities: {$count}";
     * 
     * @example
     * // Use in summary card
     * <div class="stat-card">
     *     <span class="label">Opportunities</span>
     *     <span class="value"><?= $count ?></span>
     * </div>
     * 
     * @see \App\Application\UseCases\GetClientSummaryUseCase For typical usage
     *                                                          in client summaries
     */
    public function countByClient(int $clientId): int;
}