<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Marks one as read and follows its link (only to an internal URL). */
    public function open(Request $request, string $id): RedirectResponse
    {
        // Scoped through the relation, so another user's notification id is a 404.
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        // Trailing slash matters: "https://app.test.evil.com" must not pass as "https://app.test".
        $internal = is_string($url) && str_starts_with($url, rtrim(url('/'), '/').'/');

        return $internal ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
