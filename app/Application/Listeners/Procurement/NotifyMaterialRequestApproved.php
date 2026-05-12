<?php

namespace App\Application\Listeners\Procurement;

use App\Domain\Events\MaterialRequestApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyMaterialRequestApproved
{
    /**
     * Handle the MaterialRequestApproved event.
     *
     * Creates an in-app notification for:
     * - The creator of the material request
     * - Users with Purchasing/Administrator roles
     * - The project manager
     */
    public function handle(MaterialRequestApproved $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event->projectId, $event->requestData);

            if (empty($recipients['userIds'])) {
                Log::warning('[NotifyMaterialRequestApproved] No recipients found', [
                    'request_id' => $event->requestId,
                ]);
                return;
            }

            // Determine icon and severity based on status
            [$icon, $severity, $title, $messageTemplate] = match ($event->status) {
                'approved' => [
                    'CheckCircle2',
                    'success',
                    'Material Request Approved',
                    'Material Request #%d has been fully approved by %s.'
                ],
                'partially_approved' => [
                    'AlertCircle',
                    'warning',
                    'Material Request Partially Approved',
                    'Material Request #%d has been partially approved by %s. Some items were rejected.'
                ],
                'rejected' => [
                    'XCircle',
                    'error',
                    'Material Request Rejected',
                    'Material Request #%d has been rejected by %s.'
                ],
                default => [
                    'Bell',
                    'info',
                    'Material Request Updated',
                    'Material Request #%d status updated by %s.'
                ],
            };

            $approverName = $event->requestData['approved_by_name'] ?? 'an approver';

            $insertId = DB::connection('vtiger')->table('nova_notifications')->insertGetId([
                'project_id' => $event->projectId,
                'type' => 'material_request_' . $event->status,
                'title' => $title,
                'message' => sprintf(
                    $messageTemplate,
                    $event->requestId,
                    $approverName
                ),
                'icon' => $icon,
                'severity' => $severity,
                'entity_type' => 'material_request',
                'entity_id' => $event->requestId,
                'recipient_ids' => json_encode($recipients['userIds']),
                'recipient_roles' => !empty($recipients['roles']) ? json_encode($recipients['roles']) : null,
                'is_read' => false,
                'created_by' => $event->approvedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('[NotifyMaterialRequestApproved] Notification saved', [
                'notification_id' => $insertId,
                'request_id' => $event->requestId,
                'status' => $event->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('[NotifyMaterialRequestApproved] Error in listener', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $event->requestId ?? null,
            ]);
        }
    }

    /**
     * Resolve recipients for material request approval notifications.
     */
    private function resolveRecipients(int $projectId, array $requestData): array
    {
        $rolesToNotify = ['Purchasing', 'Administrator', 'Production'];

        // Get roleids from vtiger_role
        $roleIds = DB::connection('vtiger')
            ->table('vtiger_role')
            ->whereIn('rolename', $rolesToNotify)
            ->pluck('roleid')
            ->toArray();

        // Get user_ids from vtiger_user2role
        $userIdsFromRoles = [];
        if (!empty($roleIds)) {
            $userIdsFromRoles = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->whereIn('roleid', $roleIds)
                ->pluck('userid')
                ->toArray();
        }

        // Filter active users
        $activeUserIds = [];
        if (!empty($userIdsFromRoles)) {
            $activeUserIds = DB::connection('vtiger')
                ->table('vtiger_users')
                ->whereIn('id', $userIdsFromRoles)
                ->where('status', 'Active')
                ->where('deleted', 0)
                ->pluck('id')
                ->toArray();
        }

        // Add admins via is_admin field
        $adminIds = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('is_admin', 'on')
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->pluck('id')
            ->toArray();

        // Add creator of the material request
        $requestCreator = $requestData['requested_by'] ?? null;

        // Add project manager
        $projectResponsible = $this->getProjectResponsible($projectId);

        // Combine and remove duplicates
        $allUserIds = array_unique(array_filter([
            ...$activeUserIds,
            ...$adminIds,
            $requestCreator,
            $projectResponsible,
        ]));

        return [
            'userIds' => array_values($allUserIds),
            'roles' => $rolesToNotify,
        ];
    }

    /**
     * Get the project responsible (smownerid from vtiger_crmentity)
     */
    private function getProjectResponsible(int $projectId): ?int
    {
        try {
            $ownerId = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->where('setype', 'Project')
                ->where('deleted', 0)
                ->value('smownerid');

            if ($ownerId) {
                $isActive = DB::connection('vtiger')
                    ->table('vtiger_users')
                    ->where('id', $ownerId)
                    ->where('status', 'Active')
                    ->where('deleted', 0)
                    ->exists();

                return $isActive ? (int) $ownerId : null;
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Project responsible lookup skipped', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}