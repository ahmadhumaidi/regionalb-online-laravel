<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmDailyMissionClaim;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Daily Mission reward catalog — a currency scoped entirely to this
     * widget (energy/stars), deliberately separate from GamificationService's
     * all-time XP/Level/League points. "Follow Up" and "Closing Reg" have
     * several tiers that unlock one at a time (must claim tier N before
     * tier N+1 appears claimable) — every other mission has a single tier.
     * Claiming every tier of every mission once yields 220 energy / 210
     * stars in one day, matching DAILY_CHEST_TIERS' last tier.
     */
    private const MISSION_TIERS = [
        'login' => [
            ['target' => 1, 'energy' => 10, 'stars' => 15],
        ],
        'fu' => [
            ['target' => 30, 'energy' => 20, 'stars' => 20],
            ['target' => 45, 'energy' => 20, 'stars' => 20],
            ['target' => 60, 'energy' => 20, 'stars' => 20],
        ],
        'share_fb' => [
            ['target' => 3, 'energy' => 15, 'stars' => 15],
        ],
        'aktivitas_lain' => [
            ['target' => 1, 'energy' => 15, 'stars' => 15],
        ],
        'reg' => [
            ['target' => 1, 'energy' => 40, 'stars' => 35],
            ['target' => 2, 'energy' => 40, 'stars' => 35],
            ['target' => 3, 'energy' => 40, 'stars' => 35],
        ],
    ];

    private const MISSION_LABELS = [
        'login' => 'Login',
        'fu' => 'Follow Up',
        'share_fb' => 'Share FB',
        'aktivitas_lain' => 'Aktivitas Lain',
        'reg' => 'Closing Reg',
    ];

    private const DAILY_CHEST_TIERS = [65, 120, 165, 220];

    private const WEEKLY_CHEST_TIERS = [330, 660, 990, 1320];

    private const MONTHLY_CHEST_TIERS = [1500, 3000, 4500, 6000];

    public function show(): View
    {
        $user = Auth::user();
        $query = RsmReport::query()->where('area', $user->area);
        if ($user->role === 'staff') {
            $query->where(function ($q) use ($user) { $q->where('user_id', $user->id)->orWhere('staff_name', $user->name); });
        } elseif ($user->role === 'koordinator' && $user->regional) {
            $query->where('wilayah', $user->regional);
        }
        $stats = [
            'reports' => (clone $query)->count(),
            'leads' => (clone $query)->sum('leads_count'),
            'closing' => (clone $query)->sum('closing_count'),
            'active_days' => (clone $query)->distinct('report_date')->count('report_date'),
        ];
        $dailyMissions = $this->dailyMissions($user);
        $reports = (clone $query)->latest('report_date')->latest('id')->limit(10)->get();
        $logs = RsmActivityLog::query()->where('area', $user->area)->where('actor_user_id', $user->id)->latest()->limit(10)->get();

        // Same point formula and badge thresholds as the Dashboard's "Arena
        // Performa Staff" leaderboard (GamificationService) - staff see
        // their own standing, koordinator/senior tier see their team's
        // pooled totals. See GamificationService::profileSummary().
        $gamification = GamificationService::profileSummary($user->area ?: 'Regional B', $user);
        $xp = $gamification['points'];
        ['level' => $level, 'level_progress' => $levelProgress, 'league' => $league] = GamificationService::levelFor($xp);
        $earnedBadges = $gamification['badges'];
        $badges = array_map(
            fn (string $name) => ['name' => $name, 'ok' => in_array($name, $earnedBadges, true)],
            GamificationService::BADGE_NAMES
        );
        $score = min(100, ($stats['reports'] * 4) + ($stats['leads'] * 2) + ($stats['closing'] * 8));

        $todayEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)->where('claim_date', now()->toDateString())->sum('energy');
        $weekEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereBetween('claim_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->sum('energy');
        $monthEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereBetween('claim_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('energy');
        $dailyChestTiers = self::DAILY_CHEST_TIERS;
        $weeklyChestTiers = self::WEEKLY_CHEST_TIERS;
        $monthlyChestTiers = self::MONTHLY_CHEST_TIERS;
        $missionResetAt = now()->endOfDay()->toIso8601String();
        $weekResetAt = now()->endOfWeek()->toIso8601String();
        $monthResetAt = now()->endOfMonth()->toIso8601String();

        return view('profile.index', compact(
            'user', 'stats', 'reports', 'logs', 'xp', 'level', 'levelProgress', 'league', 'score', 'badges', 'dailyMissions',
            'todayEnergy', 'weekEnergy', 'monthEnergy', 'dailyChestTiers', 'weeklyChestTiers', 'monthlyChestTiers',
            'missionResetAt', 'weekResetAt', 'monthResetAt'
        ));
    }

    private function dailyMissions(RsmUser $user): array
    {
        $area = $user->area ?: 'Regional B';
        $today = now()->toDateString();
        $filters = [
            'date_from' => $today,
            'date_to' => $today,
            'wilayah' => '',
            'unit_name' => '',
            'staff_name' => (string) $user->name,
        ];

        $dailyReports = RsmReport::query()
            ->where('area', $area)
            ->whereDate('report_date', $today)
            ->when($user->role === 'staff', fn ($q) => $q->where(fn ($inner) => $inner->where('user_id', $user->id)->orWhere('staff_name', $user->name)))
            ->when($user->role === 'koordinator' && $user->regional, fn ($q) => $q->where('wilayah', $user->regional))
            ->with('adLeads')
            ->get();

        $followUps = $dailyReports->sum(function (RsmReport $report): int {
            if ($report->adLeads->isEmpty()) {
                return 0;
            }

            return $report->adLeads
                ->filter(fn ($lead) => filled($lead->follow_up_result) || filled($lead->progress_status))
                ->count();
        });
        $otherActivities = $dailyReports->where('report_type', RsmReport::TYPE_OTHER)->count();
        $shareFb = CollabMetricsService::personalTotal($filters, $area, $user, 'Share FB Group');
        $registrasi = CollabMetricsService::personalTotal($filters, $area, $user, 'Closing Personal Per Regional');

        $claimedToday = RsmDailyMissionClaim::where('user_id', $user->id)
            ->where('claim_date', $today)
            ->pluck('mission_key')
            ->all();

        $actuals = [
            'login' => 1,
            'fu' => $followUps,
            'share_fb' => $shareFb,
            'aktivitas_lain' => $otherActivities,
            'reg' => $registrasi,
        ];

        $rows = [];
        foreach (self::MISSION_TIERS as $key => $tiers) {
            $rows = array_merge($rows, $this->missionTiers($key, self::MISSION_LABELS[$key], $actuals[$key], $tiers, $claimedToday));
        }

        return $rows;
    }

    /**
     * Collapses one mission's tier list down to a single row: whichever
     * tier is next up (the first one not yet claimed today), or the last
     * tier once every tier has been claimed. A mission with several tiers
     * (e.g. "fu": 30/45/60) therefore only ever occupies one row in the
     * list — claiming it swaps the row to the next tier's target/reward on
     * the next page load, rather than revealing an extra row.
     *
     * @param  list<array{target:int,energy:int,stars:int}>  $tiers
     * @param  list<string>  $claimedToday
     */
    private function missionTiers(string $key, string $label, float $actual, array $tiers, array $claimedToday): array
    {
        $multiTier = count($tiers) > 1;
        $activeIndex = 0;

        foreach ($tiers as $index => $tier) {
            $tierKey = $multiTier ? sprintf('%s_%d', $key, $index + 1) : $key;
            $activeIndex = $index;
            if (! in_array($tierKey, $claimedToday, true)) {
                break;
            }
        }

        $tier = $tiers[$activeIndex];
        $tierKey = $multiTier ? sprintf('%s_%d', $key, $activeIndex + 1) : $key;

        return [[
            'key' => $tierKey,
            'label' => $multiTier ? sprintf('%s %d', $label, $tier['target']) : $label,
            'tier' => $multiTier ? ($activeIndex + 1).'/'.count($tiers) : null,
            'energy' => $tier['energy'],
            'stars' => $tier['stars'],
            'claimed' => in_array($tierKey, $claimedToday, true),
            'actual' => $actual,
            'target' => $tier['target'],
            'progress' => $tier['target'] > 0 ? min(100, round(($actual / $tier['target']) * 100)) : 0,
            'done' => $actual >= $tier['target'],
        ]];
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['bio_text' => ['nullable', 'string', 'max:800'], 'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = Auth::user();
        if ($request->hasFile('profile_photo')) {
            if ($user->photo_path && str_starts_with($user->photo_path, 'profiles/')) Storage::disk('public')->delete($user->photo_path);
            $data['photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }
        $user->forceFill(array_filter(['bio_text' => $data['bio_text'] ?? null, 'photo_path' => $data['photo_path'] ?? null], static fn ($v) => $v !== null))->save();
        return back()->with('notice', 'Profil berhasil diperbarui.');
    }

    /** Claim today's reward for one Daily Mission tier - recomputes mission progress server-side rather than trusting the client. */
    public function claimMission(string $missionKey): RedirectResponse
    {
        $user = Auth::user();
        $mission = collect($this->dailyMissions($user))->firstWhere('key', $missionKey);

        abort_unless($mission, 404);
        abort_unless($mission['done'], 422, 'Misi ini belum selesai.');
        abort_if($mission['claimed'], 422, 'Reward misi ini sudah diklaim hari ini.');

        RsmDailyMissionClaim::create([
            'user_id' => $user->id,
            'mission_key' => $missionKey,
            'claim_date' => now()->toDateString(),
            'energy' => $mission['energy'],
            'stars' => $mission['stars'],
        ]);

        return back()->with('notice', 'Reward misi diklaim!');
    }

    public function edit(): View { return view('profile.password', ['active' => 'password']); }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['old_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:6'], 'confirm_password' => ['required', 'string']]);
        if ($data['new_password'] !== $data['confirm_password']) throw ValidationException::withMessages(['confirm_password' => 'Konfirmasi password tidak sama.']);
        $user = Auth::user();
        if (! Hash::check($data['old_password'], $user->password_hash)) throw ValidationException::withMessages(['old_password' => 'Password lama tidak sesuai.']);
        $user->forceFill(['password_hash' => Hash::make($data['new_password']), 'must_change_password' => false])->save();
        return back()->with('notice', 'Password berhasil diperbarui.');
    }

    /** Serves legacy profile photos (photo_path outside Laravel's own public disk); mirrors ReportFormController::attachment(). */
    public function photo(RsmUser $user)
    {
        abort_unless(filled($user->photo_path), 404);

        if (Storage::disk('public')->exists($user->photo_path)) {
            return Storage::disk('public')->response($user->photo_path);
        }

        $legacyRoot = config('filesystems.legacy_public_root');
        $legacyPath = $legacyRoot ? realpath($legacyRoot.'/'.$user->photo_path) : false;
        abort_unless($legacyPath && str_starts_with($legacyPath, realpath($legacyRoot)), 404);

        return response()->file($legacyPath);
    }
}
