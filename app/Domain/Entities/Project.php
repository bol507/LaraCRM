<?php

namespace App\Domain\Entities;

/**
 * Class Project
 * 
 * Domain entity representing a project in Vtiger CRM.
 * 
 * This entity encapsulates all data related to a project engagement or contract
 * in the CRM system. It maps to the vtiger_project table joined with vtiger_crmentity
 * for audit information and optionally with vtiger_account, vtiger_potential, and
 * vtiger_users for denormalized related entity information.
 * 
 * The entity uses constructor property promotion for concise property declaration
 * and initialization. Most properties are mutable (public) to allow updates during
 * entity hydration, while select properties like lastActivity are immutable (readonly)
 * as they are calculated values.
 * 
 * Key characteristics:
 * - Constructor property promotion: Properties declared and initialized in constructor
 * - Mixed mutability: Most properties mutable for hydration, select properties readonly
 * - Nullable fields: Optional database fields marked as nullable with default null
 * - Denormalized data: Includes related account, user, and potential names for display
 * - Task statistics: Includes aggregated task counts for dashboard display
 * - Audit tracking: Creation/modification timestamps from vtiger_crmentity
 * 
 * Database mapping:
 * - Primary table: vtiger_project (projectid is primary key)
 * - Audit join: vtiger_crmentity ON projectid = crmid
 * - Optional joins: vtiger_account, vtiger_potential, vtiger_users for denormalization
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Application\Repositories\ProjectRepositoryInterface For persistence operations
 * @see \App\Infrastructure\Mappers\ProjectMapper For database row to entity mapping
 * @see \App\Application\DTOs\CreateProjectRequest For creation request DTO
 * @see \App\Application\DTOs\UpdateProjectRequest For update request DTO
 */
class Project
{
    

    /**
     * Project constructor.
     * 
     * Creates a Project entity instance with all project data from the Vtiger
     * CRM database. Properties are declared and initialized via constructor
     * property promotion. Most properties are mutable (public) to allow updates
     * during entity hydration from database rows, while select properties like
     * lastActivity are immutable (readonly) as they represent calculated values.
     * 
     * The entity is designed to be created by the repository layer after fetching
     * and joining data from vtiger_project, vtiger_crmentity, and optionally
     * vtiger_account, vtiger_potential, and vtiger_users tables. Application
     * code should not instantiate this class directly but should use repository
     * methods instead.
     * 
     * @param int $projectid The unique identifier of the project.
     * @param string $projectname The name or title of the project.
     * @param string|null $project_no The project number as displayed in the CRM system.
     * @param string|null $startdate The planned start date of the project.
     * @param string|null $targetenddate The target or planned end date of the project.
     * @param string|null $actualenddate The actual end date when the project was completed.
     * @param string|null $targetbudget The target or planned budget for the project.
     * @param string|null $projecturl The external URL or link associated with the project.
     * @param string|null $projectstatus The current status of the project.
     * @param string|null $projectpriority The priority level assigned to the project.
     * @param string|null $projecttype The type or category classification of the project.
     * @param string|null $progress The completion progress percentage of the project.
     * @param string|null $linktoaccountscontacts The ID of related account/contact in Vtiger format.
     * @param int|null $assigned_user_id The ID of the user assigned to manage this project.
     * @param string|null $account_name The name of the related account, denormalized for display.
     * @param string|null $assigned_user_name The name of the assigned user, denormalized for display.
     * @param string|null $potential_name The name of the related potential, denormalized for display.
     * @param int|null $totalTasks The total number of tasks associated with this project.
     * @param int|null $completedTasks The number of completed tasks associated with this project.
     * @param int|null $hits The number of views or access hits for this project record.
     * @param string|null $description Additional description or notes about the project.
     * @param string|null $createdtime The timestamp when the project record was created.
     * @param string|null $modifiedtime The timestamp when the project record was last modified.
     * @param string|null $lastActivity The timestamp of the most recent activity (calculated, readonly).
     * 
     * @return void
     */
    public function __construct(
        public readonly int $projectid,
        public readonly string $projectname,
        public readonly ?string $project_no,
        public readonly ?string $startdate,
        public readonly ?string $targetenddate,
        public readonly ?string $actualenddate,
        public readonly ?string $targetbudget,
        public readonly ?string $projecturl,
        public readonly ?string $projectstatus,
        public readonly ?string $projectpriority,
        public readonly ?string $projecttype,
        public readonly ?string $progress,
        public readonly ?string $linktoaccountscontacts,
        public readonly ?int $assigned_user_id,
        public readonly ?string $account_name,
        public readonly ?string $assigned_user_name,
        public readonly ?string $potential_name,
        public readonly ?int $totalTasks,
        public readonly ?int $completedTasks,
        public readonly ?int $hits,
        public readonly ?string $description,
        public readonly ?string $createdtime,
        public readonly ?string $modifiedtime,
        public readonly ?string $lastActivity = null,
        public readonly ?string $lastActivityRaw = null
    
    ) {}

