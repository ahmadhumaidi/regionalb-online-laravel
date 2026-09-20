<?php

namespace App\Http\Controllers;

use App\Models\RsmBadgeSetting;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\XpService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $seasonXp = XpService::getSeasonXp($user);
        $season = XpService::currentLeagueSeason();
        $league = GamificationService::leagueFor($seasonXp);
        $nextLeague = GamificationService::nextLeagueThreshold($seasonXp);
        $earnedBadgeNames = GamificationService::profileSummary($user->area ?: 'Regional B', $user)['badges'];

        return view('badges.index', [
            'active' => 'badges',
            'user' => $user,
            'seasonXp' => $seasonXp,
            'season' => $season,
            'league' => $league,
            'nextLeague' => $nextLeague,
            'earnedBadgeNames' => $earnedBadgeNames,
            'badges' => GamificationService::badgeDefinitions(),
            'indicators' => GamificationService::scoringIndicators(),
            'leagues' => [
                ['name' => 'Starter', 'threshold' => 0, 'note' => 'League awal pada setiap season.'],
                ['name' => 'Silver', 'threshold' => 500, 'note' => 'Terbuka saat XP season mencapai 500.'],
                ['name' => 'Gold', 'threshold' => 1000, 'note' => 'Terbuka saat XP season mencapai 1.000.'],
                ['name' => 'Platinum', 'threshold' => 2500, 'note' => 'Terbuka saat XP season mencapai 2.500.'],
                ['name' => 'Diamond', 'threshold' => 5000, 'note' => 'League tertinggi saat XP season mencapai 5.000.'],
            ],
            'canManageBadges' => $user->role === 'super_user',
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
        abort_unless($request->user()?->role === 'super_user', 403);

        $keys = collect(GamificationService::badgeDefinitions())->pluck('key')->all();
        $indicatorKeys = array_keys(GamificationService::scoringIndicators());
        $rules = [];
        foreach ($keys as $key) {
            $rules["settings.$key.indicator_key"] = ['required', 'string', 'in:'.implode(',', $indicatorKeys)];
            $rules["settings.$key.target_value"] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);
        foreach ((array) ($data['settings'] ?? []) as $key => $value) {
            RsmBadgeSetting::updateOrCreate(
                ['badge_key' => $key],
                [
                    'indicator_key' => (string) $value['indicator_key'],
                    'target_value' => (float) $value['target_value'],
                    'updated_by_user_id' => $request->user()->id,
                    'updated_by_name' => $request->user()->name,
                ]
            );
        }

        return redirect()->route('badges')->with('status', 'Ketentuan badge berhasil diperbarui.');
    }
}
