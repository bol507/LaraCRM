<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Project;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface ProjectRepositoryInterface
 * 
 * Defines the contract for project data access operations in the CRM system.
 * 
 * This interface abstracts the data persistence layer for Project entities,
 * allowing for interchangeable implementations (e.g., Vtiger database, external
 * API, in-memory for testing) without affecting the business logic layer that
 * depends on this contract.
 * 
 * The repository pattern provides a clean separation between domain logic and
 * data access logic, promoting testability, maintainability, and adherence to
 * the Dependency Inversion Principle. Projects represent work engagements or
 * contracts with clients and are critical for project management and tracking.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see Project For the domain entity this repository manages
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerProjectRepository For the Vtiger-specific implementation
 */
interface ProjectRepositoryInterface
{
    /**
     * Retrieve a paginated list of projects with optional search and status filter.
     * 
     * This method supports pagination, text search across project fields, and
     * filtering by project status. It returns a Laravel paginator instance
     * containing Project entities for the requested page.
     * 
     * The search functionality performs case-insensitive partial matching against
     * project name, description, and related client name. The status filter enables
     * viewing projects by their current state (e.g., "planned", "in_progress",
     * "completed", "on_hold", "cancelled").
     * 
     * @param int $page The page number to retrieve (1-based index).
     * @param int $limit The number of items per page.
     * @param string|null $searchTerm Optional search term for filtering projects
     *                                by name, description, or related client name.
     * @param string|null $status Optional status filter to retrieve only projects
     *                            with the specified status. Common values include:
     *                            "planned", "in_progress", "completed", "on_hold",
     *                            "cancelled". If null, projects of all statuses
     *                            are returned.
     * @param int|null $accountId Optional account ID to filter projects by related client.
     * @return LengthAwarePaginator<Project> A paginator instance containing Project
     *                                       entities for the requested page, with
     *                                       metadata about total items and pages.
     * 
     * @throws \InvalidArgumentException If page or limit parameters are invalid
     *                                   (e.g., negative numbers or zero).
     * @throws \RuntimeException If the database query fails or connection is lost.
     * 
     * @example
     * // Get first page of all projects
     * $paginator = $repository->getAll(1, 20, null, null);
     * 
     * @example
     * // Search projects by name
     * $paginator = $repository->getAll(1, 20, 'Website Redesign', null);
     * 
     * @example
     * // Get only in-progress projects
     * $paginator = $repository->getAll(1, 20, null, 'in_progress');
     * 
     * @example
     * // Combine search and status filter
     * $paginator = $repository->getAll(1, 20, 'Mobile App', 'completed');
     * 
     * @example
     * // Iterate through results
     * foreach ($paginator->items() as $project) {
     *     echo $project->projectname;
     *     echo $project->status;
     *     echo $project->enddate;
     * }
     * 
     * @see Project::class For the entity structure returned
     * @see LengthAwarePaginator For Laravel pagination structure
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        ?int $accountId = null,
        ?string $sortBy = null,
        ?string $sortOrder = null
        
    ): LengthAwarePaginator;

    /**
     * Retrieve a single project by its unique identifier.
     * 
     * This method fetches a Project entity by its primary key (projectid).
     * It includes all project fields plus denormalized data from related
     * tables (client name, assigned user name, milestone information) for
     * comprehensive display.
     * 
     * The method returns null if no project with the specified ID exists
     * or if the project has been soft-deleted (deleted flag set in crmentity).
     * 
     * @param int $projectId The unique identifier (projectid) of the project
     *                       to retrieve.
     * 
     * @return Project|null The Project entity if found, or null if not found
     *                      or soft-deleted.
     * 
     * @throws \RuntimeException If the database query fails or connection
     *                           is lost.
     * 
     * @example
     * // Retrieve project by ID
     * $project = $repository->findById(789);
     * 
     * @example
     * // Handle not found case
     * if ($project === null) {
     *     throw new ProjectNotFoundException(789);
     * }
     * 
     * @example
     * // Display project details
     * echo "Project: {$project->projectname}";
     * echo "Status: {$project->status}";
     * echo "Client: {$project->client_name}";
     * echo "Budget: {$project->budget}";
     * 
     * @example
     * // Check project dates
     * if ($project->startdate && $project->enddate) {
     *     $duration = strtotime($project->enddate) - strtotime($project->startdate);
     *     echo "Duration: " . floor($duration / 86400) . " days";
     * }
     * 
     * @see Project::class For the entity structure returned
     * @see ProjectNotFoundException For custom exception handling
     */
    public function findById(int $projectId): ?Project;

