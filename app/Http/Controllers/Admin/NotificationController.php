<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the topbar bell dropdown — the signed-in user's own database
 * notifications. Self-service: a user only ever sees / marks their own.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()->latest()->limit(20)->get()->map(fn ($n) => [
            'id'      => $n->id,
            'title'   => $n->data['title'] ?? '',
            'message' => $n->data['message'] ?? '',
            'icon'    => $n->data['icon'] ?? 'bell',
            'url'     => $n->data['url'] ?? null,
            'read'    => $n->read_at !== null,
            'time'    => $n->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'items'  => $items,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json([
            'ok'     => true,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true, 'unread' => 0]);
    }
}