    /**
     * Check if the project has an associated account.
     * 
     * Determines whether this project is linked to a client account by checking
     * if the linktoaccountscontacts or account_name properties are populated.
     * Useful for conditional display of account information or filtering
     * projects by account association status.
     * 
     * @return bool True if the project has an associated account, false otherwise.
     * 
     * @example
     * // Conditionally display account information
     * if ($project->hasAccount()) {
     *     echo "Client: {$project->account_name}";
     * }
     * 
     * @example
     * // Filter projects with accounts
     * $withAccounts = array_filter($projects, fn($p) => $p->hasAccount());
     */
    public function hasAccount(): bool
    {
        return $this->account_name !== null || $this->linktoaccountscontacts !== null;
    }

    /**
     * Check if the project is assigned to a specific user.
     * 
     * Determines whether this project has an assigned owner by checking if the
     * assigned_user_id property is populated. Useful for permission checks,
     * ownership-based filtering, and conditional display of user information.
     * 
     * @return bool True if the project is assigned to a user, false otherwise.
     * 
     * @example
     * // Check ownership before allowing edit
     * if ($project->isAssigned() && $project->assigned_user_id !== $currentUserId) {
     *     throw new AuthorizationException('Not authorized to edit this project');
     * }
     * 
     * @example
     * // Filter assigned projects
     * $assigned = array_filter($projects, fn($p) => $p->isAssigned());
     */
    public function isAssigned(): bool
    {
        return $this->assigned_user_id !== null;
    }

    /**
     * Check if the project is completed.
     * 
     * Determines whether the project has reached a completed state by checking
     * if the projectstatus is "completed" or if the actualenddate is populated.
     * Useful for filtering completed projects, calculating completion rates,
     * and excluding completed projects from active project views.
     * 
     * @return bool True if the project is completed, false otherwise.
     * 
     * @example
     * // Exclude completed projects from active list
     * $active = array_filter($projects, fn($p) => !$p->isCompleted());
     * 
     * @example
     * // Calculate completion rate
     * $completed = array_filter($projects, fn($p) => $p->isCompleted());
     * $completionRate = count($projects) > 0 ? count($completed) / count($projects) : 0;
     */
    public function isCompleted(): bool
    {
        return $this->projectstatus === 'completed' || $this->actualenddate !== null;
    }

    /**
     * Check if the project is currently in progress.
     * 
     * Determines whether the project is actively being worked on by checking
     * if the projectstatus is "in_progress" or similar active status values.
     * Useful for filtering active projects, calculating workload, and
     * prioritizing resource allocation.
     * 
     * @return bool True if the project is in progress, false otherwise.
     * 
     * @example
     * // Get active projects for dashboard
     * $activeProjects = array_filter($projects, fn($p) => $p->isInProgress());
     * 
     * @example
     * // Display status badge in UI
     * if ($project->isInProgress()) {
     *     echo '<span class="badge badge-primary">In Progress</span>';
     * }
     */
    public function isInProgress(): bool
    {
        return in_array($this->projectstatus, ['in_progress', 'active', 'ongoing'], true);
    }

    /**
     * Calculate the completion percentage based on task counts.
     * 
     * Computes the project completion percentage by dividing completed tasks
     * by total tasks. Returns null if task data is not available. Used for
     * progress visualization when explicit progress field is not populated.
     * 
     * @return int|null The completion percentage (0-100), or null if calculation
     *                  cannot be performed due to missing task data.
     * 
     * @example
     * // Display calculated progress in UI
     * $progress = $project->calculateProgressFromTasks();
     * if ($progress !== null) {
     *     echo "Progress: {$progress}%";
     * }
     * 
     * @example
     * // Sort projects by calculated progress
     * usort($projects, fn($a, $b) => 
     *     ($b->calculateProgressFromTasks() ?? 0) <=> ($a->calculateProgressFromTasks() ?? 0)
     * );
     */
    public function calculateProgressFromTasks(): ?int
    {
        if ($this->totalTasks === null || $this->totalTasks === 0) {
            return null;
        }
        if ($this->completedTasks === null) {
            return 0;
        }
        return (int) round(($this->completedTasks / $this->totalTasks) * 100);
    }

