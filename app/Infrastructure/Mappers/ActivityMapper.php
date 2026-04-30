<?php

namespace App\Infrastructure\Mappers;

use App\Application\DTOs\Calendar\ActivityDto;
use App\Domain\Entities\Activity;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use stdClass;

/**
 * Activity Mapper
 * 
 * Responsible for transforming between:
 * - Database rows (stdClass/array) ↔ Domain Entity (Activity)
 * - Domain Entity (Activity) ↔ Application DTO (ActivityDto)
 * 
 * Handles Vtiger-specific field mappings, date parsing, and business logic flags.
 * 
 * @package App\Infrastructure\Mappers
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Activity
 * @see \App\Application\DTOs\Calendar\ActivityDto
 */
class ActivityMapper
{










    /**
     * Prepare query with proper field selection and joins for activities
     * 
     * Centralizes the JOIN logic to ensure consistency across repositories.
     * 
     * @param \Illuminate\Database\Query\Builder $query Base query builder
     * @param array $additionalSelects Extra fields to select
     * @return \Illuminate\Database\Query\Builder
     */
    public static function prepareQuery(
        \Illuminate\Database\Query\Builder $query,
        array $additionalSelects = []
    ): \Illuminate\Database\Query\Builder {
        $baseSelects = [
            'act.activityid',
            'act.subject',
            'act.activitytype',
            'act.status',
            'act.priority',
            'act.date_start',
            'act.due_date',
            'act.time_start',
            'act.time_end',
            'act.sendnotification',
            'act.location',
            'act.visibility',
            'act.duration_hours',
            'act.duration_minutes',
            'act.semodule as related_module_type',
            'actrel.crmid as related_record_id',
            'crm.smcreatorid',
            'crm.smownerid',
            'crm.createdtime',
            'crm.modifiedtime',
            'crm.description',
            'usr.user_name as assigned_user_name',
            'usr.email1 as assigned_user_email',
        ];

        return $query
            ->from('vtiger_activity as act')
            ->join('vtiger_crmentity as crm', 'act.activityid', '=', 'crm.crmid')
            ->leftJoin('vtiger_seactivityrel as actrel', 'act.activityid', '=', 'actrel.activityid')
            ->leftJoin('vtiger_users as usr', 'crm.smownerid', '=', 'usr.id')
            ->where('crm.deleted', 0)
            ->select(array_merge($baseSelects, $additionalSelects));
    }

    /**
     * Parse a Vtiger date string to DateTimeImmutable
     * 
     * @param string|null $value Date in Y-m-d format from database
     * @return \DateTimeImmutable|null
     */
    public static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            // ✅ Crear DateTimeImmutable en lugar de DateTime
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            // Loggear error en desarrollo, retornar null en producción
            if (app()->environment('local')) {
                Log::warning('ActivityMapper::parseDate failed', [
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Parse a Vtiger datetime string to DateTimeImmutable
     * 
     * @param string|null $value Datetime in Y-m-d H:i:s format
     * @return \DateTimeImmutable|null
     */
    private static function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                Log::warning('ActivityMapper::parseDateTime failed', [
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Calculate if an activity is overdue based on due date and status
     * 
     * @param string|null $dueDate Due date in Y-m-d format
     * @param string $status Activity status
     * @return bool
     */
    public static function isOverdue(?string $dueDate, string $status): bool
    {
        $completedStatuses = ['Completed', 'Closed', 'Held', 'Deferred'];
        if (in_array($status, $completedStatuses, true)) {
            return false;
        }
        if (!$dueDate || $dueDate === '0000-00-00') {
            return false;
        }
        try {
            $due = new DateTime($dueDate);
            $today = new DateTime('midnight');
            return $due < $today;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Calculate if an activity is completed based on status
     * 
     * @param string $status Activity status
     * @return bool
     */
    public static function isCompleted(string $status): bool
    {
        $completedStatuses = ['Completed', 'Closed', 'Held', 'Deferred'];
        return in_array($status, $completedStatuses, true);
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

    public static function toDatabaseRow(Activity $activity): array
    {
        return [
            'activityid' => $activity->getId(),
            'subject' => $activity->getSubject(),
            'activitytype' => $activity->getActivityType(),
            'status' => $activity->getStatus(),
            'priority' => $activity->getPriority(),
            'date_start' => $activity->getDateStart()->format('Y-m-d'),
            'due_date' => $activity->getDateDue()?->format('Y-m-d'),
            'time_start' => $activity->getTimeStart(),
            'time_end' => $activity->getTimeEnd(),
            'location' => $activity->getLocation(),
            'visibility' => $activity->getVisibility(),
            'sendnotification' => '0', // Default, o agregar al constructor si es necesario
            'notime' => empty($activity->getTimeStart()) ? '1' : '0',
            'duration_hours' => 0, // Agregar si tu entidad lo tiene
            'duration_minutes' => 0,
            'semodule' => $activity->getRelatedModuleType(),
            // Nota: related_record_id va en vtiger_seactivityrel, no aquí
        ];
    }
}
