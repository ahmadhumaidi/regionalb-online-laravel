<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmDailyMissionClaim;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\CoordinatorLiburService;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\XpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * Koordinator's Daily Mission is a separate scheme from staff's: Follow
     * Up/Share FB are wilayah-wide (target scales with active campus
     * count), and "Laporan Hasil Kunjungan" replaces "Closing Reg" (a
     * koordinator doesn't personally close registrations, they file a
     * visit report - see koordinatorDailyMissions()). Login and Aktivitas
     * Lain are unchanged from the staff scheme, same keys/rewards, so they
     * intentionally reuse those entries below rather than duplicating them
     * under a "kor_" key.
     */
    private const KOORDINATOR_MISSION_REWARDS = [
        'login' => ['energy' => 10, 'stars' => 15],
        'kor_fu' => ['energy' => 25, 'stars' => 25],
        'kor_share_fb' => ['energy' => 20, 'stars' => 20],
        'aktivitas_lain' => ['energy' => 15, 'stars' => 15],
        'kor_kunjungan' => ['energy' => 40, 'stars' => 35],
    ];

    private const DAILY_CHEST_TIERS = [65, 120, 165, 220];

    private const WEEKLY_CHEST_TIERS = [330, 660, 990, 1320];

    private const MONTHLY_CHEST_TIERS = [1500, 3000, 4500, 6000];

    /**
     * Badge & Achievement card icons, keyed by GamificationService's badge
     * key - placeholders from the existing icon library
     * (resources/views/components/icon.blade.php) until dedicated badge
     * artwork is ready.
     */
    private const BADGE_ICONS = [
        'closing_hunter' => 'flag',
        'herregistrasi_champion' => 'shield',
        'kampus_growth' => 'chart-bar',
        'kampus_herreg_champion' => 'user-group',
        'efisiensi_iklan' => 'currency',
        'closing_iklan_hunter' => 'bolt',
        'budget_efficient' => 'briefcase',
        'follow_up_hero' => 'chat',
        'activity_helper' => 'document',
        'consistency_streak' => 'calendar',
        'share_fb_booster' => 'cloud',
        'live_streamer' => 'photo',
        'affiliator_mahasiswa' => 'users',
        'affiliator_non_mahasiswa' => 'user-group',
    ];

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

        // Badge thresholds still come from the Dashboard's "Arena Performa
        // Staff" formula/scope (GamificationService::profileSummary()) -
        // staff see their own badges, koordinator/senior tier see their
        // team's pooled badges. Unchanged by the Gamification Phase 1 XP
        // ledger refactor below.
        $gamification = GamificationService::profileSummary($user->area ?: 'Regional B', $user);

        // Gamification Phase 1: Level/XP/League now read from the lifetime
        // XP ledger (rsm_gamification_transactions via XpService) instead
        // of a live recalculation - syncPersonalActivity() banks any growth
        // since the user's last profile visit (idempotent, at most once per
        // day), then the ledger's lifetime sum drives the level curve. XP
        // means their own report authorship for staff, but their team's
        // average output for koordinator/senior tier (their job is
        // monitoring/approving, not filing reports themselves) - see
        // GamificationService::profileActivityXp(). Badges above still use
        // profileSummary()'s pooled team total, unchanged.
        XpService::syncPersonalActivity($user);
        $xp = XpService::getLifetimeXp($user);
        $levelInfo = XpService::calculateLevel($xp);
        $level = $levelInfo['level'];
        $levelProgress = $levelInfo['progress_percent'];
        $league = GamificationService::leagueFor($xp);
        // Placeholder icons (existing icon library) until dedicated badge
        // artwork is ready - swap self::BADGE_ICONS's values then, nothing
        // else about this mapping needs to change.
        $badges = array_map(function (array $badge) {
            $percent = 0;
            if ($badge['target'] > 0) {
                // "lower is better" badges (e.g. CPM/CPL efficiency) invert
                // the ratio - being further under target means more filled,
                // not less, and 0 actual (no data yet) reads as 0% instead
                // of a division-by-zero "full bar".
                $percent = $badge['direction'] === 'lower'
                    ? ($badge['actual'] > 0 ? min(100, ($badge['target'] / $badge['actual']) * 100) : 0)
                    : min(100, ($badge['actual'] / $badge['target']) * 100);
            }

            return [
                'key' => $badge['key'],
                'name' => $badge['name'],
                'tone' => $badge['tone'],
                'icon' => self::BADGE_ICONS[$badge['key']] ?? 'trophy',
                'condition' => $badge['condition'],
                'actual' => $badge['actual'],
                'target' => $badge['target'],
                'progress_percent' => (int) round($percent),
                'ok' => $badge['achieved'],
            ];
        }, $gamification['badge_progress']);
        $todayEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)->whereDate('claim_date', now()->toDateString())->sum('energy');
        $weekEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereBetween('claim_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->sum('energy');
        $monthEnergy = (int) RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereBetween('claim_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('energy');
        // Aura ("Skor Performa"): now Energy-based, not report/lead/closing
        // volume - Daily Mission's monthly chest tops out at 6000 energy
        // (self::MONTHLY_CHEST_TIERS), so /60 maps a maxed-out month to
        // exactly 100. Use ceil() for positive energy so a small claim like
        // Login (10 energy) immediately moves Aura from 0 to 1 instead of
        // looking like it had no effect. Energy stays its own currency (separate from the
        // XP ledger even though claiming also banks XP 1:1 - see
        // claimMission()) - this just reads the same monthEnergy total the
        // chest progress bar below already uses, not a new source.
        $score = $monthEnergy > 0 ? min(100, (int) ceil($monthEnergy / 60)) : 0;
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
        if ($user->role === 'koordinator') {
            return $this->koordinatorDailyMissions($user);
        }
        if ($user->role !== 'staff') {
            return [];
        }

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
            ->get();

        $otherActivities = $dailyReports->where('report_type', RsmReport::TYPE_OTHER)->count();
        // Sourced from the /sumber-collab "Follow Up BDC" sync, same as
        // share_fb/reg below - not the local ad_leads follow-up log, which
        // tracks a different activity (in-app follow-up notes) and never
        // reaches the tier targets (30/45/60) this mission is scaled for.
        $followUps = CollabMetricsService::personalTotal($filters, $area, $user, 'Follow Up BDC');
        $shareFb = CollabMetricsService::personalTotal($filters, $area, $user, 'Share FB Group');
        $registrasi = CollabMetricsService::personalTotal($filters, $area, $user, 'Closing Personal Per Regional');

        $claimedToday = RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereDate('claim_date', $today)
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

    /**
     * Koordinator's Daily Mission: Login/Aktivitas Lain are identical to
     * the staff scheme (their own report authorship); Follow Up/Share FB
     * are wilayah-wide (every staff report today under their regional, not
     * just their own) with a target that scales with how many campuses are
     * actually active today (jumlah unit − libur hari ini); "Laporan Hasil
     * Kunjungan" replaces "Closing Reg" and checks whether today's
     * coordinator-schedule visit was marked Selesai.
     */
    private function koordinatorDailyMissions(RsmUser $user): array
    {
        $area = $user->area ?: 'Regional B';
        $today = now()->toDateString();
        $regional = trim((string) $user->regional);

        $unitCount = $regional !== '' ? DB::table('partner_campuses')->where('wilayah', $regional)->count() : 0;
        $activeUnits = max(0, $unitCount - $this->coordinatorLiburToday($user));

        $otherActivities = RsmReport::query()
            ->where('area', $area)
            ->whereDate('report_date', $today)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('staff_name', $user->name))
            ->where('report_type', RsmReport::TYPE_OTHER)
            ->count();

        $filters = ['date_from' => $today, 'date_to' => $today, 'wilayah' => '', 'unit_name' => '', 'staff_name' => ''];
        // Regional-wide total (all staff under this koordinator), same
        // Collab source ("Follow Up BDC") the staff-level 'fu' mission uses.
        $followUps = CollabMetricsService::personalTotal($filters, $area, $user, 'Follow Up BDC');
        $shareFb = CollabMetricsService::personalTotal($filters, $area, $user, 'Share FB Group');

        $visitReportDone = RsmCoordinatorSchedule::query()
            ->where('koordinator_user_id', $user->id)
            ->whereDate('schedule_date', $today)
            ->where('status', 'Selesai')
            ->exists();

        $claimedToday = RsmDailyMissionClaim::where('user_id', $user->id)
            ->whereDate('claim_date', $today)
            ->pluck('mission_key')
            ->all();

        $missions = [
            ['key' => 'login', 'label' => 'Login', 'actual' => 1, 'target' => 1],
            ['key' => 'kor_fu', 'label' => 'Follow Up', 'actual' => $followUps, 'target' => 30 * $activeUnits],
            ['key' => 'kor_share_fb', 'label' => 'Share FB', 'actual' => $shareFb, 'target' => 3 * $activeUnits],
            ['key' => 'aktivitas_lain', 'label' => 'Aktivitas Lain', 'actual' => $otherActivities, 'target' => 1],
            ['key' => 'kor_kunjungan', 'label' => 'Laporan Hasil Kunjungan', 'actual' => $visitReportDone ? 1 : 0, 'target' => 1],
        ];

        return array_map(function (array $mission) use ($claimedToday) {
            $reward = self::KOORDINATOR_MISSION_REWARDS[$mission['key']];
            $target = $mission['target'];

            return [
                'key' => $mission['key'],
                'label' => $mission['label'],
                'tier' => null,
                'energy' => $reward['energy'],
                'stars' => $reward['stars'],
                'claimed' => in_array($mission['key'], $claimedToday, true),
                'actual' => $mission['actual'],
                'target' => $target,
                'progress' => $target > 0 ? min(100, round(($mission['actual'] / $target) * 100)) : 0,
                // target > 0 guard: a wilayah with no partner_campuses data
                // (or 0 active units today) would otherwise divide/compare
                // against 0 and read as instantly "done", handing out a
                // reward for a mission that was never actually achievable.
                'done' => $target > 0 && $mission['actual'] >= $target,
            ];
        }, $missions);
    }

    /** Whether TODAY is a day off for this koordinator, per Jadwal Personalia (CoordinatorLiburService) - the visit schedule generates at most 1 unit/day, so this is 0 or 1. */
    private function coordinatorLiburToday(RsmUser $user): int
    {
        $map = CoordinatorLiburService::map();
        if ($map['month'] === '' || $map['month'] !== now()->format('Y-m')) {
            return 0;
        }

        $day = (int) now()->format('j');

        return isset($map['coordinators'][$user->name][$day]) ? 1 : 0;
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

        $claim = RsmDailyMissionClaim::create([
            'user_id' => $user->id,
            'mission_key' => $missionKey,
            'claim_date' => now()->toDateString(),
            'energy' => $mission['energy'],
            'stars' => $mission['stars'],
        ]);

        // Daily Mission-XP unification: claiming a mission also banks XP
        // into the lifetime ledger (1 energy = 1 XP), on top of whatever
        // per-event XP the underlying activity already earned in real time
        // (e.g. Follow Up leads already award lead_follow_up XP as they're
        // filled in) - an intentional daily-quest bonus layer, not a
        // duplicate of that per-event XP. Keyed off the claim row's own id,
        // which the (user_id, mission_key, claim_date) unique constraint on
        // rsm_daily_mission_claims already guarantees is one-per-day.
        XpService::awardXp(
            user: $user,
            eventType: 'daily_mission_claim',
            xp: $mission['energy'],
            sourceType: 'daily_mission_claim',
            sourceId: $claim->id,
            reason: 'Misi harian diklaim: '.$mission['label'],
        );

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
