<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateClientRequest;
use App\Application\DTOs\UpdateClientRequest;
use App\Domain\Entities\Client;
use App\Domain\Entities\ClientSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface ClientRepositoryInterface
 * 
 * Defines the contract for client data access operations in the CRM system.
 * 
 * This interface abstracts the data persistence layer for Client entities,
 * allowing for interchangeable implementations (e.g., Vtiger database,
 * external API, in-memory for testing) without affecting the business logic
 * layer that depends on this contract.
 * 
 * The repository pattern provides a clean separation between domain logic
 * and data access logic, promoting testability, maintainability, and
 * adherence to the Dependency Inversion Principle.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see Client For the domain entity this repository manages
 * @see ClientSummary For the summary entity returned by getSummary()
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerClientRepository For the Vtiger-specific implementation
 */
interface ClientRepositoryInterface
{
    /**
     * Retrieve a paginated list of clients with optional search and filters.
     * 
     * This method supports pagination, text search across multiple client fields,
     * and additional filtering criteria. It returns a Laravel paginator instance
     * containing Client entities for the requested page.
     * 
     * The search functionality performs case-insensitive partial matching against
     * client name, account number, email, and phone fields. Filters are applied
     * as additional WHERE clauses based on the provided key-value pairs.
     * 
     * @param int $page The page number to retrieve (1-based index). Default is 1.
     * @param int $perPage The number of items per page. Default is 20.
     * @param string|null $search Optional search term for filtering clients by
     *                            name, account number, email, or phone.
     * @param array<string, mixed>|null $filters Optional associative array of
     *                                            additional filter criteria where
     *                                            keys are field names and values
     *                                            are the desired filter values.
     * 
     * @return LengthAwarePaginator<Client> A paginator instance containing Client
     *                                      entities for the requested page, with
     *                                      metadata about total items and pages.
     * 
     * @throws \InvalidArgumentException If page or perPage parameters are invalid.
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Get first page of clients with search term
     * $paginator = $repository->getAll(1, 20, 'Acme');
     * 
     * @example
     * // Get clients filtered by industry
     * $paginator = $repository->getAll(1, 20, null, ['industry' => 'Technology']);
     * 
     * @example
     * // Iterate through results
     * foreach ($paginator->items() as $client) {
     *     echo $client->accountname;
     * }
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?array $filters = null
    ): LengthAwarePaginator;

    /**
     * Retrieve a single client by its unique identifier.
     * 
     * This method fetches a Client entity by its primary key (accountid).
     * It includes all client fields plus denormalized data from related
     * tables (addresses, audit information) for comprehensive display.
     * 
     * The method returns null if no client with the specified ID exists
     * or if the client has been soft-deleted (deleted flag set in crmentity).
     * 
     * @param int $id The unique identifier (accountid) of the client to retrieve.
     * 
     * @return Client|null The Client entity if found, or null if not found.
     * 
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Retrieve client by ID
     * $client = $repository->findById(123);
     * 
     * @example
     * // Handle not found case
     * if ($client === null) {
     *     throw new ClientNotFoundException(123);
     * }
     * 
     * @see Client::class For the entity structure returned
     */
    public function findById(int $id): ?Client;

    /**
     * Create a new client record in the database.
     * 
     * This method persists a new client entity using data from the provided
     * request DTO. It handles the creation of records across multiple related
     * tables (vtiger_account, vtiger_crmentity, address tables) within a
     * database transaction to ensure data consistency.
     * 
     * The method automatically generates required fields such as account number,
     * timestamps, and audit information. It returns the newly created client's ID
     * for subsequent operations or redirection.
     * 
     * @param CreateClientRequest $request The validated request DTO containing
     *                                     client data for creation.
     * @param int $userId The ID of the user performing the creation operation,
     *                    used for audit tracking (smcreatorid, smownerid).
     * 
     * @return int The unique identifier (accountid) of the newly created client.
     * 
     * @throws \InvalidArgumentException If required fields are missing or invalid.
     * @throws \RuntimeException If the database transaction fails.
     * @throws \Exception If any error occurs during the creation process.
     * 
     * @example
     * // Create new client
     * $request = new CreateClientRequest(accountname: 'Acme Corp', ...);
     * $newId = $repository->create($request, $currentUserId);
     * 
     * @example
     * // Redirect to new client detail page
     * return redirect()->route('clients.show', $newId);
     * 
     * @see CreateClientRequest For the expected input structure
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function create(CreateClientRequest $request, int $userId): int;

    /**
     * Update an existing client record in the database.
     * 
     * This method updates a client entity using data from the provided request
     * DTO. It handles updates across multiple related tables within a database
     * transaction to ensure data consistency.
     * 
     * The method updates audit information (modifiedtime, modifiedby) automatically
     * and preserves fields not included in the update request. It returns a boolean
     * indicating whether the update was successful.
     * 
     * @param UpdateClientRequest $request The validated request DTO containing
     *                                     updated client data.
     * @param int $userId The ID of the user performing the update operation,
     *                    used for audit tracking (modifiedby field).
     * 
     * @return bool True if the update was successful, false if the client
     *              was not found or the update failed.
     * 
     * @throws \InvalidArgumentException If the client ID is invalid or
     *                                   required fields are missing.
     * @throws \RuntimeException If the database transaction fails.
     * @throws \Exception If any error occurs during the update process.
     * 
     * @example
     * // Update existing client
     * $request = new UpdateClientRequest(accountname: 'Acme Corp Updated', ...);
     * $success = $repository->update($request, $currentUserId);
     * 
     * @example
     * // Handle update result
     * if ($success) {
     *     toast()->success('Client updated successfully');
     * }
     * 
     * @see UpdateClientRequest For the expected input structure
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function update(UpdateClientRequest $request, int $userId): bool;

    /**
     * Soft delete a client record by marking it as deleted.
     * 
     * This method performs a soft delete by updating the deleted flag in the
     * vtiger_crmentity table rather than removing the record from the database.
     * This preserves audit history and allows for potential restoration.
     * 
     * After deletion, the client will no longer appear in normal queries unless
     * explicitly requested with deleted records included. Related entities
     * (opportunities, quotes, etc.) are not automatically deleted.
     * 
     * @param int $id The unique identifier (accountid) of the client to delete.
     * 
     * @return bool True if the deletion was successful, false if the client
     *              was not found or already deleted.
     * 
     * @throws \RuntimeException If the database update fails.
     * 
     * @example
     * // Delete client
     * $success = $repository->delete(123);
     * 
     * @example
     * // Confirm deletion to user
     * if ($success) {
     *     return response()->json(['message' => 'Client deleted']);
     * }
     * 
     * @see Client::isActive For checking if a client is soft-deleted
     */
    public function delete(int $id): bool;

