<?php

namespace App\Services\Dashboard;

use App\Models\RsmBadgeSetting;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Ports rsm_gamification_summary() (rsm_db.php:6887) for the "Arena
 * Performa Staff" panel. Live per-staff report aggregates are merged with
 * Collab-sourced registrasi/herregistrasi (matched by staff name — the
 * legacy NIK/name lookup collapses to a name match here since rsm_reports
 * has no NIK column) to compute points and badges.
 *
 * Simplification vs. legacy: the extra per-role rollup rows legacy builds
 * purely so a koordinator/senior viewer can see their own aggregate rank
 * are not built here — `my_rank` is only populated for an actual staff
 * report author, not a supervisor's rolled-up total.
 */
class GamificationService
{
    private const STATUS_APPROVED = ['Diverifikasi', 'Disetujui', 'Disetujui Senior Manager', 'Selesai', 'Berjalan'];

    /** Ad efficiency indicators only count spend once the koordinator wilayah has verified the report ("Diverifikasi" or later). */
    private const AD_KOORDINATOR_VERIFIED_STATUSES = ['diverifikasi', 'selesai'];

    /** The achievable badges scoreRow()/badges() can award - excludes the "On Progress" fallback shown when none are earned yet. */
    public const BADGE_NAMES = [
        'Closing Hunter', 'Herregistrasi Champion', 'Kampus Growth', 'Kampus Herreg Champion',
        'Efisiensi Iklan', 'Closing Iklan Hunter', 'Budget Efficient', 'Follow Up Hero',
        'Activity Helper', 'Consistency Streak', 'Share FB Booster', 'Live Streamer',
        'Affiliator Mahasiswa', 'Affiliator Non Mahasiswa',
    ];

    private const BADGE_DEFAULTS = [
        'closing_hunter' => ['name' => 'Closing Hunter', 'target' => 3, 'metric_key' => 'registrasi_personal', 'source' => 'Reg / Closing Personal Per Regional dari Collab.', 'tone' => 'green'],
        'herregistrasi_champion' => ['name' => 'Herregistrasi Champion', 'target' => 1, 'metric_key' => 'herregistrasi_personal', 'source' => 'Herreg Personal Per Regional dari Collab.', 'tone' => 'purple'],
        'kampus_growth' => ['name' => 'Kampus Growth', 'target' => 5, 'metric_key' => 'registrasi_kampus', 'source' => 'Reg Kampus dari Closing Kampus Regional.', 'tone' => 'green'],
        'kampus_herreg_champion' => ['name' => 'Kampus Herreg Champion', 'target' => 2, 'metric_key' => 'herregistrasi_kampus', 'source' => 'Herreg Kampus dari Herreg Kampus Regional.', 'tone' => 'purple'],
        'efisiensi_iklan' => ['name' => 'Efisiensi Iklan', 'target' => 4000, 'metric_key' => 'cpm_cpl', 'source' => 'CPM atau CPL sesuai tujuan iklan.', 'tone' => 'orange'],
        'closing_iklan_hunter' => ['name' => 'Closing Iklan Hunter', 'target' => 1, 'metric_key' => 'closing_iklan', 'source' => 'Closing dari data hasil iklan.', 'tone' => 'green'],
        'budget_efficient' => ['name' => 'Budget Efficient', 'target' => 1, 'indicator_key' => 'reg', 'source' => 'Indikator pilihan pada periode/filter.', 'tone' => 'red'],
        'follow_up_hero' => ['name' => 'Follow Up Hero', 'target' => 10, 'metric_key' => 'follow_up_total', 'source' => 'FU / follow_up_total dari data lead dan laporan.', 'tone' => 'blue'],
        'activity_helper' => ['name' => 'Activity Helper', 'target' => 2, 'metric_key' => 'aktivitas_lain_total', 'source' => 'Jumlah laporan Aktivitas Lain pada periode/filter.', 'tone' => 'blue'],
        'consistency_streak' => ['name' => 'Consistency Streak', 'target' => 5, 'metric_key' => 'hari_aktif', 'source' => 'Jumlah hari unik dari report_date laporan.', 'tone' => 'orange'],
        'share_fb_booster' => ['name' => 'Share FB Booster', 'target' => 10, 'metric_key' => 'share_fb_group', 'source' => 'Share FB Group dari Collab.', 'tone' => 'blue'],
        'live_streamer' => ['name' => 'Live Streamer', 'target' => 2, 'metric_key' => 'live_streaming', 'source' => 'Live Streaming dari Collab.', 'tone' => 'red'],
        'affiliator_mahasiswa' => ['name' => 'Affiliator Mahasiswa', 'target' => 1, 'metric_key' => 'affiliator_mahasiswa', 'source' => 'Affiliator Mahasiswa dari Collab.', 'tone' => 'green'],
        'affiliator_non_mahasiswa' => ['name' => 'Affiliator Non Mahasiswa', 'target' => 1, 'metric_key' => 'affiliator_non_mahasiswa', 'source' => 'Affiliator Non Mahasiswa dari Collab.', 'tone' => 'green'],
    ];

