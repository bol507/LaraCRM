<?php

namespace App\Application\ValueObjects\Task;

use InvalidArgumentException;

/**
 * Task Status Value Object
 * 
 * Encapsulates valid status values for tasks and provides
 * business logic for status validation and state checking.
 * 
 * This value object ensures that task status values are always
 * valid and provides a type-safe way to work with task statuses
 * throughout the application.
 * 
 * Valid statuses:
 * - Not Started: Initial state, work has not begun
 * - In Progress: Work is actively being performed
 * - Completed: Work has been finished
 * - Pending Input: Waiting for external information or approval
 * - Planned: Scheduled for future start
 * 
 * @package App\Application\ValueObjects\Task
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Task\CreateTaskUseCase
 * @see \App\Application\UseCases\Task\UpdateTaskUseCase
 * @see \App\Domain\Entities\Task
 */
class TaskStatus
{
    /**
     * Status: Not Started
     * 
     * Initial state for newly created tasks.
     */
    public const NOT_STARTED = 'Not Started';

    /**
     * Status: In Progress
     * 
     * Work is actively being performed on the task.
     */
    public const IN_PROGRESS = 'In Progress';

    /**
     * Status: Completed
     * 
     * Task work has been finished.
     */
    public const COMPLETED = 'Completed';

    /**
     * Status: Pending Input
     * 
     * Task is waiting for external information, approval, or input.
     */
    public const PENDING_INPUT = 'Pending Input';

    /**
     * Status: Planned
     * 
     * Task is scheduled for future start.
     */
    public const PLANNED = 'Planned';

    /**
     * List of all valid status values
     * 
     * Used for validation and dropdown options.
     * 
     * @var array<string>
     */
    private const VALID_STATUSES = [
        self::NOT_STARTED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::PENDING_INPUT,
        self::PLANNED,
    ];


    /**
     * Create a new TaskStatus instance
     * 
     * Validates that the provided status value is one of the
     * allowed constants. Throws an exception if the value is invalid.
     * 
     * @param string $status Status value to validate and store
     * 
     * @throws InvalidArgumentException If the status value is not valid
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::NOT_STARTED);
     * 
     * @example
     * $status = new TaskStatus('In Progress');
     * 
     * @example
     * // This will throw an exception
     * $status = new TaskStatus('Invalid Status');
     */
    public function __construct(private readonly string $status)
    {
        if (!self::isValid($status)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid task status: "%s". Valid values are: %s',
                    $status,
                    implode(', ', self::VALID_STATUSES)
                )
            );
        }

        $this->status = $status;
    }

    /**
     * Get the status value as a string
     * 
     * @return string The status value
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::COMPLETED);
     * echo $status->value(); // Outputs: "Completed"
     */
    public function value(): string
    {
        return $this->status;
    }

    /**
     * Check if the status is Completed
     * 
     * @return bool True if status is Completed, false otherwise
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::COMPLETED);
     * if ($status->isCompleted()) {
     *     // Task is complete
     * }
     */
    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    /**
     * Check if the status is In Progress
     * 
     * @return bool True if status is In Progress, false otherwise
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::IN_PROGRESS);
     * if ($status->isInProgress()) {
     *     // Task is in progress
     * }
     */
    public function isInProgress(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }

    /**
     * Check if the status is Not Started
     * 
     * @return bool True if status is Not Started, false otherwise
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::NOT_STARTED);
     * if ($status->isNotStarted()) {
     *     // Task has not started yet
     * }
     */
    public function isNotStarted(): bool
    {
        return $this->status === self::NOT_STARTED;
    }

    /**
     * Check if the status is Pending Input
     * 
     * @return bool True if status is Pending Input, false otherwise
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::PENDING_INPUT);
     * if ($status->isPendingInput()) {
     *     // Task is waiting for input
     * }
     */
    public function isPendingInput(): bool
    {
        return $this->status === self::PENDING_INPUT;
    }

    /**
     * Check if the status is Planned
     * 
     * @return bool True if status is Planned, false otherwise
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::PLANNED);
     * if ($status->isPlanned()) {
     *     // Task is planned for future
     * }
     */
    public function isPlanned(): bool
    {
        return $this->status === self::PLANNED;
    }

    /**
     * Check if a status value is valid
     * 
     * Static method for validation without instantiating the object.
     * 
     * @param string $status Status value to validate
     * 
     * @return bool True if valid, false otherwise
     * 
     * @example
     * if (TaskStatus::isValid('Completed')) {
     *     // Status is valid
     * }
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    /**
     * Get all valid status values
     * 
     * Useful for populating dropdowns or validation rules.
     * 
     * @return array<string> Array of valid status values
     * 
     * @example
     * $statuses = TaskStatus::all();
     * // Returns: ['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Planned']
     */
    public static function all(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Get a human-readable label for the status
     * 
     * Returns localized labels suitable for UI display.
     * 
     * @return string Human-readable label in Spanish
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::IN_PROGRESS);
     * echo $status->label(); // Outputs: "En Progreso"
     */
    public function label(): string
    {
        return match ($this->status) {
            self::NOT_STARTED => 'No Iniciada',
            self::IN_PROGRESS => 'En Progreso',
            self::COMPLETED => 'Completada',
            self::PENDING_INPUT => 'Pendiente',
            self::PLANNED => 'Planificada',
            default => $this->status,
        };
    }

    /**
     * Convert the status to a string
     * 
     * Allows the object to be used in string contexts.
     * 
     * @return string The status value
     * 
     * @example
     * $status = new TaskStatus(TaskStatus::COMPLETED);
     * echo "Status: {$status}"; // Outputs: "Status: Completed"
     */
    public function __toString(): string
    {
        return $this->status;
    }

    /**
     * Create a TaskStatus instance from a string value
     * 
     * Factory method for creating instances with validation.
     * 
     * @param string $value Status value
     * 
     * @return self New TaskStatus instance
     * 
     * @throws InvalidArgumentException If the value is invalid
     * 
     * @example
     * $status = TaskStatus::fromString('In Progress');
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the default status (Not Started)
     * 
     * Factory method for creating the default initial status.
     * 
     * @return self New TaskStatus instance with NOT_STARTED value
     * 
     * @example
     * $defaultStatus = TaskStatus::default();
     * echo $defaultStatus->value(); // Outputs: "Not Started"
     */
    public static function default(): self
    {
        return new self(self::NOT_STARTED);
    }

    /**
     * Check if this status can transition to another status
     * 
     * Business rules for valid status transitions:
     * - Completed tasks cannot transition to other statuses (final state)
     * - All other statuses can transition to Completed
     * - All other statuses can transition to each other
     * 
     * @param self $targetStatus Target status to transition to
     * 
     * @return bool True if transition is allowed, false otherwise
     * 
     * @example
     * $current = new TaskStatus(TaskStatus::NOT_STARTED);
     * $target = new TaskStatus(TaskStatus::IN_PROGRESS);
     * if ($current->canTransitionTo($target)) {
     *     // Transition is allowed
     * }
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        // Completed is a final state - no transitions allowed from it
        if ($this->isCompleted()) {
            return false;
        }

        // All other transitions are allowed
        return true;
    }

    /**
     * Check if the status represents a completed or closed state
     * 
     * Useful for filtering and reporting.
     * 
     * @return bool True if status is a closed state, false otherwise
     */
    public function isClosed(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Check if the status represents an active or open state
     * 
     * Useful for filtering active tasks.
     * 
     * @return bool True if status is an open state, false otherwise
     */
    public function isOpen(): bool
    {
        return !$this->isClosed();
    }
}