    /**
     * Find a client by its account name (exact match).
     * 
     * This method searches for a client with an exact match on the accountname
     * field. It is useful for duplicate checking during client creation or
     * for quick lookups when the exact name is known.
     * 
     * The method returns an associative array with basic client information
     * rather than a full Client entity, optimizing for performance when only
     * identification is needed.
     * 
     * @param string $accountName The exact account name to search for.
     * 
     * @return array<string, mixed>|null An associative array containing basic
     *                                   client information if found, or null
     *                                   if no match exists.
     * 
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Check for duplicate before creation
     * $existing = $repository->findByAccountName('Acme Corporation');
     * if ($existing !== null) {
     *     throw new DuplicateClientException('Client already exists');
     * }
     * 
     * @example
     * // Return minimal data for autocomplete
     * return response()->json($existing ?? []);
     */
    public function findByAccountName(string $accountName): ?array;

    /**
     * Find clients by name or email address (partial match).
     * 
     * This method performs a case-insensitive search across the accountname
     * and email1 fields for partial matches. It is designed for autocomplete
     * functionality and quick search operations where the user may not know
     * the exact client identifier.
     * 
     * The method returns an array of associative arrays with minimal client
     * information suitable for display in dropdown menus or search results.
     * Results are limited to a reasonable number for performance.
     * 
     * @param string $searchTerm The search term to match against client names
     *                           and email addresses. Supports partial matching.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing basic client information
     *                                          for each matching client.
     * 
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Search for clients matching "acme"
     * $results = $repository->findByNameOrEmail('acme');
     * 
     * @example
     * // Format for autocomplete dropdown
     * $options = array_map(fn($c) => [
     *     'value' => $c['accountid'],
     *     'label' => $c['accountname']
     * ], $results);
     * 
     * @see search() For more advanced search with ranking and limits
     */
    public function findByNameOrEmail(string $searchTerm): array;

    /**
     * Perform a global search for clients with ranking and limits.
     * 
     * This method provides advanced search functionality with relevance ranking,
     * result limiting, and matching across multiple client fields. It is designed
     * for global search features that aggregate results from multiple entity types.
     * 
     * The search supports partial matching and returns results ordered by
     * relevance. Each result includes a type identifier for frontend routing
     * and display purposes.
     * 
     * @param string $query The search query string. Supports partial matching
     *                      across accountname, email, phone, and other fields.
     * @param int $limit The maximum number of results to return. Default is 10.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing client information
     *                                          with a 'type' field set to 'client'
     *                                          for frontend identification.
     * 
     * @throws \InvalidArgumentException If the query is empty or limit is invalid.
     * @throws \RuntimeException If the database query fails.
     * 
     * @example
     * // Global search with limit
     * $results = $repository->search('acme', 5);
     * 
     * @example
     * // Format for global search response
     * return [
     *     'clients' => $results,
     *     'total' => count($results)
     * ];
     * 
     * @see \App\Application\UseCases\GlobalSearchUseCase For the use case that
     *                                                    aggregates results from
     *                                                    multiple repositories
     */
    public function search(string $query, int $limit): array;

    /**
     * Retrieve a summary of related entities for a client.
     * 
     * This method aggregates counts of related entities (opportunities, quotes,
     * projects, contacts) associated with a specific client. It is optimized
     * for performance using efficient counting queries and returns a dedicated
     * summary entity.
     * 
     * The summary includes optional last activity timestamp derived from the
     * most recently modified related entity, providing context for client
     * engagement without requiring detailed entity loading.
     * 
     * @param int $clientId The unique identifier (accountid) of the client
     *                      for which to retrieve the summary.
     * 
     * @return ClientSummary A summary entity containing counts of related
     *                       entities and optional last activity timestamp.
     * 
     * @throws \InvalidArgumentException If the client ID is invalid or
     *                                   the client does not exist.
     * @throws \RuntimeException If the database queries fail.
     * 
     * @example
     * // Get summary for client dashboard
     * $summary = $repository->getSummary(123);
     * 
     * @example
     * // Display summary in frontend
     * echo "Opportunities: {$summary->opportunitiesCount}";
     * echo "Last activity: {$summary->lastActivity}";
     * 
     * @see ClientSummary For the structure of the returned summary entity
     */
    public function getSummary(int $clientId): ClientSummary;
}