<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Task;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Task Mapper
 * 
 * Converts between Vtiger database representations and domain Task entities.
 * Supports both raw database rows and domain entity transformations.
 * 
 * @package App\Infrastructure\Mappers
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Task
 * @see \App\Infrastructure\Repositories\VtigerTaskRepository
 */
class TaskMapper
{
    /**
     * Map raw database row to domain Task entity
     * 
     * This method handles the transformation of a joined database query result
     * (from vtiger_activity, vtiger_crmentity, vtiger_users, etc.) into a
     * properly structured Task domain entity.
     * 
     * @param object $row Raw database row from joined query
     * 
     * @return Task Domain entity with all properties mapped
     * 
     * @throws InvalidArgumentException If required fields are missing
     * 
     * @example
     * $row = DB::table('vtiger_activity')
     *     ->join('vtiger_crmentity', ...)
     *     ->join('vtiger_users', ...)
     *     ->select(...)
     *     ->first();
     * $task = TaskMapper::fromDatabaseRow($row);
     */
    public static function fromDatabaseRow(object $row): Task
    {
        // Validate required fields
        self::validateRequiredFields($row);

        return new Task(
            id: (int) $row->activityid,
            subject: $row->subject,
            activityType: $row->activitytype ?? 'Task',
            dateStart: self::parseDate($row->date_start),
            dueDate: isset($row->due_date) ? self::parseDate($row->due_date) : null,
            timeStart: $row->time_start ?? null,
            timeEnd: $row->time_end ?? null,
            status: $row->status ?? 'Not Started',
            priority: $row->priority ?? 'Medium',
            location: $row->location ?? null,
            description: $row->description ?? null,
            assignedUserId: (int) $row->smownerid,
            assignedUserName: self::buildUserName($row),
            assignedUserEmail: $row->email ?? null,
            createdByUserId: (int) ($row->smcreatorid ?? $row->smownerid),
            createdAt: self::parseDate($row->createdtime),
            updatedAt: self::parseDate($row->modifiedtime),
            relatedRecordId: isset($row->related_record_id) ? (int) $row->related_record_id : null,
            relatedModuleType: $row->related_module_type ?? null,
            sendNotification: ($row->sendnotification ?? '0') === '1',
            durationHours: isset($row->duration_hours) ? (int) $row->duration_hours : null,
            durationMinutes: isset($row->duration_minutes) ? (int) $row->duration_minutes : null,
        );
    }

    /**
     * Map domain Task entity to persistence array
     * 
     * This method prepares task data for database insertion or update.
     * It separates fields that go into vtiger_activity from those
     * that go into vtiger_crmentity.
     * 
     * @param Task $task Domain entity to map
     * 
     * @return array{activity: array, crmentity: array} Associative arrays for database operations
     * 
     * @example
     * $mapped = TaskMapper::toPersistence($task);
     * DB::table('vtiger_activity')->insert($mapped['activity']);
     * DB::table('vtiger_crmentity')->insert($mapped['crmentity']);
     */
    public static function toPersistence(Task $task): array
    {
        return [
            'activity' => [
                'activityid' => $task->getId(),
                'subject' => $task->getSubject(),
                'activitytype' => $task->getActivityType(),
                'date_start' => $task->getDateStart()->format('Y-m-d'),
                'due_date' => $task->getDueDate()?->format('Y-m-d'),
                'time_start' => $task->getTimeStart(),
                'time_end' => $task->getTimeEnd(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'location' => $task->getLocation(),
                'sendnotification' => $task->shouldSendNotification() ? '1' : '0',
                'duration_hours' => $task->getDurationHours(),
                'duration_minutes' => $task->getDurationMinutes(),
                'visibility' => 'all',
                'notime' => '0',
            ],
            'crmentity' => [
                'crmid' => $task->getId(),
                'smcreatorid' => $task->getCreatedByUserId(),
                'smownerid' => $task->getAssignedUserId(),
                'modifiedby' => $task->getAssignedUserId(),
                'setype' => 'Calendar',
                'label' => $task->getSubject(),
                'description' => $task->getDescription(),
                'createdtime' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'modifiedtime' => $task->getUpdatedAt()->format('Y-m-d H:i:s'),
                'deleted' => 0,
                'version' => 0,
                'presence' => 1,
            ],
        ];
    }

    /**
     * Build full user name from database row
     * 
     * @param object $row Database row with first_name and last_name
     * @return string|null Full name or null if not available
     */
    private static function buildUserName(object $row): ?string
    {
        if (empty($row->first_name) && empty($row->last_name)) {
            return null;
        }

        return trim("{$row->first_name} {$row->last_name}") ?: null;
    }

    /**
     * Parse date string to DateTimeImmutable
     * 
     * @param string $dateString Date string in Y-m-d or Y-m-d H:i:s format
     * @return DateTimeImmutable Parsed datetime object
     * 
     * @throws InvalidArgumentException If date format is invalid
     */
    private static function parseDate(string $dateString): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($dateString);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Invalid date format: {$dateString}. Expected Y-m-d or Y-m-d H:i:s",
                previous: $e
            );
        }
    }

    /**
     * Validate that all required fields are present in the database row
     * 
     * @param object $row Database row to validate
     * @return void
     * 
     * @throws InvalidArgumentException If required fields are missing
     */
    private static function validateRequiredFields(object $row): void
    {
        $requiredFields = [
            'activityid',
            'subject',
            'date_start',
            'smownerid',
            'createdtime',
            'modifiedtime',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($row->$field)) {
                throw new InvalidArgumentException(
                    "Required field '{$field}' is missing from database row"
                );
            }
        }
    }
}