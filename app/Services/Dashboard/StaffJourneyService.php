<?php

namespace App\Services\Dashboard;

use App\Models\RsmCollabDailyMetric;
use App\Models\RsmReport;
use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use App\Services\PersonnelScheduleService;
use App\Support\CampusMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StaffJourneyService
{
    public const ACTIVITY_LABELS = [
        'absen' => 'Absen Masuk',
        'fu_bdc' => 'FU BDC',
        'instagram' => 'Konten Instagram',
        'facebook' => 'Konten Facebook',
        'tiktok' => 'TikTok',
        'live_day' => 'Live Streaming Day',
        'story_ig' => 'Story Instagram',
        'share_fb' => 'Share Konten Facebook',
        'laporan' => 'Laporan Aktivitas Lain',
        'live_night' => 'Live Streaming Night',
        'affiliate' => 'Sapa Grup Affiliate',
        'canvassing' => 'Canvassing',
        'brosur' => 'Sebar Brosur',
        'spanduk' => 'Spanduk Kerjasama',
    ];

    /** Sum source activity quantities within the requested dates, without journey completion thresholds or rolling windows. */
    public static function reportRows(array $roster, array $filters): array
    {
        return self::summarizeRows($roster, ...self::reportSources($roster, $filters));
    }

    private static function reportSources(array $roster, array $filters): array
    {
        $metricNames = [
            'absen' => 'Absen Tepat Waktu', 'fu_bdc' => 'Follow Up BDC',
            'live_day' => 'Live Streaming', 'share_fb' => 'Share FB Group',
            'live_night' => 'Live Streaming Night',
            'canvassing' => 'Canvasing', 'brosur' => 'Sebar Brosur', 'spanduk' => 'Pasang Spanduk',
        ];
        $metrics = RsmCollabDailyMetric::query()
            ->whereDate('metric_date', '>=', $filters['date_from'])
            ->whereDate('metric_date', '<=', $filters['date_to'])
            ->whereIn('report_name', array_values($metricNames))
            ->get(['report_name', 'staff_name', 'regional', 'value', 'metric_date']);
        $reports = RsmReport::query()
            ->whereDate('report_date', '>=', $filters['date_from'])
            ->whereDate('report_date', '<=', $filters['date_to'])
            ->where(function ($query) use ($roster): void {
                $query->whereIn('user_id', array_column($roster, 'user_id'))
                    ->orWhereIn('staff_name', array_column($roster, 'name'));
            })
            ->get(['user_id', 'staff_name', 'created_by_name', 'activity_kind', 'title', 'report_date']);
        $posts = RsmSocialPost::query()->with('account:id,unit_name')
            ->whereDate('post_date', '>=', $filters['date_from'])
            ->whereDate('post_date', '<=', $filters['date_to'])
            ->where('media_type', '!=', 'no_post')
            ->get(['id', 'account_id', 'media_type', 'post_date']);

        return [$metrics, $reports, $posts];
    }

    private static function summarizeRows(array $roster, Collection $metrics, Collection $reports, Collection $posts): array
    {
        $metricNames = [
            'absen' => 'Absen Tepat Waktu', 'fu_bdc' => 'Follow Up BDC',
            'live_day' => 'Live Streaming', 'share_fb' => 'Share FB Group',
            'live_night' => 'Live Streaming Night',
            'canvassing' => 'Canvasing', 'brosur' => 'Sebar Brosur', 'spanduk' => 'Pasang Spanduk',
        ];
        $metrics = $metrics->groupBy(fn ($row) => self::nameKey((string) $row->staff_name));
        $reports = $reports->groupBy(fn (RsmReport $report) => $report->user_id ? 'id:'.$report->user_id : 'name:'.self::nameKey((string) ($report->staff_name ?: $report->created_by_name)));

        return array_map(function (array $row) use ($metrics, $reports, $posts, $metricNames): array {
            $actuals = array_fill_keys(array_keys(self::ACTIVITY_LABELS), 0);
            $staffMetrics = $metrics->get(self::nameKey((string) $row['name']), collect());
            foreach ($metricNames as $key => $name) {
                $matching = $staffMetrics->where('report_name', $name);
                if (in_array($key, ['canvassing', 'brosur', 'spanduk', 'live_night'], true)) {
                    $matching = $matching->where('regional', $row['wilayah']);
                }
                $actuals[$key] = (float) $matching->sum('value');
            }
            $campusPosts = $posts->filter(fn (RsmSocialPost $post): bool => CampusMatcher::matches((string) ($post->account?->unit_name ?? ''), (string) $row['unit_name']));
            foreach (['instagram' => ['feed', 'reels'], 'facebook' => ['facebook'], 'tiktok' => ['tiktok'], 'story_ig' => ['story']] as $key => $types) {
                $actuals[$key] = $campusPosts->whereIn('media_type', $types)->count();
            }
            // Include both ID-linked and legacy name-linked reports once.
            $staffReports = $reports->get('id:'.$row['user_id'], collect())
                ->concat($reports->get('name:'.self::nameKey((string) $row['name']), collect()));
            $actuals['laporan'] = $staffReports->count();
            $actuals['affiliate'] = $staffReports->filter(fn (RsmReport $report): bool => self::isActivity($report, 'Sapa Grup Affiliate'))->count();

            return array_merge($row, $actuals);
        }, $roster);
    }

    private const DAILY_KEYS = ['absen', 'fu_bdc', 'instagram', 'facebook', 'tiktok', 'live_day', 'story_ig', 'share_fb', 'laporan'];

    /** Seven-day blocks starting on the first of the month; quantities match Journey source reports. */
    public static function profilePerformance(RsmUser $user, string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfDay();
        $end = $start->endOfMonth();
        $roster = [['user_id' => $user->id, 'name' => $user->name, 'wilayah' => $user->regional, 'unit_name' => $user->campus_name]];
        [$metrics, $reports, $posts] = self::reportSources($roster, ['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()]);
        // Filter once for this profile instead of repeating campus/name matching every week.
        $metrics = $metrics->filter(fn ($row) => self::nameKey((string) $row->staff_name) === self::nameKey((string) $user->name));
        $posts = $posts->filter(fn ($post) => CampusMatcher::matches((string) ($post->account?->unit_name ?? ''), (string) $user->campus_name));
        $weeks = [];
        for ($from = $start; $from->lte($end); $from = $to->addDay()) {
            $to = $from->addDays(6)->min($end->startOfDay());
            $inWeek = fn ($date): bool => CarbonImmutable::parse($date)->betweenIncluded($from, $to->endOfDay());
            $row = self::summarizeRows($roster,
                $metrics->filter(fn ($metric) => $inWeek($metric->metric_date)),
                $reports->filter(fn ($report) => $inWeek($report->report_date)),
                $posts->filter(fn ($post) => $inWeek($post->post_date)),
            )[0];
            $weeks[] = [
                'label' => $from->format('d').'–'.$to->format('d M'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'values' => array_intersect_key($row, self::ACTIVITY_LABELS),
            ];
        }

        return ['month' => $month, 'label' => $start->locale('id')->translatedFormat('F Y'), 'labels' => self::ACTIVITY_LABELS, 'weeks' => $weeks];
    }

    /** @return array{date:string,total_daily:int,tracked_daily:int,staff:list<array>} */
    public static function build(RsmUser $viewer, ?string $date = null, ?string $area = null): array
    {
        $date ??= now()->toDateString();
        $selectedDate = CarbonImmutable::parse($date);
        $staff = self::staffScope($viewer, $area)->get();
        $names = $staff->pluck('name')->filter()->values();
        $offStatuses = PersonnelScheduleService::offStatusesForDate($date);

        $periodCollab = RsmCollabDailyMetric::query()
            ->whereIn('report_name', ['Pasang Spanduk', 'Sebar Brosur', 'Canvasing', 'Live Streaming Night'])
            ->whereBetween('metric_date', [$selectedDate->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), $date])
            ->get(['report_name', 'metric_date', 'staff_name', 'regional', 'value'])
            ->groupBy(fn ($row) => self::nameKey((string) $row->staff_name));

        $collab = RsmCollabDailyMetric::query()
            ->whereDate('metric_date', $date)
            ->whereIn('report_name', ['Follow Up BDC', 'Share FB Group', 'Live Streaming', 'Absen Tepat Waktu'])
            ->get(['report_name', 'staff_name', 'value', 'synced_at'])
            ->groupBy(fn ($row) => self::nameKey((string) $row->staff_name));

        $reports = RsmReport::query()
            ->whereBetween('report_date', [$selectedDate->startOfWeek()->toDateString(), $selectedDate->endOfWeek()->toDateString()])
            ->where(function ($query) use ($staff, $names): void {
                $query->whereIn('user_id', $staff->pluck('id'))->orWhereIn('staff_name', $names);
            })
            ->get(['user_id', 'staff_name', 'created_by_name', 'report_date', 'report_type', 'title', 'activity_kind', 'created_at'])
            ->groupBy(fn (RsmReport $report) => $report->user_id ? 'id:'.$report->user_id : 'name:'.self::nameKey((string) ($report->staff_name ?: $report->created_by_name)));

        $posts = RsmSocialPost::query()
            ->with('account:id,unit_name')
            ->whereDate('post_date', $date)
            ->where('media_type', '!=', 'no_post')
            ->get(['id', 'account_id', 'media_type', 'post_time', 'created_at']);

        return [
            'date' => $date,
            'total_daily' => count(self::DAILY_KEYS),
            'tracked_daily' => 9,
            'staff' => $staff->map(fn (RsmUser $user): array => self::staffRow($user, $date, $collab, $reports, $posts, $offStatuses, $periodCollab))->values()->all(),
        ];
    }

    /** @return list<array{id:int,name:string,unit:string,campus:string,regional:string,area:string,avatar:string,avatar_fallback:string,activity_total:int}> */
    public static function leaderboard(RsmUser $viewer, string $dateFrom, string $dateTo, ?string $area = null): array
    {
        // Expanded access is deliberately limited to leaderboard summaries.
        $staff = $area === 'Regional B' && $viewer->role === RsmUser::ROLE_KOORDINATOR
            ? RsmUser::query()->where('role', RsmUser::ROLE_STAFF)->where('is_active', true)->where('area', 'Regional B')->orderBy('name')->get()
            : self::staffScope($viewer)->get();
        $names = $staff->pluck('name')->filter()->values();
        $metrics = RsmCollabDailyMetric::query()
            ->whereDate('metric_date', '>=', $dateFrom)
            ->whereDate('metric_date', '<=', $dateTo)
            ->whereIn('report_name', ['Follow Up BDC', 'Share FB Group', 'Live Streaming', 'Absen Tepat Waktu'])
            ->get(['report_name', 'staff_name', 'metric_date', 'value'])
            ->groupBy(fn ($row) => self::nameKey((string) $row->staff_name));
        $reports = RsmReport::query()
            ->whereDate('report_date', '>=', $dateFrom)
            ->whereDate('report_date', '<=', $dateTo)
            ->where(function ($query) use ($staff, $names): void {
                $query->whereIn('user_id', $staff->pluck('id'))->orWhereIn('staff_name', $names);
            })
            ->get(['user_id', 'staff_name', 'created_by_name', 'report_date'])
            ->groupBy(fn (RsmReport $report) => $report->user_id ? 'id:'.$report->user_id : 'name:'.self::nameKey((string) ($report->staff_name ?: $report->created_by_name)));
        $posts = RsmSocialPost::query()
            ->with('account:id,unit_name')
            ->whereDate('post_date', '>=', $dateFrom)
            ->whereDate('post_date', '<=', $dateTo)
            ->where('media_type', '!=', 'no_post')
            ->get(['id', 'account_id', 'post_date', 'media_type']);

        return $staff->map(function (RsmUser $user) use ($metrics, $reports, $posts): array {
            $activityTotal = 0;
            foreach ($metrics->get(self::nameKey((string) $user->name), collect())->groupBy('metric_date') as $dailyMetrics) {
                $totals = $dailyMetrics->groupBy('report_name')->map->sum('value');
                $activityTotal += (int) (($totals['Absen Tepat Waktu'] ?? 0) >= 1);
                $activityTotal += (int) (($totals['Follow Up BDC'] ?? 0) >= 30);
                $activityTotal += (int) (($totals['Live Streaming'] ?? 0) >= 1);
                $activityTotal += (int) (($totals['Share FB Group'] ?? 0) >= 3);
            }

            $campusPosts = $posts->filter(fn (RsmSocialPost $post): bool => CampusMatcher::matches((string) ($post->account?->unit_name ?? ''), (string) $user->campus_name));
            foreach ($campusPosts->groupBy(fn (RsmSocialPost $post) => optional($post->post_date)->toDateString()) as $dailyPosts) {
                $activityTotal += (int) $dailyPosts->whereIn('media_type', ['feed', 'reels'])->isNotEmpty();
                $activityTotal += (int) $dailyPosts->where('media_type', 'facebook')->isNotEmpty();
                $activityTotal += (int) $dailyPosts->where('media_type', 'tiktok')->isNotEmpty();
                $activityTotal += (int) $dailyPosts->where('media_type', 'story')->isNotEmpty();
            }

            $staffReports = $reports->get('id:'.$user->id)
                ?? $reports->get('name:'.self::nameKey((string) $user->name))
                ?? collect();
            $activityTotal += $staffReports->groupBy(fn (RsmReport $report) => optional($report->report_date)->toDateString())->count();
            $avatarFallback = self::avatarFallback((string) $user->name);

            return [
                'id' => $user->id,
                'name' => (string) $user->name,
                'area' => (string) ($user->area ?: '-'),
                'unit' => (string) ($user->campus_name ?: 'Unit belum diatur'),
                'campus' => (string) ($user->campus_name ?: 'Kampus belum diatur'),
                'regional' => (string) ($user->regional ?: '-'),
                'avatar' => $user->photoUrl() ?: $avatarFallback,
                'avatar_fallback' => $avatarFallback,
                'activity_total' => $activityTotal,
            ];
        })->sortByDesc('activity_total')->values()->all();
    }

    private static function staffScope(RsmUser $viewer, ?string $area = null)
    {
        if ($area === 'Regional B' && $viewer->role === RsmUser::ROLE_KOORDINATOR) {
            return RsmUser::query()->where('role', RsmUser::ROLE_STAFF)->where('is_active', true)->where('area', 'Regional B')->orderBy('name');
        }
        return RsmUser::query()
            ->where('role', RsmUser::ROLE_STAFF)
            ->where('is_active', true)
            ->when(! $viewer->hasSuperUserAccess(), function ($query) use ($viewer): void {
                $query->where('area', $viewer->area);

                if (in_array($viewer->role, [RsmUser::ROLE_KOORDINATOR, RsmUser::ROLE_STAFF], true)) {
                    $query->when(
                        trim((string) $viewer->regional) !== '',
                        fn ($regionalQuery) => $regionalQuery->where('regional', $viewer->regional),
                        fn ($regionalQuery) => $regionalQuery->whereRaw('1 = 0'),
                    );

                    return;
                }
            })
            ->orderBy('name');
    }

    private static function staffRow(RsmUser $user, string $date, Collection $collab, Collection $reports, Collection $posts, array $offStatuses, Collection $periodCollab): array
    {
        $staffMetrics = $collab->get(self::nameKey((string) $user->name), collect());
        $metric = fn (string $reportName): float => (float) $staffMetrics->where('report_name', $reportName)->sum('value');
        $metricTime = fn (string $reportName, float|int $target): ?string => self::metricCompletionTime(
            $staffMetrics->where('report_name', $reportName),
            $target,
        );
        $campusPosts = $posts->filter(fn (RsmSocialPost $post): bool => CampusMatcher::matches((string) ($post->account?->unit_name ?? ''), (string) $user->campus_name));
        $staffReports = $reports->get('id:'.$user->id)
            ?? $reports->get('name:'.self::nameKey((string) $user->name))
            ?? collect();
        $dailyReports = $staffReports->filter(fn (RsmReport $report): bool => optional($report->report_date)->toDateString() === $date);
        $affiliateReports = $staffReports->filter(fn (RsmReport $report): bool => self::isActivity($report, 'Sapa Grup Affiliate'));
        $periodMetrics = $periodCollab->get(self::nameKey((string) $user->name), collect())
            ->where('regional', $user->regional);
        $spandukStart = CarbonImmutable::parse($date)->subMonthNoOverflow()->startOfMonth()->toDateString();
        $spandukTotal = (float) $periodMetrics->where('report_name', 'Pasang Spanduk')
            ->filter(fn ($row): bool => $row->metric_date->toDateString() >= $spandukStart)->sum('value');
        $weekStart = CarbonImmutable::parse($date)->startOfWeek()->toDateString();
        $weeklyMetrics = $periodMetrics->filter(fn ($row): bool => $row->metric_date->toDateString() >= $weekStart);
        $brosurTotal = (float) $periodMetrics->where('report_name', 'Sebar Brosur')->sum('value');
        $canvassingTotal = (float) $weeklyMetrics->where('report_name', 'Canvasing')->sum('value');
        $nightMetrics = $weeklyMetrics->where('report_name', 'Live Streaming Night');
        $nightTotal = (float) $nightMetrics->sum('value');

        $activity = [
            'brosur' => self::activity($brosurTotal >= 1000, $brosurTotal, 1000, null, 'Collab · Sebar Brosur · 3 bulan berjalan'),
            'canvassing' => self::activity($canvassingTotal >= 3, $canvassingTotal, 3, null, 'Collab · Canvasing · minggu berjalan'),
            'live_night' => self::activity($nightTotal >= 1, $nightTotal, 1, null, 'Collab · Live Streaming Night · minggu berjalan'),
            'spanduk' => self::activity($spandukTotal >= 6, $spandukTotal, 6, null, 'Collab · Pasang Spanduk · bulan sebelumnya sampai tanggal terpilih'),
            'absen' => self::activity($metric('Absen Tepat Waktu') >= 1, $metric('Absen Tepat Waktu'), 1, $metricTime('Absen Tepat Waktu', 1), 'GGKlik v2 · Absen Tepat Waktu'),
            'fu_bdc' => self::activity($metric('Follow Up BDC') >= 30, $metric('Follow Up BDC'), 30, $metricTime('Follow Up BDC', 30), 'Collab · Follow Up BDC'),
            'instagram' => self::activity($campusPosts->whereIn('media_type', ['feed', 'reels'])->isNotEmpty(), $campusPosts->whereIn('media_type', ['feed', 'reels'])->count(), 1, self::postTime($campusPosts->whereIn('media_type', ['feed', 'reels'])->first()), 'Upload Konten Sosmed · Instagram'),
            'facebook' => self::activity($campusPosts->where('media_type', 'facebook')->isNotEmpty(), $campusPosts->where('media_type', 'facebook')->count(), 1, self::postTime($campusPosts->where('media_type', 'facebook')->first()), 'Upload Konten Sosmed · Facebook'),
            'tiktok' => self::activity($campusPosts->where('media_type', 'tiktok')->isNotEmpty(), $campusPosts->where('media_type', 'tiktok')->count(), 1, self::postTime($campusPosts->where('media_type', 'tiktok')->first()), 'Upload Konten Sosmed · TikTok'),
            'live_day' => self::activity($metric('Live Streaming') >= 1, $metric('Live Streaming'), 1, $metricTime('Live Streaming', 1), 'Collab · Live Streaming'),
            'story_ig' => self::activity($campusPosts->where('media_type', 'story')->isNotEmpty(), $campusPosts->where('media_type', 'story')->count(), 1, self::postTime($campusPosts->where('media_type', 'story')->first()), 'Monitoring Konten'),
            'share_fb' => self::activity($metric('Share FB Group') >= 3, $metric('Share FB Group'), 3, $metricTime('Share FB Group', 3), 'Collab · Share FB Group'),
            'laporan' => self::activity($dailyReports->isNotEmpty(), $dailyReports->count(), 1, optional($dailyReports->sortBy('created_at')->first()?->created_at)->format('H:i'), 'Laporan Aktivitas Lain'),
            'affiliate' => self::activity($affiliateReports->isNotEmpty(), $affiliateReports->count(), 1, optional($affiliateReports->sortBy('created_at')->first()?->created_at)->format('H:i'), 'Laporan · Sapa Grup Affiliate'),
        ];
        $dailyActivity = collect($activity)->only(self::DAILY_KEYS);
        $completed = $dailyActivity->where('done', true)->count();
        $checkpointTimes = $dailyActivity
            ->where('done', true)
            ->pluck('time')
            ->filter()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $time, int $index): array => [$index + 1 => $time])
            ->all();
        $avatarFallback = self::avatarFallback((string) $user->name);

        return [
            'id' => $user->id,
            'name' => (string) $user->name,
            'is_libur' => isset($offStatuses[self::nameKey((string) $user->name)]),
            'schedule_status' => $offStatuses[self::nameKey((string) $user->name)] ?? null,
            'area' => (string) ($user->area ?: '-'),
            'unit' => (string) ($user->campus_name ?: 'Unit belum diatur'),
            'campus' => (string) ($user->campus_name ?: 'Kampus belum diatur'),
            'regional' => (string) ($user->regional ?: '-'),
            'avatar' => $user->photoUrl() ?: $avatarFallback,
            'avatar_fallback' => $avatarFallback,
            'completed_daily' => $completed,
            'total_daily' => count(self::DAILY_KEYS),
            'tracked_daily' => 9,
            'activities' => $activity,
            'checkpoint_times' => $checkpointTimes,
            'checkpoint_time' => $checkpointTimes[$completed] ?? null,
            'finish_time' => $completed >= count(self::DAILY_KEYS) ? ($checkpointTimes[count(self::DAILY_KEYS)] ?? null) : null,
            'last_activity_at' => collect($activity)->pluck('time')->filter()->sortDesc()->first(),
        ];
    }

    private static function avatarFallback(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($words)->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '?';
        $safeInitials = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" rx="48" fill="#2563eb"/><text x="48" y="51" fill="#fff" font-family="Arial,sans-serif" font-size="34" font-weight="700" text-anchor="middle" dominant-baseline="middle">'.$safeInitials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function activity(bool $done, float|int $actual, float|int $target, ?string $time, string $source): array
    {
        return ['done' => $done, 'tracked' => true, 'actual' => $actual, 'target' => $target, 'time' => $time, 'source' => $source];
    }

    private static function untracked(string $source): array
    {
        return ['done' => false, 'tracked' => false, 'actual' => null, 'target' => null, 'time' => null, 'source' => $source];
    }

    private static function postTime(?RsmSocialPost $post): ?string
    {
        if (! $post) {
            return null;
        }

        return $post->post_time ? substr((string) $post->post_time, 0, 5) : optional($post->created_at)->format('H:i');
    }

    private static function metricCompletionTime(Collection $metrics, float|int $target): ?string
    {
        $runningTotal = 0.0;

        foreach ($metrics->sortBy('synced_at') as $metric) {
            $runningTotal += (float) $metric->value;

            if ($runningTotal >= $target) {
                return optional($metric->synced_at)->format('H:i');
            }
        }

        return null;
    }

    private static function isActivity(RsmReport $report, string $activity): bool
    {
        $expected = self::activityKey($activity);

        return collect([$report->activity_kind, $report->title])
            ->filter(fn ($value): bool => trim((string) $value) !== '')
            ->contains(fn ($value): bool => self::activityKey((string) $value) === $expected);
    }

    private static function activityKey(string $activity): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $activity)));
    }

    private static function nameKey(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+Tim Terpilih$/i', '', $name)));
    }
}