    /**
     * Create a new project record in the database.
     * 
     * This method persists a new project entity using data from the provided
     * array. It handles the creation of records across multiple related
     * tables (vtiger_project, vtiger_crmentity, and potentially related
     * milestone or task tables) within a database transaction to ensure
     * data consistency.
     * 
     * The method automatically generates required fields such as project
     * number, timestamps, and audit information. It returns the newly
     * created project's ID for subsequent operations or redirection.
     * 
     * @param array<string, mixed> $data Associative array containing the
     *                                    project data for creation. Expected
     *                                    keys include: projectname, description,
     *                                    startdate, enddate, status, budget,
     *                                    related_to (client ID), assigned_user_id.
     * 
     * @return int The unique identifier (projectid) of the newly created
     *             project.
     * 
     * @throws \InvalidArgumentException If required fields are missing or
     *                                   invalid (e.g., invalid dates,
     *                                   negative budget).
     * @throws \RuntimeException If the database transaction fails or
     *                           connection is lost.
     * @throws \Exception If any error occurs during the creation process.
     * 
     * @example
     * // Create new project
     * $newId = $repository->create([
     *     'projectname' => 'Website Redesign',
     *     'description' => 'Complete website overhaul for Acme Corp',
     *     'startdate' => '2026-01-15',
     *     'enddate' => '2026-06-30',
     *     'status' => 'planned',
     *     'budget' => 50000,
     *     'related_to' => 123,
     *     'assigned_user_id' => 5
     * ]);
     * 
     * @example
     * // Redirect to new project detail page
     * return redirect()->route('projects.show', $newId);
     * 
     * @example
     * // Return ID for frontend reference
     * return response()->json(['project_id' => $newId]);
     * 
     * @example
     * // Handle creation errors
     * try {
     *     $newId = $repository->create($data);
     * } catch (InvalidArgumentException $e) {
     *     // Handle validation error
     *     logger()->error('Invalid project data', ['error' => $e->getMessage()]);
     * }
     * 
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function create(array $data): int;

    /**
     * Update an existing project record in the database.
     * 
     * This method updates a project entity using data from the provided
     * array. It handles updates across multiple related tables within a
     * database transaction to ensure data consistency.
     * 
     * The method updates audit information (modifiedtime, modifiedby)
     * automatically and preserves fields not included in the update data.
     * It returns a boolean indicating whether the update was successful.
     * 
     * @param int $projectId The unique identifier (projectid) of the project
     *                       to update.
     * @param array<string, mixed> $data Associative array containing the
     *                                    fields to update with their new
     *                                    values. Only provided fields will
     *                                    be updated, allowing for partial
     *                                    updates.
     * 
     * @return bool True if the update was successful, false if the project
     *              was not found or the update failed.
     * 
     * @throws \InvalidArgumentException If the project ID is invalid or
     *                                   required fields are missing.
     * @throws \RuntimeException If the database transaction fails or
     *                           connection is lost.
     * @throws \Exception If any error occurs during the update process.
     * 
     * @example
     * // Update project status
     * $success = $repository->update(789, [
     *     'status' => 'in_progress'
     * ]);
     * 
     * @example
     * // Update multiple fields
     * $success = $repository->update(789, [
     *     'status' => 'completed',
     *     'enddate' => '2026-05-15',
     *     'budget' => 45000,
     *     'description' => 'Updated project description'
     * ]);
     * 
     * @example
     * // Update progress percentage
     * $success = $repository->update(789, [
     *     'progress' => 75
     * ]);
     * 
     * @example
     * // Handle update result
     * if ($success) {
     *     toast()->success('Project updated successfully');
     * } else {
     *     toast()->error('Failed to update project');
     * }
     * 
     * @see \Illuminate\Support\Facades\DB For transaction handling
     */
    public function update(int $projectId, array $data): bool;

    /**
     * Soft delete a project record by marking it as deleted.
     * 
     * This method performs a soft delete by updating the deleted flag in
     * the vtiger_crmentity table rather than removing the record from
     * the database. This preserves audit history and allows for potential
     * restoration.
     * 
     * After deletion, the project will no longer appear in normal queries
     * unless explicitly requested with deleted records included. Related
     * entities (tasks, milestones, etc.) are not automatically deleted
     * and should be handled separately if needed.
     * 
     * @param int $projectId The unique identifier (projectid) of the project
     *                       to delete.
     * 
     * @return bool True if the deletion was successful, false if the project
     *              was not found or already deleted.
     * 
     * @throws \RuntimeException If the database update fails or connection
     *                           is lost.
     * 
     * @example
     * // Delete project
     * $success = $repository->delete(789);
     * 
     * @example
     * // Confirm deletion to user
     * if ($success) {
     *     return response()->json(['message' => 'Project deleted']);
     * }
     * 
     * @example
     * // Handle deletion in frontend
     * const handleDelete = async (id) => {
     *     const confirmed = await confirm('Delete this project?');
     *     if (confirmed) {
     *         await api.delete(`/projects/${id}`);
     *     }
     * };
     * 
     * @example
     * // Check if project is deleted
     * $project = $repository->findById(789);
     * if ($project === null) {
     *     // Project not found or deleted
     * }
     * 
     * @see Project::isActive For checking if a project is soft-deleted
     */
    public function delete(int $projectId): bool;

