<?php

namespace App\Http\Controllers;

use App\Models\RsmBadgeSetting;
use App\Services\Dashboard\GamificationService;
use App\Support\RsmRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        return view('badges.index', [
            'active' => 'badges',
            'badges' => GamificationService::badgeDefinitions(),
            'canManageBadges' => RsmRole::canManageTargets($request->user()),
            'fallback' => [
                'name' => 'On Progress',
                'condition' => 'Ditampilkan ketika staff belum memenuhi syarat badge mana pun.',
                'source' => 'Status awal otomatis dari Arena Performa Staff dan Profil.',
                'tone' => 'slate',
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(RsmRole::canManageTargets($request->user()), 403);

        $keys = collect(GamificationService::badgeDefinitions())->pluck('key')->all();
        $rules = [];
        foreach ($keys as $key) {
            $rules["settings.$key"] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);
        foreach ((array) ($data['settings'] ?? []) as $key => $value) {
            RsmBadgeSetting::updateOrCreate(
                ['badge_key' => $key],
                [
                    'target_value' => (float) $value,
                    'updated_by_user_id' => $request->user()->id,
                    'updated_by_name' => $request->user()->name,
                ]
            );
        }

        return redirect()->route('badges')->with('status', 'Ketentuan badge berhasil diperbarui.');
    }
}