    /**
     * Check if the project is overdue.
     * 
     * Determines whether the project has exceeded its target end date by
     * comparing targetenddate with the current date. Returns false if the
     * project is completed or if target date is not set. Useful for
     * identifying delayed projects and triggering escalation workflows.
     * 
     * @param string|null $referenceDate Optional reference date in "YYYY-MM-DD"
     *                                   format for comparison. Defaults to today.
     * @return bool True if the project is overdue, false otherwise.
     * 
     * @example
     * // Flag overdue projects in UI
     * if ($project->isOverdue()) {
     *     echo '<span class="badge badge-danger">Overdue</span>';
     * }
     * 
     * @example
     * // Get list of overdue projects
     * $overdue = array_filter($projects, fn($p) => $p->isOverdue());
     */
    public function isOverdue(?string $referenceDate = null): bool
    {
        if ($this->isCompleted() || $this->targetenddate === null) {
            return false;
        }
        $compareDate = $referenceDate ?? date('Y-m-d');
        return $this->targetenddate < $compareDate;
    }

    /**
     * Convert the entity to an array for JSON serialization.
     * 
     * Prepares the project data for transmission in API responses. The returned
     * array uses snake_case keys to conform to common JSON API conventions and
     * includes all public properties of the entity. Calculated fields like
     * progress_from_tasks are included for frontend convenience.
     * 
     * @return array<string, mixed> An associative array containing all entity
     *                              properties formatted for JSON serialization.
     * 
     * @example
     * // Convert to array for API response
     * $responseData = $project->toArray();
     * 
     * @example
     * // Use in controller
     * return response()->json($project->toArray());
     * 
     * @example
     * // Response structure
     * // Returns:
     * // [
     * //     'projectid' => 789,
     * //     'projectname' => 'Website Redesign',
     * //     'project_no' => 'PROJ-2026-001',
     * //     'startdate' => '2026-01-15',
     * //     'targetenddate' => '2026-06-30',
     * //     'actualenddate' => null,
     * //     'targetbudget' => '50000.00',
     * //     'projecturl' => 'https://example.com/project',
     * //     'projectstatus' => 'in_progress',
     * //     'projectpriority' => 'High',
     * //     'projecttype' => 'Client',
     * //     'progress' => '65',
     * //     'linktoaccountscontacts' => '123x4',
     * //     'assigned_user_id' => 5,
     * //     'account_name' => 'Acme Corporation',
     * //     'assigned_user_name' => 'John Doe',
     * //     'potential_name' => 'Website Deal Q1',
     * //     'totalTasks' => 20,
     * //     'completedTasks' => 13,
     * //     'hits' => 45,
     * //     'description' => 'Complete website overhaul',
     * //     'createdtime' => '2026-01-10 09:00:00',
     * //     'modifiedtime' => '2026-03-15 14:30:00',
     * //     'lastActivity' => '2026-03-15 14:30:00',
     * //     'progress_from_tasks' => 65
     * // ]
     */
    public function toArray(): array
    {
        return [
            'projectid' => $this->projectid,
            'projectname' => $this->projectname,
            'project_no' => $this->project_no,
            'startdate' => $this->startdate,
            'targetenddate' => $this->targetenddate,
            'actualenddate' => $this->actualenddate,
            'targetbudget' => $this->targetbudget,
            'projecturl' => $this->projecturl,
            'projectstatus' => $this->projectstatus,
            'projectpriority' => $this->projectpriority,
            'projecttype' => $this->projecttype,
            'progress' => $this->progress,
            'linktoaccountscontacts' => $this->linktoaccountscontacts,
            'assigned_user_id' => $this->assigned_user_id,
            'account_name' => $this->account_name,
            'assigned_user_name' => $this->assigned_user_name,
            'potential_name' => $this->potential_name,
            'totalTasks' => $this->totalTasks,
            'completedTasks' => $this->completedTasks,
            'hits' => $this->hits,
            'description' => $this->description,
            'createdtime' => $this->createdtime,
            'modifiedtime' => $this->modifiedtime,
            'lastActivity' => $this->lastActivity,
            'progress_from_tasks' => $this->calculateProgressFromTasks(),
        ];
    }
}