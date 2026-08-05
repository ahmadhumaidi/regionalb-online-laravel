<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Support\Menu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Renders a styled "coming soon" page for legacy menu items not yet ported.
 * Keeps the same role gating as the equivalent legacy sidebar entry
 * (dashboard.php:566-579), so a role that couldn't see the item in the
 * legacy nav still can't reach it directly by URL here.
 */
class PlaceholderController extends Controller
{
    public function show(string $key): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless(Menu::isAllowed($key, $user), 403);

        return view('placeholder', [
            'pageTitle' => Menu::title($key),
            'active' => $key,
        ]);
    }
}
