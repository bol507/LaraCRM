<?php
// app/Http/Controllers/Api/NotificationController.php
namespace App\Http\Controllers\Api;

use App\Application\UseCases\Role\GetUserRoleUseCase;
use App\Http\Controllers\Controller;
use App\Services\CurrentUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct(
        private readonly GetUserRoleUseCase $getUserRoles,
    ) {}

    /**
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = CurrentUserService::idOr(1);
        $projectId = $request->integer('project_id');
        $unreadOnly = $request->boolean('unread_only');
        $page = max(1, $request->integer('page', 1));
        $limit = min(50, max(1, $request->integer('limit', 20)));

        $query = DB::connection('vtiger')->table('nova_notifications')
            ->where(function ($q) use ($userId) {
                // User is in recipient_ids OR has a role in recipient_roles
                $q->whereJsonContains('recipient_ids', $userId)
                    ->orWhere(function ($sub) use ($userId) {
                        // Get user roles and check against recipient_roles
                        $userRoles = $this->getUserRoles->execute($userId);
                        foreach ($userRoles as $role) {
                            $sub->orWhereJsonContains('recipient_roles', $role);
                        }
                    });
            });

        if ($projectId) $query->where('project_id', $projectId);
        if ($unreadOnly) $query->where('is_read', false);

        $total = (clone $query)->count();
        $unreadCount = (clone $query)->where('is_read', false)->count();

        $notifications = $query->orderByDesc('created_at')
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->get()
            ->toArray();

        return response()->json([
            'data' => $notifications,
            'meta' => [
                'total' => $total,
                'unread_count' => $unreadCount,
                'per_page' => $limit,
                'current_page' => $page,
            ]
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Mark a single notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $userId = CurrentUserService::idOr(1);

        // Verify that the user is a recipient
        $notification = DB::connection('vtiger')->table('nova_notifications')->find($id);
        
        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }
        
        $recipientIds = json_decode($notification->recipient_ids, true);
        if (!in_array($userId, $recipientIds)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        DB::connection('vtiger')->table('nova_notifications')
            ->where('id', $id)
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * POST /api/notifications/mark-all-read
     * Mark all notifications for the user as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = CurrentUserService::idOr(1);
        $projectId = $request->integer('project_id');

        $query = DB::connection('vtiger')->table('nova_notifications')
            ->where('is_read', false)
            ->where(function ($q) use ($userId) {
                $q->whereJsonContains('recipient_ids', $userId);
                
                // Role-based filtering similar to index()
                $userRoles = $this->getUserRoles->execute($userId);
                foreach ($userRoles as $role) {
                    $q->orWhereJsonContains('recipient_roles', $role);
                }
            });

        if ($projectId) $query->where('project_id', $projectId);

        $query->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(int $id): JsonResponse
    {
        $userId = CurrentUserService::idOr(1);
        
        $notification = DB::connection('vtiger')->table('nova_notifications')->find($id);
        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        // Verificar autorización
        $recipientIds = json_decode($notification->recipient_ids ?? '[]');
        if (!in_array($userId, $recipientIds) && !$this->isAdmin($userId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::connection('vtiger')->table('nova_notifications')->where('id', $id)->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    

    private function isAdmin(int $userId): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $userId)
            ->where('is_admin', 'on')
            ->exists();
    }
}