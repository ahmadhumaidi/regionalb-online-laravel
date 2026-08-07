<?php

namespace App\View\Components\Layouts;

use App\Models\RsmUser;
use App\Support\Menu;
use App\Support\RsmRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class App extends Component
{
    /** @var \Illuminate\Support\Collection<int, RsmUser> */
    public $impersonationUsers;

    /** @var list<array{title: string, items: list<array{key: string, label: string, icon: string}>}> */
    public $menuSections;

    public RsmUser $user;

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
            ? RsmUser::where('is_active', true)->where('area', $user->area)->orderBy('regional')->orderBy('name')->get()
            : collect();
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