    /** @return list<array{key: string, name: string, indicator_key: string, indicator_label: string, target_value: float, condition: string, source: string, tone: string}> */
    public static function badgeDefinitions(): array
    {
        $settings = self::badgeSettings();
        $indicators = self::scoringIndicators();

        return collect(self::BADGE_DEFAULTS)->map(function (array $meta, string $key) use ($settings, $indicators) {
            $defaultIndicatorKey = self::defaultIndicatorKey($meta, $indicators);
            $indicatorKey = (string) ($settings[$key]['indicator_key'] ?? $defaultIndicatorKey);
            if (! array_key_exists($indicatorKey, $indicators)) {
                $indicatorKey = $defaultIndicatorKey;
            }
            $indicatorLabel = (string) ($indicators[$indicatorKey]['label'] ?? $meta['name']);
            $target = (float) ($settings[$key]['target_value'] ?? $meta['target']);
            $direction = (string) ($indicators[$indicatorKey]['direction'] ?? 'higher');

            return [
                'key' => $key,
                'name' => $meta['name'],
                'indicator_key' => $indicatorKey,
                'indicator_label' => $indicatorLabel,
                'target_value' => $target,
                'condition' => self::conditionFor($indicatorLabel, $target, $direction),
                'source' => self::sourceForIndicator($indicatorKey, $indicators, $meta),
                'tone' => $meta['tone'],
            ];
        })->values()->all();
    }

    public static function build(string $area, array $filters, RsmUser $user): array
    {
        [, $scoredRows] = self::scoredRows($area, $filters, $user);
        $badgesByName = $scoredRows->keyBy(fn (array $row) => mb_strtolower(trim((string) $row['name'])));

        $leaderboard = collect(ScoringTableService::build($area, $filters, $user)['rows'])
            ->filter(fn (array $row) => trim($row['name']) !== '' && $row['name'] !== '-')
            ->filter(fn (array $row) => (float) ($row['total_weight'] ?? 0) > 0)
            ->map(function (array $row) use ($badgesByName) {
                $legacy = $badgesByName->get(mb_strtolower(trim((string) $row['name'])));

                return array_merge($row, [
                    'points' => (float) ($row['total_score'] ?? 0),
                    'badges' => self::badgesFromScoringRow($row, $legacy['badges'] ?? []),
                ]);
            })
            ->sortBy([['total_score', 'desc'], ['name', 'asc']])
            ->values();

        $myRank = $leaderboard->first(fn (array $row) => ($row['user_id'] ?? null) === $user->id)
            ?? $leaderboard->first(fn (array $row) => mb_strtolower(trim($row['name'])) === mb_strtolower(trim((string) $user->name)));

        return [
            'leaderboard' => $leaderboard->take(5)->values()->all(),
            'my_rank' => $myRank,
            'challenge' => ['items' => self::challengeItems()],
            'point_rules' => self::pointRules(),
        ];
    }

