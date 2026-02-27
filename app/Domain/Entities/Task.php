<?php

namespace App\Domain\Entities;

use DateTimeImmutable;

/**
 * Task Entity
 * 
 * Represents a task/activity in Vtiger CRM.
 * Tasks are activities with type 'Task' that have deadlines, priorities, and assignments.
 * 
 * @package App\Domain\Entities
 */
class Task
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly string $activityType,
        public readonly DateTimeImmutable $dateStart,
        public readonly ?DateTimeImmutable $dueDate,
        public readonly ?string $timeStart,
        public readonly ?string $timeEnd,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?string $location,
        public readonly ?string $description,
        public readonly int $assignedUserId,
        public readonly int $createdByUserId,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?int $relatedRecordId = null,
        public readonly ?string $relatedModuleType = null,
        public readonly bool $sendNotification = false,
        public readonly ?string $durationHours = null,
        public readonly ?string $durationMinutes = null,
    ) {}

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->isCompleted() || !$this->dueDate) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        return $this->dueDate < $today;
    }

    /**
     * Check if task is high priority
     */
    public function isHighPriority(): bool
    {
        return $this->priority === 'High';
    }

    /**
     * Get task duration in minutes
     */
    public function getDurationInMinutes(): ?int
    {
        if (!$this->durationHours && !$this->durationMinutes) {
            return null;
        }

        $hours = (int) ($this->durationHours ?? 0);
        $minutes = (int) ($this->durationMinutes ?? 0);

        return ($hours * 60) + $minutes;
    }

    /**
     * Get formatted date range
     */
    public function getFormattedDateRange(): string
    {
        $start = $this->dateStart->format('d/m/Y');
        $end = $this->dueDate?->format('d/m/Y') ?? $start;

        return $start === $end ? $start : "$start - $end";
    }

    /**
     * Get formatted time range
     */
    public function getFormattedTimeRange(): string
    {
        if (!$this->timeStart && !$this->timeEnd) {
            return 'All day';
        }

        $start = $this->timeStart ?? '00:00';
        $end = $this->timeEnd ?? '23:59';

        return "$start - $end";
    }
}