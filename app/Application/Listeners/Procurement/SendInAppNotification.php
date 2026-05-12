<?php
// app/Application/Listeners/Procurement/SendInAppNotification.php

namespace App\Application\Listeners\Procurement;

use App\Domain\Events\MaterialRequestCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendInAppNotification
{
    public function handle(MaterialRequestCreated $event): void
    {
        try {
            // Determine recipients: project managers + specific roles
            $recipients = $this->resolveRecipients($event->projectId, $event->requestData);
            
            DB::connection('vtiger')->table('nova_notifications')->insert([
                'project_id' => $event->projectId,
                'type' => 'material_request_created',
                'title' => 'New Material Request',
                'message' => sprintf(
                    '%s created a material request with %d item(s)',
                    $event->requestData['created_by_name'] ?? 'A user',
                    $event->requestData['items_count'] ?? 0,
                    $event->requestData['total_estimated'] ?? 0
                ),
                'icon' => 'Package',
                'severity' => 'info',
                'entity_type' => 'material_request',
                'entity_id' => $event->materialRequestId,
                'recipient_ids' => json_encode($recipients['userIds']),
                'recipient_roles' => json_encode($recipients['roles']),
                'is_read' => false,
                'created_by' => $event->createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('vtiger')->getPdo()->lastInsertId();

           
        } catch (\JsonException $e) {
            Log::error('❌ JSON encode failed', [
                'error' => $e->getMessage(),
                'data' => ['recipients' => $recipients ?? null],
            ]);
            // No relanzar para no romper el flujo principal
        } catch (\Throwable $e) {
            Log::error('❌ Listener fatal error', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'event_id' => $event->materialRequestId,
            ]);
            
        }
    }

    private function resolveRecipients(int $projectId, array $requestData): array
    {
        $rolesToNotify = ['CEO', 'Jefe de Planta', 'Asistente de Ingenieria'];

        // Get user_ids of users with those roles (adjust according to your user/role table schema)
        // ✅ Paso 1: Obtener roleids de vtiger_role por rolename
        $roleIds = DB::connection('vtiger')
            ->table('vtiger_role')
            ->whereIn('rolename', $rolesToNotify)
            ->pluck('roleid')
            ->toArray();

        // ✅ Paso 2: Obtener user_ids de vtiger_user2role para esos roleids
        $userIdsFromRoles = [];
        if (!empty($roleIds)) {
            $userIdsFromRoles = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->whereIn('roleid', $roleIds)
                ->pluck('userid')
                ->toArray();
        }

        // ✅ Paso 3: Filtrar solo usuarios activos en vtiger_users
        $activeUserIds = [];
        if (!empty($userIdsFromRoles)) {
            $activeUserIds = DB::connection('vtiger')
                ->table('vtiger_users')
                ->whereIn('id', $userIdsFromRoles)
                ->where('status', 'Active') // ← VTiger usa 'Active', no 1/0
                ->where('deleted', 0)
                ->pluck('id')
                ->toArray();
        }

         $adminIds = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('is_admin', 'on') // ← VTiger usa 'on'/'off', no boolean
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->pluck('id')
            ->toArray();
        $projectResponsible = !empty($requestData['project_responsible_id']) 
            ? [(int) $requestData['project_responsible_id']] 
            : [];
        $allUserIds = array_unique(array_merge(
            $activeUserIds, 
            $adminIds, 
            $projectResponsible
        ));

        Log::debug('🔍 resolveRecipients debug', [
            'roles_queried' => $rolesToNotify,
            'roleids_found' => $roleIds,
            'users_from_roles' => $userIdsFromRoles,
            'active_users' => $activeUserIds,
            'admin_users' => $adminIds,
            'project_responsible' => $projectResponsible,
            'final_recipients' => $allUserIds,
        ]);

        return [
            'userIds' => array_values($allUserIds),
            'roles' => $rolesToNotify,
        ];
    }
}