    /**
     * Retrieve all tasks related to a specific project.
     * 
     * This method fetches all task entities that are linked to the specified
     * project ID through the vtiger_projecttask relationship. It includes
     * task metadata, assignee information, and status for display in project
     * task lists or Gantt charts.
     * 
     * The method respects soft-deletion by excluding tasks where the deleted
     * flag is set in the vtiger_crmentity table. Results are typically ordered
     * by due date or sequence number for proper task sequencing.
     * 
     * @param int $projectId The unique identifier (projectid) of the project
     *                       whose tasks should be retrieved.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing task information
     *                                          related to the specified project.
     *                                          Each array includes task ID,
     *                                          name, status, assignee, dates,
     *                                          and other relevant metadata.
     *                                          Returns an empty array if the
     *                                          project has no tasks.
     * 
     * @throws \RuntimeException If the database query fails or connection
     *                           is lost.
     * 
     * @example
     * // Get all tasks for a project
     * $tasks = $repository->getTasksByProject(789);
     * 
     * @example
     * // Display tasks in frontend
     * foreach ($tasks as $task) {
     *     echo $task['taskname'];
     *     echo $task['status'];
     *     echo $task['duedate'];
     * }
     * 
     * @example
     * // Calculate project progress from tasks
     * $completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
     * $progress = ($completedTasks / count($tasks)) * 100;
     * 
     * @example
     * // Check if project has tasks
     * if (empty($tasks)) {
     *     echo 'No tasks defined for this project';
     * }
     * 
     * @see \App\Domain\Entities\ProjectTask For the task entity structure
     * @see vtiger_projecttask For the relationship table used in the query
     */
    public function getTasksByProject(int $projectId): array;

    /**
     * Perform a global search for projects with ranking and limits.
     * 
     * This method provides advanced search functionality with relevance
     * ranking, result limiting, and matching across multiple project fields.
     * It is designed for global search features that aggregate results from
     * multiple entity types.
     * 
     * The search supports partial matching and returns results ordered by
     * relevance. Each result includes a type identifier for frontend routing
     * and display purposes.
     * 
     * @param string $query The search query string. Supports partial matching
     *                      across projectname, description, client name,
     *                      and other fields.
     * @param int $limit The maximum number of results to return.
     * 
     * @return array<int, array<string, mixed>> An array of associative arrays
     *                                          containing project information
     *                                          with a 'type' field set to
     *                                          'project' for frontend
     *                                          identification.
     * 
     * @throws \InvalidArgumentException If the query is empty or limit is
     *                                   invalid (e.g., negative or zero).
     * @throws \RuntimeException If the database query fails or connection
     *                           is lost.
     * 
     * @example
     * // Global search with limit
     * $results = $repository->search('website redesign', 5);
     * 
     * @example
     * // Format for global search response
     * return [
     *     'projects' => $results,
     *     'total' => count($results)
     * ];
     * 
     * @example
     * // Response structure
     * // Returns: [
     * //     ['id' => 789, 'name' => 'Website Redesign', 'type' => 'project', ...],
     * //     ...
     * // ]
     * 
     * @example
     * // Use in global search bar
     * const searchProjects = async (query) => {
     *     const response = await api.get(`/search?query=${query}&limit=10`);
     *     displayResults(response.data.projects);
     * };
     * 
     * @see \App\Application\UseCases\GlobalSearchUseCase For the use case that
     *                                                    aggregates results from
     *                                                    multiple repositories
     */
    public function search(string $query, int $limit): array;

    /**
     * Count the number of projects related to a specific client.
     * 
     * This method performs an efficient COUNT query to determine how many
     * projects are linked to a specific account (client). It is optimized
     * for performance and is typically used for displaying summary statistics
     * on client detail pages.
     * 
     * The count excludes soft-deleted projects and only includes active
     * records in the CRM system.
     * 
     * @param int $clientId The unique identifier (accountid) of the client
     *                      for which to count projects.
     * 
     * @return int The number of active projects related to the specified
     *             client. Returns 0 if the client has no projects or
     *             if the client does not exist.
     * 
     * @throws \RuntimeException If the database query fails or connection
     *                           is lost.
     * 
     * @example
     * // Get project count for client dashboard
     * $count = $repository->countByClient(123);
     * 
     * @example
     * // Display in frontend
     * echo "Projects: {$count}";
     * 
     * @example
     * // Use in summary card
     * <div class="stat-card">
     *     <span class="label">Projects</span>
     *     <span class="value"><?= $count ?></span>
     * </div>
     * 
     * @example
     * // Check if client has projects
     * if ($count > 0) {
     *     echo 'Client has active projects';
     * } else {
     *     echo 'No projects for this client';
     * }
     * 
     * @see \App\Application\UseCases\GetClientSummaryUseCase For typical usage
     *                                                          in client summaries
     */
    public function countByClient(int $clientId): int;
}