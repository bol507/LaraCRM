<?php

namespace App\Application\ValueObjects\Task;

/**
 * Task Status Value Object
 */
class TaskStatus
{
    public const NOT_STARTED = 'Not Started';
    public const IN_PROGRESS = 'In Progress';
    public const COMPLETED = 'Completed';
    public const PENDING_INPUT = 'Pending Input';
    public const PLANNED = 'Planned';

    private const VALID_STATUSES = [
        self::NOT_STARTED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::PENDING_INPUT,
        self::PLANNED,
    ];

    public function __construct(
        private readonly string $status
    ) {
        if (!$this->isValid($status)) {
            throw new \InvalidArgumentException("Invalid task status: {$status}");
        }

        $this->status = $status;
    }

    public function value(): string
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    public static function all(): array
    {
        return self::VALID_STATUSES;
    }
}