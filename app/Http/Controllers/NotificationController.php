<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One controller for both buyers and vendors — a notification only ever belongs to a User, exactly
 * like the Support Ticket controller. Admins get the equivalent via Filament's own built-in
 * database-notifications bell (see AdminPanelProvider), not this controller.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'vendor' => $request->user()->vendor,
        ]);
    }

    /**
     * Polled by NotificationBell.jsx every 30s for the dropdown — deliberately small and fast
     * rather than reusing index()'s full paginated query.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(8)->get(),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
