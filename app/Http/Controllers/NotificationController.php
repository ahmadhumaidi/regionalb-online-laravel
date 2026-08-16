<?php

namespace App\Http\Controllers;

use App\Models\RsmNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function open(RsmNotification $notification): RedirectResponse
    {
        abort_unless($notification->recipient_user_id === Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        if ($notification->report_id) {
            return redirect()->route('reports.show', $notification->report_id);
        }

        if ($notification->forum_post_id) {
            return redirect()->to(route('forum', ['post' => $notification->forum_post_id]).'#forum-post-'.$notification->forum_post_id);
        }

        return redirect()->route('dashboard');
    }

    public function readAll(): RedirectResponse
    {
        RsmNotification::where('recipient_user_id', Auth::id())
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('notice', 'Semua notifikasi ditandai terbaca.');
    }
}