    /**
     * Single source of truth for the "Level"/"XP"/"League" numbers shown on
     * the Profile page - same point formula and badge thresholds as the
     * Dashboard's "Arena Performa Staff" leaderboard, just scoped
     * differently per the viewer's role (ReportScope already does the
     * scoping work for us via scoredRows() -> ScopedReports):
     * - staff: their own row only (ReportScope already narrows visible
     *   reports to their own campus/work).
     * - koordinator: their wilayah's staff pooled together (ReportScope
     *   widens visible reports to the whole wilayah for this role).
     * - everyone else (senior tier, mentor): the whole area pooled
     *   together (ReportScope leaves reports unscoped for these roles).
     *
     * @return array{points: int, badges: list<string>}
     */
    public static function profileSummary(string $area, RsmUser $user): array
    {
        [$reports, $scoredRows] = self::scoredRows($area, DashboardFilters::allTime(), $user);

        if ($user->role === RsmUser::ROLE_STAFF) {
            $mine = $scoredRows->first(fn (array $row) => $row['user_id'] === $user->id)
                ?? $scoredRows->first(fn (array $row) => mb_strtolower(trim($row['name'])) === mb_strtolower(trim((string) $user->name)))
                ?? [];

            return [
                'points' => (int) ($mine['points'] ?? 0),
                'badges' => $mine['badges'] ?? ['On Progress'],
                'badge_progress' => self::badgeProgressFor($mine),
            ];
        }

        $reportDays = $reports->pluck('report_date')->filter()->map(fn ($date) => $date->toDateString())->unique()->count();
        $pooledRow = [
            'registrasi_personal' => (float) $scoredRows->sum('registrasi_personal'),
            'herregistrasi_personal' => (float) $scoredRows->sum('herregistrasi_personal'),
            'cpm_cpl' => (float) $scoredRows->avg('cpm_cpl'),
            'closing_iklan' => (float) $scoredRows->sum('closing_iklan'),
            'follow_up_total' => (float) $scoredRows->sum('follow_up_total'),
            'leads_total' => (float) $scoredRows->sum('leads_total'),
            'laporan_total' => (float) $scoredRows->sum('laporan_total'),
            'aktivitas_lain_total' => (float) $scoredRows->sum('aktivitas_lain_total'),
            'hari_aktif' => $reportDays,
        ];

        return [
            'points' => (int) $scoredRows->sum('points'),
            'badges' => self::badgesFromScoringRow($pooledRow),
            'badge_progress' => self::badgeProgressFor($pooledRow),
        ];
    }

    /**
     * @return array{level: int, level_progress: int, league: string}
     *
     * @deprecated Kept for backward compatibility (delegates to
     * XpService::calculateLevel() for the level curve instead of the old
     * flat "200 XP = 1 level" rule). ProfileController now calls
     * XpService::calculateLevel() directly for the richer breakdown
     * (xp_into_level/xp_needed/etc); this wrapper is for any other caller
     * that only needs the level/progress/league summary.
     */
    public static function levelFor(int $points): array
    {
        $calc = XpService::calculateLevel($points);

        return [
            'level' => $calc['level'],
            'level_progress' => $calc['progress_percent'],
            'league' => self::leagueFor($points),
        ];
    }

    /** League thresholds - unchanged from the original levelFor(); Phase 1 only swaps the XP input source (lifetime ledger instead of live-recalculated points), not the League tiers themselves. */
    public static function leagueFor(int $points): string
    {
        return match (true) {
            $points >= 5000 => 'Diamond',
            $points >= 2500 => 'Platinum',
            $points >= 1000 => 'Gold',
            $points >= 500 => 'Silver',
            default => 'Starter',
        };
    }

    /**
     * The next League tier's XP threshold above the given lifetime XP, or
     * null once already at the top tier (Diamond) - reuses leagueFor()'s
     * exact thresholds so this stays in sync if they ever change. Purely a
     * display helper for "X XP menuju League Y" progress text.
     *
     * @return array{name: string, threshold: int}|null
     */
    public static function nextLeagueThreshold(int $points): ?array
    {
        foreach (['Silver' => 500, 'Gold' => 1000, 'Platinum' => 2500, 'Diamond' => 5000] as $name => $threshold) {
            if ($points < $threshold) {
                return ['name' => $name, 'threshold' => $threshold];
            }
        }

        return null;
    }

