<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own in-app notifications (FR-019). Every query goes
 * through $request->user()->notifications(), so nobody can read or mark
 * another user's notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $onlyUnread = $request->query('filter') === 'unread';

        $notifications = $request->user()
            ->notifications()
            ->when($onlyUnread, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'onlyUnread' => $onlyUnread,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification as read and open the item it points to.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only follow links into this application.
        if (is_string($url) && str_starts_with($url, url('/'))) {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
