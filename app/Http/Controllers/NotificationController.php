<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    private function viewer(): array
    {
        return [
            'healthUnitId' => session('health_unit_id'),
            'userId'       => session('user_id'),
            'role'         => session('role'),
        ];
    }

    private function checkAccess()
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (!in_array(session('role'), ['admin', 'staff', 'midwife'])) {
            return redirect()->route('login');
        }
        return null;
    }

    public function unreadCount(Request $request): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        return response()->json([
            'count' => $this->notificationService->getUnreadCount(
                $v['healthUnitId'], $v['userId'], $v['role']
            ),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();
        $limit = $request->input('limit', 20);

        $notifications = $this->notificationService->getRecentNotifications(
            $v['healthUnitId'], $v['userId'], $v['role'], $limit
        );

        return response()->json([
            'notifications' => $notifications->map(function ($n) {
                $url = $n->link
                    ?? ($n->inventory_id
                        ? route('inventory.index', ['highlight' => $n->inventory_id])
                        : '#');
                return [
                    'id'         => $n->id,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'severity'   => $n->severity,
                    'is_read'    => $n->is_read,
                    'created_at' => $n->created_at->diffForHumans(),
                    'url'        => $url,
                ];
            }),
            'unread_count' => $this->notificationService->getUnreadCount(
                $v['healthUnitId'], $v['userId'], $v['role']
            ),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        return response()->json([
            'success' => $this->notificationService->markAsRead(
                $id, $v['healthUnitId'], $v['userId'], $v['role']
            ),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        return response()->json([
            'success' => true,
            'count'   => $this->notificationService->markAllAsRead(
                $v['healthUnitId'], $v['userId'], $v['role']
            ),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        $deleted = $this->notificationService->destroyById(
            $id, $v['healthUnitId'], $v['userId'], $v['role']
        );

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Notification deleted.' : 'Notification not found.',
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        $deleted = $this->notificationService->destroyAll(
            $v['healthUnitId'], $v['userId'], $v['role']
        );

        return response()->json([
            'success' => true,
            'count'   => $deleted,
            'message' => $deleted . ' notification(s) deleted.',
        ]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        $success = $this->notificationService->archive(
            $id, $v['healthUnitId'], $v['userId'], $v['role']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification archived.' : 'Notification not found.',
        ]);
    }

    public function unarchive(Request $request, int $id): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        $success = $this->notificationService->unarchive(
            $id, $v['healthUnitId'], $v['userId'], $v['role']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification restored.' : 'Notification not found.',
        ]);
    }

    public function destroyArchived(Request $request): JsonResponse
    {
        if (!$request->expectsJson()) abort(404);
        $v = $this->viewer();

        $count = $this->notificationService->destroyArchived(
            $v['healthUnitId'], $v['userId'], $v['role']
        );

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => $count . ' archived notification(s) deleted.',
        ]);
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) return $redirect;

        $v = $this->viewer();
        $view = $request->get('view', 'active');

        $notifications = $this->notificationService->getNotifications(
            $v['healthUnitId'], $v['userId'], $v['role'], $view, 50
        );
        $unreadCount   = $this->notificationService->getUnreadCount(
            $v['healthUnitId'], $v['userId'], $v['role']
        );
        $archivedCount = $this->notificationService->getArchivedCount(
            $v['healthUnitId'], $v['userId'], $v['role']
        );

        return view('notifications.index', compact(
            'notifications', 'unreadCount', 'archivedCount', 'view'
        ));
    }
}