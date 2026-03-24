<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\ActivityLog\ActivityLogDTO;
use App\Application\DTOs\ActivityLog\ActivityLogListDTO;
use App\Application\Repositories\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VtigerActivityLogRepository implements ActivityLogRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getRecentActivities(int $limit = 20): ActivityLogListDTO
    {
        $records = $this->queryBase()
            ->orderBy('vtiger_modtracker_basic.changedon', 'desc')
            ->limit($limit)
            ->get();

        $activities = $this->mapToEntities($records);

        return ActivityLogListDTO::fromEntities($activities);
    }

    /**
     * @inheritDoc
     */
    public function getByEntityType(string $entityType, int $limit = 20): ActivityLogListDTO
    {
        $moduleMap = array_flip([
            'client' => 'Account',
            'project' => 'Project',
            'quote' => 'Quotes',
            'activity' => 'Calendar',
            'opportunity' => 'Potentials',
            'contact' => 'Contacts',
        ]);

        $module = $moduleMap[$entityType] ?? null;

        if (!$module) {
            return new ActivityLogListDTO([], 0);
        }

        $records = $this->queryBase()
            ->where('vtiger_modtracker_basic.module', $module)
            ->orderBy('vtiger_modtracker_basic.changedon', 'desc')
            ->limit($limit)
            ->get();

        $activities = $this->mapToEntities($records);

        return ActivityLogListDTO::fromEntities($activities);
    }

    /**
     * @inheritDoc
     */
    public function getByUser(int $userId, int $limit = 20): ActivityLogListDTO
    {
        $records = $this->queryBase()
            ->where('vtiger_modtracker_basic.whodid', $userId)
            ->orderBy('vtiger_modtracker_basic.changedon', 'desc')
            ->limit($limit)
            ->get();

        $activities = $this->mapToEntities($records);

        return ActivityLogListDTO::fromEntities($activities);
    }

    /**
     * Get recent activities with advanced filters
     */
    public function getRecentActivitiesWithFilters(
        int $limit = 50,
        ?string $entityType = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): ActivityLogListDTO {
        $query = $this->queryBase();

        // Filter by entity type
        if ($entityType) {
            $moduleMap = [
                'client' => 'Accounts',
                'project' => 'Project',
                'task' => 'ProjectTask',
                'quote' => 'Quotes',
                'opportunity' => 'Potentials',
                'contact' => 'Contacts',
                'activity' => 'Calendar',
                'document' => 'Documents',
                'ticket' => 'HelpDesk',
                'product' => 'Products',
            ];

            if (isset($moduleMap[$entityType])) {
                $query->where('vtiger_modtracker_basic.module', $moduleMap[$entityType]);
            }
        }

        // Filter by action
        if ($action) {
            $statusMap = [
                'created' => 0,
                'updated' => 1,
                'deleted' => 2,
                'restored' => 3,
                'transferred' => 4,
            ];

            if (isset($statusMap[$action])) {
                $query->where('vtiger_modtracker_basic.status', $statusMap[$action]);
            }
        }

        // Filter by user
        if ($userId) {
            $query->where('vtiger_modtracker_basic.whodid', $userId);
        }

        // Filter by date from
        if ($dateFrom) {
            $query->where('vtiger_modtracker_basic.changedon', '>=', $dateFrom);
        }

        // Filter by date to
        if ($dateTo) {
            $query->where('vtiger_modtracker_basic.changedon', '<=', $dateTo);
        }

        // Search by entity name
        if ($search) {
            $query->where('vtiger_crmentity.label', 'LIKE', "%{$search}%");
        }

        $records = $query->orderBy('vtiger_modtracker_basic.changedon', 'desc')
            ->limit($limit)
            ->get();

        $activities = $this->mapToEntities($records);

        return ActivityLogListDTO::fromEntities($activities);
    }

    /**
     * Base query with common joins.
     */
    private function queryBase(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('vtiger')
            ->table('vtiger_modtracker_basic')
            ->leftJoin('vtiger_users', 'vtiger_modtracker_basic.whodid', '=', 'vtiger_users.id')
            ->leftJoin('vtiger_crmentity', 'vtiger_modtracker_basic.crmid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->select(
                'vtiger_modtracker_basic.id',
                'vtiger_modtracker_basic.crmid',
                'vtiger_modtracker_basic.module',
                'vtiger_modtracker_basic.whodid',
                'vtiger_modtracker_basic.changedon',
                'vtiger_modtracker_basic.status',
                'vtiger_users.user_name',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_crmentity.label as entity_name',
                'vtiger_crmentity.setype as entity_type'
            );
    }

    /**
     * Map database records to Entities.
     * 
     * @param Collection $records
     * @return ActivityLog[]
     */
    private function mapToEntities(Collection $records): array
    {
        return $records
            ->map(fn($record) => ActivityLogDTO::fromArray((array) $record))
            ->map(fn(ActivityLogDTO $dto) => $dto->toEntity())
            ->toArray();
    }
}
