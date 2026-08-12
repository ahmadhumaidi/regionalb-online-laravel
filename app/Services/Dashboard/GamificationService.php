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
        'Report Consistent', 'Consistency Streak', 'Share FB Booster', 'Live Streamer',
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
        'report_consistent' => ['name' => 'Report Consistent', 'target' => 5, 'metric_key' => 'laporan_total', 'source' => 'Total laporan pada periode/filter.', 'tone' => 'orange'],
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
                ?? $scoredRows->first(fn (array $row) => mb_strtolower(trim($row['name'])) === mb_strtolower(trim((string) $user->name)));

            return [
                'points' => (int) ($mine['points'] ?? 0),
                'badges' => $mine['badges'] ?? ['On Progress'],
            ];
        }

        $reportDays = $reports->pluck('report_date')->filter()->map(fn ($date) => $date->toDateString())->unique()->count();

        return [
            'points' => (int) $scoredRows->sum('points'),
            'badges' => self::badgesFromScoringRow([
                'registrasi_personal' => (float) $scoredRows->sum('registrasi_personal'),
                'herregistrasi_personal' => (float) $scoredRows->sum('herregistrasi_personal'),
                'cpm_cpl' => (float) $scoredRows->avg('cpm_cpl'),
                'closing_iklan' => (float) $scoredRows->sum('closing_iklan'),
                'follow_up_total' => (float) $scoredRows->sum('follow_up_total'),
                'leads_total' => (float) $scoredRows->sum('leads_total'),
                'laporan_total' => (float) $scoredRows->sum('laporan_total'),
                'hari_aktif' => $reportDays,
            ]),
        ];
    }

    /** @return array{level: int, level_progress: int, league: string} */
    public static function levelFor(int $points): array
    {
        $level = max(1, (int) floor($points / 200) + 1);
        $levelBase = ($level - 1) * 200;
        $levelProgress = min(100, (int) round((($points - $levelBase) / 200) * 100));
        $league = match (true) {
            $points >= 5000 => 'Diamond',
            $points >= 2500 => 'Platinum',
            $points >= 1000 => 'Gold',
            $points >= 500 => 'Silver',
            default => 'Starter',
        };

        return ['level' => $level, 'level_progress' => $levelProgress, 'league' => $league];
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
        $badges = [];
        $settings = self::badgeSettings();
        $indicators = self::scoringIndicators();

        foreach (self::BADGE_DEFAULTS as $key => $meta) {
            $indicatorKey = (string) ($settings[$key]['indicator_key'] ?? self::defaultIndicatorKey($meta, $indicators));
            $metricKey = (string) ($indicators[$indicatorKey]['metric_key'] ?? $meta['metric_key'] ?? '');
            if ($metricKey === '') {
                continue;
            }
            $actual = (float) ($row[$metricKey] ?? 0);
            $target = (float) ($settings[$key]['target_value'] ?? $meta['target']);
            $direction = (string) ($indicators[$indicatorKey]['direction'] ?? 'higher');
            $achieved = $direction === 'lower'
                ? ($target > 0 && $actual > 0 && $actual <= $target)
                : ($actual >= $target);

            if ($achieved) {
                $badges[] = $meta['name'];
            }
        }

        if ($badges === [] && $fallbackBadges !== []) {
            $badges = array_values(array_filter($fallbackBadges, fn (string $badge) => $badge !== 'On Progress'));
        }

        return $badges === [] ? ['On Progress'] : $badges;
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
