<?php

namespace App\View\Components\Layouts;

use App\Models\RsmNotification;
use App\Models\RsmUser;
use App\Support\Menu;
use App\Support\RsmRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

class App extends Component
{
    /** @var \Illuminate\Support\Collection<int, RsmUser> */
    public $impersonationUsers;

    /** @var list<array{title: string, items: list<array{key: string, label: string, icon: string}>}> */
    public $menuSections;

    public RsmUser $user;

    public int $unreadNotificationCount;

    /** @var \Illuminate\Support\Collection<int, RsmNotification> */
    public $recentNotifications;

    public function __construct(
        public string $title,
        public string $active = '',
        public string $eyebrow = '',
    ) {
        /** @var RsmUser $user */
        $user = Auth::user();

        $this->user = $user;
        $this->eyebrow = str_replace('Regional B', 'RSM B', $eyebrow !== '' ? $eyebrow : ($user->area ?: 'Regional B'));
        $this->menuSections = Menu::sections($user);
        $this->impersonationUsers = RsmRole::canImpersonate($user)
            ? RsmUser::where('is_active', true)
                ->where(fn ($q) => $q->where('area', $user->area)->orWhereNull('area')->orWhere('area', ''))
                ->get()
                ->sortBy([
                    fn (RsmUser $a, RsmUser $b) => RsmRole::roleRank($a->role) <=> RsmRole::roleRank($b->role),
                    fn (RsmUser $a, RsmUser $b) => strcasecmp((string) $a->name, (string) $b->name),
                ])
                ->values()
            : collect();
        if (Schema::hasTable('rsm_notifications')) {
            $this->unreadNotificationCount = RsmNotification::where('recipient_user_id', $user->id)->unread()->count();
            $this->recentNotifications = RsmNotification::where('recipient_user_id', $user->id)->latest()->limit(10)->get();
        } else {
            $this->unreadNotificationCount = 0;
            $this->recentNotifications = collect();
        }
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