    /**
     * Personal-only point total for the lifetime XP ledger (Gamification
     * Phase 1) - deliberately does NOT go through ScopedReports/ReportScope,
     * since that widens visibility to a koordinator's whole wilayah or a
     * senior's whole area for *viewing* reports, which would leak the
     * team's pooled activity into what's supposed to be one person's XP.
     * Reuses the same personal-attribution filter ProfileController already
     * applies to a staff member's own "Ringkasan" stats (user_id or
     * staff_name match), just applied regardless of role - a koordinator/
     * senior with no personal report activity of their own legitimately
     * gets 0 here, by design (team performance stays a separate metric).
     */
    public static function personalProfileXp(RsmUser $user): int
    {
        $area = $user->area ?: 'Regional B';
        $reports = RsmReport::query()
            ->where('area', $area)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('staff_name', $user->name);
            })
            ->with('adLeads')
            ->get();

        if ($reports->isEmpty()) {
            return 0;
        }

        $rawStatuses = $reports->flatMap(fn (RsmReport $report) => $report->adLeads->pluck('closing_status'))
            ->filter(fn ($value) => trim((string) $value) !== '');
        $buckets = ClosingStatusClassifier::buckets($rawStatuses);
        $rows = self::aggregateByStaff($reports, $buckets);

        $collabPerformance = CollabMetricsService::personalPerformance($area, DashboardFilters::allTime(), $user);
        $collabByName = collect($collabPerformance['rows'])->keyBy(fn ($row) => mb_strtolower(trim((string) $row['name'])));

        return (int) $rows->map(fn (array $row) => self::scoreRow($row, $collabByName))->sum('points');
    }

    /**
     * Personal-activity XP entry point for the ONE-TIME legacy backfill
     * command only (gamification:backfill-xp) - full historical formula, so
     * a brand-new ledger's opening balance reflects everything that
     * happened before real-time events existed. Dispatches by role since
     * "personal" means something different for a staff member (their own
     * report authorship) than for a koordinator/senior (their team's
     * average output, since their actual job is monitoring/approving
     * rather than filing reports themselves).
     *
     * NOT used by the ongoing daily sync anymore (Gamification Phase 2) -
     * see profileSyncXp() for that.
     */
    public static function profileActivityXp(RsmUser $user): int
    {
        return $user->role === RsmUser::ROLE_STAFF
            ? self::personalProfileXp($user)
            : self::averageTeamProfileXp($user);
    }

    /**
     * Gamification Phase 2: entry point for XpService::syncPersonalActivity()
     * (the ongoing daily reconciliation), NOT the same formula as
     * profileActivityXp(). Report/lead-driven components (report_total,
     * approved_reports, leads_total, follow_up_total, closing_iklan,
     * complete_follow_up_notes) are now awarded in real time at their
     * authoritative mutation points (XpService::syncReportEventXp(), called
     * from ReportFormService, ReportStatusController, AdBudgetActionController,
     * ObstacleFollowUpController, and AdLeadImportService) - if the daily
     * sync also recomputed those, every report/lead would earn XP twice.
     * Staff's sync therefore only tracks the remainder: registrasi/herreg,
     * which come from Collab (an external periodic sync with no per-event
     * mutation point in this codebase to hook a real-time award to).
     * Koordinator/senior tier are unaffected - their XP is an all-or-nothing
     * team average with no per-event equivalent, so they keep using the
     * full pooled formula here, same as backfill.
     */
    public static function profileSyncXp(RsmUser $user): int
    {
        return $user->role === RsmUser::ROLE_STAFF
            ? self::personalRegistrasiHerregXp($user)
            : self::averageTeamProfileXp($user);
    }

    /**
     * Registrasi/herreg-only slice of the point formula - see profileSyncXp()
     * for why this excludes everything report/lead-driven. Prefers Collab
     * data exactly like scoreRow()'s leaderboard formula: a staff member
     * with ANY Collab row (even a zero one) is scored from Collab alone. A
     * staff member with NO Collab row at all - never synced, or not yet
     * matched by name - falls back to their own ad_leads-derived registrasi/
     * herreg count instead of silently earning 0 for this slice, mirroring
     * scoreRow()'s `$collab['registrasi'] ?? $row['registrasi_total']`
     * fallback.
     */
    public static function personalRegistrasiHerregXp(RsmUser $user): int
    {
        $area = $user->area ?: 'Regional B';
        $collabPerformance = CollabMetricsService::personalPerformance($area, DashboardFilters::allTime(), $user);
        $collabRow = collect($collabPerformance['rows'])
            ->first(fn (array $r) => mb_strtolower(trim((string) $r['name'])) === mb_strtolower(trim((string) $user->name)));

        if ($collabRow !== null) {
            $registrasi = (float) ($collabRow['registrasi'] ?? 0);
            $herreg = (float) ($collabRow['herregistrasi'] ?? 0);

            return (int) round($registrasi * 20 + $herreg * 35);
        }

        $reports = RsmReport::query()
            ->where('area', $area)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('staff_name', $user->name);
            })
            ->with('adLeads')
            ->get();

        if ($reports->isEmpty()) {
            return 0;
        }

        $rawStatuses = $reports->flatMap(fn (RsmReport $report) => $report->adLeads->pluck('closing_status'))
            ->filter(fn ($value) => trim((string) $value) !== '');
        $buckets = ClosingStatusClassifier::buckets($rawStatuses);
        $rows = self::aggregateByStaff($reports, $buckets);

        $registrasi = (float) $rows->sum('registrasi_total');
        $herreg = (float) $rows->sum('herregistrasi_total');

        return (int) round($registrasi * 20 + $herreg * 35);
    }

    /**
     * Which RsmUser a report's XP should be credited to - prefers the
     * report's own user_id, falls back to a staff_name match scoped to the
     * report's area (mirrors the attribution ReportScope/personalProfileXp()
     * already use). Only ever returns a staff-role user, since real-time
     * per-report XP is a personal-authorship concept - a report authored by
     * or attributed to a koordinator/senior doesn't have an individual to
     * credit (their XP is a team average, computed separately).
     */
    public static function resolveStaffAuthor(RsmReport $report): ?RsmUser
    {
        if ($report->user_id) {
            $user = RsmUser::find($report->user_id);
            if ($user && $user->role === RsmUser::ROLE_STAFF) {
                return $user;
            }
        }

        $name = trim((string) $report->staff_name);
        if ($name === '') {
            return null;
        }

        return RsmUser::query()
            ->where('role', RsmUser::ROLE_STAFF)
            ->where('area', $report->area)
            ->where('name', $name)
            ->first();
    }

    /**
     * Koordinator/senior tier XP: their team's pooled point total (same
     * all-time, role-widened scope ReportScope already applies - wilayah
     * for koordinator, whole area for senior tier and above) averaged
     * across the number of active staff in that scope. A koordinator
     * managing 10 staff who together closed 100 registrations earns less
     * personal credit per head (10) than one managing 2 staff with the
     * same team total (50) - an average, not the raw pooled sum, so team
     * size doesn't by itself inflate or deflate a koordinator's XP.
     */
    public static function averageTeamProfileXp(RsmUser $user): int
    {
        $area = $user->area ?: 'Regional B';
        [, $scoredRows] = self::scoredRows($area, DashboardFilters::allTime(), $user);
        $pooledPoints = (int) $scoredRows->sum('points');

        $staffQuery = RsmUser::query()->where('role', RsmUser::ROLE_STAFF)->where('area', $area)->where('is_active', true);
        if ($user->role === RsmUser::ROLE_KOORDINATOR && trim((string) $user->regional) !== '') {
            $staffQuery->where('regional', $user->regional);
        }
        $staffCount = $staffQuery->count();

        return $staffCount > 0 ? (int) round($pooledPoints / $staffCount) : 0;
    }

    /**
     * The raw per-staff indicator breakdown (report counts, leads, follow
     * ups, registrasi/herreg, ad spend, etc.) behind build()/
     * profileSummary(), without the points/leaderboard framing - used by
     * ScoringTableService to lay every indicator out as its own column.
     *
     * @return Collection<int, array>
     */
    public static function indicatorRows(string $area, array $filters, RsmUser $user): Collection
    {
        [, $rows] = self::scoredRows($area, $filters, $user);

        return $rows;
    }

    /** @return array{0: Collection<int, RsmReport>, 1: Collection<int, array>} */
    private static function scoredRows(string $area, array $filters, RsmUser $user): array
    {
        $reports = ScopedReports::query($area, $filters, $user)->with('adLeads')->get();

        $rawStatuses = $reports->flatMap(fn (RsmReport $report) => $report->adLeads->pluck('closing_status'))
            ->filter(fn ($value) => trim((string) $value) !== '');
        $buckets = ClosingStatusClassifier::buckets($rawStatuses);

        $liveRows = self::aggregateByStaff($reports, $buckets);

        $collabPerformance = CollabMetricsService::personalPerformance($area, $filters, $user);
        $collabByName = collect($collabPerformance['rows'])->keyBy(fn ($row) => mb_strtolower(trim((string) $row['name'])));

        return [$reports, $liveRows->map(fn (array $row) => self::scoreRow($row, $collabByName))];
    }

    /** @return Collection<int, array> */
    private static function aggregateByStaff(Collection $reports, array $buckets): Collection
    {
        return $reports
            ->groupBy(fn (RsmReport $report) => ($report->user_id ?: 0).':'.mb_strtolower(trim((string) ($report->staff_name ?: $report->created_by_name ?: ''))))
            ->map(function (Collection $groupReports) use ($buckets) {
                $first = $groupReports->first();
                $label = $first->staff_name ?: $first->created_by_name ?: '-';

                $leads = 0;
                $followUp = 0;
                $registrasi = 0;
                $herreg = 0;
                $completeFollowUpNotes = 0;
                $otherReports = 0;
                $spend = 0.0;
                $adLeads = 0;
                $adClosing = 0;
                $impressions = 0;
                $leadGoalSpend = 0.0;
                $leadGoalLeads = 0;
                $impressionGoalSpend = 0.0;
                $impressionGoalImpressions = 0;

                foreach ($groupReports as $report) {
                    $leadRows = $report->adLeads;

                    if ($leadRows->isNotEmpty()) {
                        $leads += $leadRows->count();
                        $followUp += $leadRows->filter(fn ($lead) => filled($lead->follow_up_result) || filled($lead->progress_status))->count();
                        $registrasi += $leadRows->filter(fn ($lead) => in_array(mb_strtolower(trim((string) $lead->closing_status)), $buckets['registrasi'], true))->count();
                        $herreg += $leadRows->filter(fn ($lead) => in_array(mb_strtolower(trim((string) $lead->closing_status)), $buckets['herreg'], true))->count();
                        $completeFollowUpNotes += $leadRows->filter(fn ($lead) => filled($lead->follow_up_result) && filled($lead->notes))->count();
                    } else {
                        $leads += (int) $report->leads_count;
                        $registrasi += (int) $report->closing_count;
                    }

                    if ($report->report_type === RsmReport::TYPE_OTHER) {
                        $otherReports++;
                    }

                    if ($report->report_type === RsmReport::TYPE_ADS) {
                        $adStatus = mb_strtolower(trim((string) $report->status));
                        if (in_array($adStatus, self::AD_KOORDINATOR_VERIFIED_STATUSES, true)) {
                            $reportAdLeads = $leadRows->isNotEmpty() ? $leadRows->count() : (int) $report->leads_count;
                            $reportAdClosing = $leadRows->isNotEmpty()
                                ? $leadRows->filter(fn ($lead) => in_array(mb_strtolower(trim((string) $lead->closing_status)), $buckets['registrasi'], true))->count()
                                : (int) $report->closing_count;
                            $spend += (float) $report->realization_amount;
                            $impressions += (int) $report->impressions_count;
                            $adLeads += $reportAdLeads;
                            $adClosing += $reportAdClosing;
                            if (self::usesCplMetric($report, $reportAdLeads)) {
                                $leadGoalSpend += (float) $report->realization_amount;
                                $leadGoalLeads += $reportAdLeads;
                            } else {
                                $impressionGoalSpend += (float) $report->realization_amount;
                                $impressionGoalImpressions += (int) $report->impressions_count;
                            }
                        }
                    }
                }

                $cpmCpl = match (true) {
                    $leadGoalSpend > 0 && $leadGoalLeads > 0 => round($leadGoalSpend / $leadGoalLeads, 2),
                    $impressionGoalSpend > 0 && $impressionGoalImpressions > 0 => round(($impressionGoalSpend / $impressionGoalImpressions) * 1000, 2),
                    default => 0.0,
                };

                return [
                    'user_id' => $first->user_id ?: null,
                    'name' => $label,
                    'wilayah' => $first->wilayah,
                    'unit_name' => $first->unit_name,
                    'report_total' => $groupReports->count(),
                    'report_days' => $groupReports->pluck('report_date')->filter()->map(fn ($date) => $date->toDateString())->unique()->count(),
                    'approved_reports' => $groupReports->whereIn('status', self::STATUS_APPROVED)->count(),
                    'aktivitas_lain_total' => $otherReports,
                    'leads_total' => $leads,
                    'follow_up_total' => $followUp,
                    'registrasi_total' => $registrasi,
                    'herregistrasi_total' => $herreg,
                    'spend_total' => $spend,
                    'impressions_total' => $impressions,
                    'ad_leads_total' => $adLeads,
                    'ad_closing_total' => $adClosing,
                    'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : 0.0,
                    'cpl_iklan' => $adLeads > 0 ? round($spend / $adLeads, 2) : 0.0,
                    'cpm_cpl' => $cpmCpl,
                    'closing_iklan' => $adClosing,
                    'complete_follow_up_notes' => $completeFollowUpNotes,
                ];
            })
            ->values();
    }

    private static function usesCplMetric(RsmReport $report, int $adLeads): bool
    {
        $goal = mb_strtolower(trim((string) $report->ad_goal));

        return $adLeads > 0 || str_contains($goal, 'lead') || str_contains($goal, 'prospek') || str_contains($goal, 'ctwa');
    }

    private static function scoreRow(array $row, Collection $collabByName): array
    {
        $collab = $collabByName->get(mb_strtolower(trim($row['name'])));

        $closingForPoints = $collab['registrasi'] ?? $row['registrasi_total'];
        $herregForPoints = $collab['herregistrasi'] ?? $row['herregistrasi_total'];

        $points = $row['report_total'] * 5
            + $row['approved_reports'] * 10
            + $row['leads_total'] * 2
            + $row['follow_up_total'] * 4
            + $closingForPoints * 20
            + $herregForPoints * 35
            + $row['closing_iklan'] * 10
            + $row['complete_follow_up_notes'] * 5;

        $scoredRow = array_merge($row, [
            'closing_for_points' => $closingForPoints,
            'herreg_for_points' => $herregForPoints,
            'registrasi_personal' => (float) $closingForPoints,
            'herregistrasi_personal' => (float) $herregForPoints,
            'laporan_total' => (float) $row['report_total'],
            'aktivitas_lain_total' => (float) $row['aktivitas_lain_total'],
            'hari_aktif' => (float) $row['report_days'],
            'points' => (int) round($points),
        ]);

        return array_merge($scoredRow, [
            'badges' => self::badgesFromScoringRow($scoredRow),
        ]);
    }

    /** @return list<string> */
    private static function badges(float $followUp, float $closing, float $herreg, int $reportDays, float $spend): array
    {
        $badges = [];
        $thresholds = self::badgeThresholds();

        if ($followUp >= (float) $thresholds['follow_up_hero']) {
            $badges[] = 'Follow Up Hero';
        }
        if ($closing >= (float) $thresholds['closing_hunter']) {
            $badges[] = 'Closing Hunter';
        }
        if ($herreg >= (float) $thresholds['herregistrasi_champion']) {
            $badges[] = 'Herregistrasi Champion';
        }
        if ($reportDays >= (float) $thresholds['consistency_streak']) {
            $badges[] = 'Consistency Streak';
        }
        if ($spend > 0 && $closing >= (float) $thresholds['budget_efficient']) {
            $badges[] = 'Budget Efficient';
        }

        return $badges === [] ? ['On Progress'] : $badges;
    }

    /** @return list<string> */
    private static function badgesFromScoringRow(array $row, array $fallbackBadges = []): array
    {
        $badges = array_column(array_filter(self::badgeProgressFor($row), fn (array $b) => $b['achieved']), 'name');

        if ($badges === [] && $fallbackBadges !== []) {
            $badges = array_values(array_filter($fallbackBadges, fn (string $badge) => $badge !== 'On Progress'));
        }

        return $badges === [] ? ['On Progress'] : $badges;
    }

    /**
     * Full per-badge breakdown (actual/target/achieved, not just the
     * achieved names badgesFromScoringRow() reduces this down to) for one
     * scored row - same settings/indicators/actual/target/achieved
     * computation either way, just exposing the whole thing for progress
     * displays (Profile page badge cards).
     *
     * @return list<array{key: string, name: string, tone: string, indicator_label: string, target: float, actual: float, direction: string, achieved: bool, condition: string}>
     */
    public static function badgeProgressFor(array $row): array
    {
        $settings = self::badgeSettings();
        $indicators = self::scoringIndicators();
        $result = [];

        foreach (self::BADGE_DEFAULTS as $key => $meta) {
            $indicatorKey = (string) ($settings[$key]['indicator_key'] ?? self::defaultIndicatorKey($meta, $indicators));
            $metricKey = (string) ($indicators[$indicatorKey]['metric_key'] ?? $meta['metric_key'] ?? '');
            if ($metricKey === '') {
                continue;
            }
            $actual = (float) ($row[$metricKey] ?? 0);
            $target = (float) ($settings[$key]['target_value'] ?? $meta['target']);
            $indicatorLabel = (string) ($indicators[$indicatorKey]['label'] ?? $meta['name']);
            $direction = (string) ($indicators[$indicatorKey]['direction'] ?? 'higher');
            $achieved = $direction === 'lower'
                ? ($target > 0 && $actual > 0 && $actual <= $target)
                : ($actual >= $target);

            $result[] = [
                'key' => $key,
                'name' => $meta['name'],
                'tone' => $meta['tone'],
                'indicator_label' => $indicatorLabel,
                'target' => $target,
                'actual' => $actual,
                'direction' => $direction,
                'achieved' => $achieved,
                'condition' => self::conditionFor($indicatorLabel, $target, $direction),
            ];
        }

        return $result;
    }

    /** @return array<string, float> */
    private static function badgeThresholds(): array
    {
        return collect(self::BADGE_DEFAULTS)
            ->mapWithKeys(fn (array $meta, string $key) => [$key => (float) (self::badgeSettings()[$key]['target_value'] ?? $meta['target'])])
            ->all();
    }

    /** @return array<string, array{indicator_key?: string, target_value: float}> */
    private static function badgeSettings(): array
    {
        $settings = collect(self::BADGE_DEFAULTS)
            ->mapWithKeys(fn (array $meta, string $key) => [$key => ['target_value' => (float) $meta['target']]])
            ->all();

        if (! Schema::hasTable('rsm_badge_settings')) {
            return $settings;
        }

        $hasIndicatorKey = Schema::hasColumn('rsm_badge_settings', 'indicator_key');
        RsmBadgeSetting::query()->get(['badge_key', 'target_value', ...($hasIndicatorKey ? ['indicator_key'] : [])])->each(function (RsmBadgeSetting $setting) use (&$settings, $hasIndicatorKey) {
            if (array_key_exists($setting->badge_key, $settings)) {
                $settings[$setting->badge_key]['target_value'] = (float) $setting->target_value;
                if ($hasIndicatorKey && filled($setting->indicator_key)) {
                    $settings[$setting->badge_key]['indicator_key'] = (string) $setting->indicator_key;
                }
            }
        });

        return $settings;
    }

    /** @return array<string, array> */
    public static function scoringIndicators(): array
    {
        return (array) config('scoring_indicators.indicators', []);
    }

    private static function defaultIndicatorKey(array $meta, array $indicators): string
    {
        if (isset($meta['indicator_key']) && array_key_exists((string) $meta['indicator_key'], $indicators)) {
            return (string) $meta['indicator_key'];
        }

        $metricKey = (string) ($meta['metric_key'] ?? '');
        foreach ($indicators as $key => $indicator) {
            if ((string) ($indicator['metric_key'] ?? '') === $metricKey) {
                return (string) $key;
            }
        }

        return (string) array_key_first($indicators);
    }

    private static function sourceForIndicator(string $indicatorKey, array $indicators, array $meta): string
    {
        $indicator = $indicators[$indicatorKey] ?? null;
        if (! is_array($indicator)) {
            return (string) $meta['source'];
        }

        $group = (string) ($indicator['group'] ?? 'Indikator');

        return "Indikator {$indicator['label']} dari grup {$group}.";
    }

    private static function conditionFor(string $indicatorLabel, float $target, string $direction = 'higher'): string
    {
        $formatted = number_format($target, 0, ',', '.');

        $prefix = $direction === 'lower' ? 'Maksimal' : 'Minimal';

        return "{$prefix} {$formatted} {$indicatorLabel} dalam periode/filter yang dipilih.";
    }

    /** @return list<string> */
    private static function challengeItems(): array
    {
        return [
            'Kirim minimal 1 laporan setiap hari kerja.',
            'Follow up minimal 10 lead dalam sebulan.',
            'Catat hasil follow up lengkap di setiap lead.',
            'Bantu closing minimal 3 registrasi dalam sebulan.',
        ];
    }

    /** @return list<string> */
    private static function pointRules(): array
    {
        return [
            '+5 poin per laporan terkirim',
            '+10 poin per laporan disetujui',
            '+2 poin per lead',
            '+4 poin per follow up',
            '+20 poin per registrasi',
            '+35 poin per herregistrasi',
            '+10 poin per closing iklan',
            '+5 poin per catatan follow up lengkap',
        ];
    }
}
