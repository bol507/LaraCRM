<?php
// app/Application/Listeners/Procurement/NotifyPOCreated.php

namespace App\Application\Listeners\Procurement;

use App\Domain\Events\PurchaseOrderCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyPOCreated
{
    /**
     * Handle the PurchaseOrderCreated event.
     * 
     * Creates an in-app notification for:
     * - The creator of the source material request
     * - Users with Purchasing/Administrator roles
     * - The project manager
     */
    public function handle(PurchaseOrderCreated $event): void
    {
        try {
            Log::debug('[NotifyPOCreated] Listener triggered', [
                'po_id' => $event->purchaseOrderId,
                'project_id' => $event->projectId,
                'quote_id' => $event->quoteId,
            ]);

            $recipients = $this->resolveRecipients($event->projectId, $event->requestData);
            
            if (empty($recipients['userIds'])) {
                Log::warning('[NotifyPOCreated] No recipients found for PO creation notification', [
                    'po_id' => $event->purchaseOrderId,
                ]);
                return;
            }

            Log::debug('[NotifyPOCreated] Recipients for PO notification', [
                'userIds' => $recipients['userIds'],
                'roles' => $recipients['roles'],
            ]);

            // Insert notification into database
            $insertId = DB::connection('vtiger')->table('nova_notifications')->insertGetId([
                'project_id' => $event->projectId,
                'type' => 'purchase_order_created',
                'title' => 'Purchase Order Generated',
                'message' => sprintf(
                    'PO %s created for %s for $%.2f (%d item(s)). Ready for review and sending to vendor.',
                    $event->requestData['po_number'] ?? '#N/A',
                    $event->requestData['vendor_name'] ?? 'a vendor',
                    $event->requestData['total_amount'] ?? 0,
                    $event->requestData['items_count'] ?? 0
                ),
                'icon' => 'Package',
                'severity' => 'success',
                'entity_type' => 'purchase_order',
                'entity_id' => $event->purchaseOrderId,
                'recipient_ids' => json_encode($recipients['userIds']),
                'recipient_roles' => !empty($recipients['roles']) ? json_encode($recipients['roles']) : null,
                'is_read' => false,
                'created_by' => $event->createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('[NotifyPOCreated] PO creation notification saved', [
                'notification_id' => $insertId,
                'po_id' => $event->purchaseOrderId,
            ]);

        } catch (\Throwable $e) {
            Log::error('[NotifyPOCreated] Error in listener', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'po_id' => $event->purchaseOrderId ?? null,
            ]);
            // Do NOT rethrow to avoid breaking the main PO creation flow
        }
    }

    /**
     * Resolve recipients for PO creation notifications.
     * 
     * Includes:
     * - Creator of the source material request (if exists)
     * - Users with Purchasing/Administrator roles
     * - Project manager
     */
    private function resolveRecipients(int $projectId, array $requestData): array
    {
        $rolesToNotify = ['Purchasing', 'Administrator'];
        
        // Step 1: Get roleids from vtiger_role
        $roleIds = DB::connection('vtiger')
            ->table('vtiger_role')
            ->whereIn('rolename', $rolesToNotify)
            ->pluck('roleid')
            ->toArray();

        // Step 2: Get user_ids from vtiger_user2role
        $userIdsFromRoles = [];
        if (!empty($roleIds)) {
            $userIdsFromRoles = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->whereIn('roleid', $roleIds)
                ->pluck('userid')
                ->toArray();
        }

        // Step 3: Filter active users
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

        // Step 4: Add admins via is_admin field
        $adminIds = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('is_admin', 'on')
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->pluck('id')
            ->toArray();

        // Step 5: Add creator of the source material request (if exists)
        $materialRequestCreator = !empty($requestData['material_request_id'])
            ? $this->getMaterialRequestCreator($requestData['material_request_id'])
            : null;

        // Step 6: Add project manager
        $projectResponsible = $this->getProjectResponsible($projectId);

        // Combine and remove duplicates
        $allUserIds = array_unique(array_filter([
            ...$activeUserIds,
            ...$adminIds,
            $materialRequestCreator,
            $projectResponsible,
        ]));

        return [
            'userIds' => array_values($allUserIds),
            'roles' => $rolesToNotify,
        ];
    }

    /**
     * Get the user who created the source material request
     */
    private function getMaterialRequestCreator(int $materialRequestId): ?int
    {
        return DB::connection('vtiger')
            ->table('nova_material_requests')
            ->where('id', $materialRequestId)
            ->value('requested_by'); 
    }

    /**
     * Obtener responsable del proyecto usando la tabla central de VTiger.
     * 
     * @param int $projectId
     * @return int|null
     */
    private function getProjectResponsible(int $projectId): ?int
    {
        try {
            // 🔍 En VTiger, smownerid vive en crmentity, vinculado por crmid = projectid
            $ownerId = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->where('setype', 'Project') // ← Nombre exacto del módulo en VTiger
                ->where('deleted', 0)
                ->value('smownerid');

            // Validar que el usuario exista y esté activo (opcional pero recomendado)
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
            Log::debug('️ Project responsible lookup skipped', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);
            return null; // ← Fallo seguro: no rompe el flujo de notificaciones
        }
    }
}