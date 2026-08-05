<?php

namespace App\Services\Dashboard;

use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Collection;

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

    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $reports = ScopedReports::query($area, $filters, $user)->with('adLeads')->get();

        $rawStatuses = $reports->flatMap(fn (RsmReport $report) => $report->adLeads->pluck('closing_status'))
            ->filter(fn ($value) => trim((string) $value) !== '');
        $buckets = ClosingStatusClassifier::buckets($rawStatuses);

        $liveRows = self::aggregateByStaff($reports, $buckets);

        $collabPerformance = CollabMetricsService::staffPerformance($area, $filters, $user);
        $collabByName = collect($collabPerformance['rows'])->keyBy(fn ($row) => mb_strtolower(trim((string) $row['name'])));

        $scoredRows = $liveRows->map(fn (array $row) => self::scoreRow($row, $collabByName));

        $leaderboard = $scoredRows
            ->filter(fn (array $row) => trim($row['name']) !== '' && $row['name'] !== '-')
            ->sortBy([['points', 'desc'], ['name', 'asc']])
            ->values();

        $myRank = $scoredRows->first(fn (array $row) => $row['user_id'] === $user->id)
            ?? $scoredRows->first(fn (array $row) => mb_strtolower(trim($row['name'])) === mb_strtolower(trim((string) $user->name)));

        return [
            'leaderboard' => $leaderboard->take(5)->values()->all(),
            'my_rank' => $myRank,
            'challenge' => ['items' => self::challengeItems()],
            'point_rules' => self::pointRules(),
        ];
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
                $uploadedAdReports = 0;
                $completeFollowUpNotes = 0;
                $spend = 0.0;

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
                        $spend += (float) $report->realization_amount;
                        if ((float) $report->realization_amount > 0) {
                            $uploadedAdReports++;
                        }
                    }
                }

                return [
                    'user_id' => $first->user_id ?: null,
                    'name' => $label,
                    'report_total' => $groupReports->count(),
                    'report_days' => $groupReports->pluck('report_date')->filter()->map(fn ($date) => $date->toDateString())->unique()->count(),
                    'approved_reports' => $groupReports->whereIn('status', self::STATUS_APPROVED)->count(),
                    'leads_total' => $leads,
                    'follow_up_total' => $followUp,
                    'registrasi_total' => $registrasi,
                    'herregistrasi_total' => $herreg,
                    'spend_total' => $spend,
                    'uploaded_ad_reports' => $uploadedAdReports,
                    'complete_follow_up_notes' => $completeFollowUpNotes,
                ];
            })
            ->values();
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
            + $row['uploaded_ad_reports'] * 10
            + $row['complete_follow_up_notes'] * 5;

        return array_merge($row, [
            'closing_for_points' => $closingForPoints,
            'herreg_for_points' => $herregForPoints,
            'points' => (int) round($points),
            'badges' => self::badges($row['follow_up_total'], $closingForPoints, $herregForPoints, $row['report_days'], $row['spend_total']),
        ]);
    }

    /** @return list<string> */
    private static function badges(float $followUp, float $closing, float $herreg, int $reportDays, float $spend): array
    {
        $badges = [];

        if ($followUp >= 10) {
            $badges[] = 'Follow Up Hero';
        }
        if ($closing >= 3) {
            $badges[] = 'Closing Hunter';
        }
        if ($herreg >= 1) {
            $badges[] = 'Herregistrasi Champion';
        }
        if ($reportDays >= 5) {
            $badges[] = 'Consistency Streak';
        }
        if ($spend > 0 && $closing > 0) {
            $badges[] = 'Budget Efficient';
        }

        return $badges === [] ? ['On Progress'] : $badges;
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
            '+10 poin per laporan iklan terunggah',
            '+5 poin per catatan follow up lengkap',
        ];
    }
}
