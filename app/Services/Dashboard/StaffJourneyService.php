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
    private const DAILY_KEYS = ['absen', 'fu_bdc', 'instagram', 'facebook', 'tiktok', 'live_day', 'story_ig', 'share_fb', 'laporan'];

    /** @return array{date:string,total_daily:int,tracked_daily:int,staff:list<array>} */
    public static function build(RsmUser $viewer, ?string $date = null): array
    {
        $date ??= now()->toDateString();
        $selectedDate = CarbonImmutable::parse($date);
        $staff = self::staffScope($viewer)->get();
        $names = $staff->pluck('name')->filter()->values();
        $offStatuses = PersonnelScheduleService::offStatusesForDate($date);

        $collab = RsmCollabDailyMetric::query()
            ->whereDate('metric_date', $date)
            ->whereIn('report_name', ['Follow Up BDC', 'Share FB Group', 'Live Streaming', 'Absen Staff'])
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
            'staff' => $staff->map(fn (RsmUser $user): array => self::staffRow($user, $date, $collab, $reports, $posts, $offStatuses))->values()->all(),
        ];
    }

    /** @return list<array{id:int,name:string,unit:string,campus:string,regional:string,area:string,avatar:string,avatar_fallback:string,activity_total:int}> */
    public static function leaderboard(RsmUser $viewer, string $dateFrom, string $dateTo): array
    {
        $staff = self::staffScope($viewer)->get();
        $names = $staff->pluck('name')->filter()->values();
        $metrics = RsmCollabDailyMetric::query()
            ->whereDate('metric_date', '>=', $dateFrom)
            ->whereDate('metric_date', '<=', $dateTo)
            ->whereIn('report_name', ['Follow Up BDC', 'Share FB Group', 'Live Streaming', 'Absen Staff'])
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
                $activityTotal += (int) (($totals['Absen Staff'] ?? 0) >= 1);
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

    private static function staffScope(RsmUser $viewer)
    {
        return RsmUser::query()
            ->where('role', RsmUser::ROLE_STAFF)
            ->where('is_active', true)
            ->when($viewer->role !== RsmUser::ROLE_SUPER_USER, function ($query) use ($viewer): void {
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

    private static function staffRow(RsmUser $user, string $date, Collection $collab, Collection $reports, Collection $posts, array $offStatuses): array
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

        $activity = [
            'absen' => self::activity($metric('Absen Staff') >= 1, $metric('Absen Staff'), 1, $metricTime('Absen Staff', 1), 'GGKlik v2 · Absen Masuk'),
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
