<?php
// app/Application/Listeners/Procurement/NotifyQuoteAccepted.php

namespace App\Application\Listeners\Procurement;

use App\Domain\Events\VendorQuoteAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyQuoteAccepted
{
    /**
     * Handle the VendorQuoteAccepted event.
     * 
     * Creates an in-app notification for:
     * - The creator of the source material request
     * - Users with Purchasing/Administrator roles
     * - The project manager (if exists)
     */
    public function handle(VendorQuoteAccepted $event): void
    {
        try {

            $recipients = $this->resolveRecipients($event->projectId, $event->quoteData);

            if (empty($recipients['userIds'])) {
                Log::warning('[NotifyQuoteAccepted] No recipients found for quote acceptance notification', [
                    'quote_id' => $event->quoteId,
                ]);
                return;
            }


            // Insert notification into database
            $insertId = DB::connection('vtiger')->table('nova_notifications')->insertGetId([
                'project_id' => $event->projectId,
                'type' => 'vendor_quote_accepted',
                'title' => 'Quote Accepted',
                'message' => sprintf(
                    'Quote %s from %s for $%.2f has been accepted. Ready to generate Purchase Order.',
                    $event->quoteData['quote_number'] ?? '#N/A',
                    $event->quoteData['vendor_name'] ?? 'a vendor',
                    $event->quoteData['total_amount'] ?? 0
                ),
                'icon' => 'CheckCircle2',
                'severity' => 'success',
                'entity_type' => 'vendor_quote',
                'entity_id' => $event->quoteId,
                'recipient_ids' => json_encode($recipients['userIds']),
                'recipient_roles' => !empty($recipients['roles']) ? json_encode($recipients['roles']) : null,
                'is_read' => false,
                'created_by' => $event->acceptedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('[NotifyQuoteAccepted] Quote acceptance notification saved', [
                'notification_id' => $insertId,
                'quote_id' => $event->quoteId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[NotifyQuoteAccepted] Error in listener', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'quote_id' => $event->quoteId ?? null,
            ]);
            // Do NOT rethrow to avoid breaking the main acceptance flow
        }
    }

    /**
     * Resolve recipients for quote acceptance notifications.
     * 
     * Includes:
     * - Creator of the source material request (if exists)
     * - Users with Purchasing/Administrator roles
     * - Project manager (if defined)
     */
    private function resolveRecipients(int $projectId, array $quoteData): array
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
        $materialRequestCreator = !empty($quoteData['material_request_id'])
            ? $this->getMaterialRequestCreator($quoteData['material_request_id'])
            : null;

        // Step 6: Add project manager (optional)
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
