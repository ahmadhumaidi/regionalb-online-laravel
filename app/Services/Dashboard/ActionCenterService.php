<?php

namespace App\Services\Dashboard;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\AdBudget\PendingAdReportsService;
use App\Services\Reports\ReportFormService;
use App\Services\Reports\ReportListService;
use App\Support\RsmRole;
use Illuminate\Support\Facades\Schema;

class ActionCenterService
{
    /** @return list<array{label: string, count: int, description: string, href: string, tone: string, icon: string}> */
    public static function build(string $area, RsmUser $user): array
    {
        if (! Schema::hasTable('rsm_reports')) {
            return [];
        }

        $cards = [];
        $pendingAds = PendingAdReportsService::build($area, $user);
        $incompleteAds = collect([...$pendingAds['belum_dilaporkan'], ...$pendingAds['belum_tuntas']])
            ->filter(fn (array $row) => $row['can_edit'])
            ->unique('id')
            ->count();
        if ($incompleteAds > 0) {
            $cards[] = self::card('Lengkapi laporan iklan', $incompleteAds, 'Bukti, realisasi, atau data hasil masih belum lengkap.', route('anggaran'), 'amber', 'warning');
        }

        $revisionCount = ReportScope::apply(
            RsmReport::query()->where('area', $area)->where('status', 'Revisi'),
            $user,
        )->get()->filter(fn (RsmReport $report) => ReportFormService::canEdit($report, $user))->count();
        if ($revisionCount > 0) {
            $cards[] = self::card('Perbaiki laporan revisi', $revisionCount, 'Laporan dikembalikan dan perlu diperbarui.', route('rekap'), 'red', 'edit');
        }

        $verificationCount = ReportScope::apply(
            RsmReport::query()->where('area', $area)->where('report_type', RsmReport::TYPE_ADS)->where('status', 'Dilaporkan Unit'),
            $user,
        )->get()->filter(fn (RsmReport $report) => RsmRole::canVerifyAdBudgetRequest($report, $user))->count();
        if ($verificationCount > 0) {
            $cards[] = self::card('Verifikasi laporan iklan', $verificationCount, 'Bukti dari unit sudah masuk dan menunggu pemeriksaan.', route('anggaran'), 'blue', 'check');
        }

        $obstacleCount = ReportScope::apply(
            RsmReport::query()
                ->where('area', $area)
                ->where('report_type', RsmReport::TYPE_OTHER)
                ->whereNotNull('obstacle_text')
                ->where('obstacle_text', '<>', '')
                ->whereIn('status', ['Dikirim', 'Ditindak Lanjuti']),
            $user,
        )->get()->filter(function (RsmReport $report) use ($user) {
            $row = ReportListService::shape($report, $user);

            return $row['can_follow_up'] || $row['can_mark_selesai'];
        })->count();
        if ($obstacleCount > 0) {
            $cards[] = self::card('Tindak lanjuti kendala', $obstacleCount, 'Ada kendala tim yang masih membutuhkan respons.', route('aktivitas'), 'red', 'warning');
        }

        $scheduleCount = self::todayScheduleCount($area, $user);
        if ($scheduleCount > 0) {
            $cards[] = self::card('Jadwal hari ini', $scheduleCount, 'Agenda koordinator hari ini belum berstatus selesai.', route('jadwal-koordinator'), 'green', 'calendar');
        }

        return $cards;
    }

    private static function todayScheduleCount(string $area, RsmUser $user): int
    {
        if ($user->role === RsmUser::ROLE_STAFF || ! Schema::hasTable('rsm_coordinator_schedules')) {
            return 0;
        }

        return RsmCoordinatorSchedule::query()
            ->where('area', $area)
            ->whereDate('schedule_date', today())
            ->where('status', '<>', 'Selesai')
            ->when($user->role === RsmUser::ROLE_KOORDINATOR, fn ($query) => $query
                ->where(function ($scope) use ($user) {
                    $scope->where('koordinator_user_id', $user->id)->orWhere('koordinator_name', $user->name);
                }))
            ->count();
    }

    private static function card(string $label, int $count, string $description, string $href, string $tone, string $icon): array
    {
        return compact('label', 'count', 'description', 'href', 'tone', 'icon');
    }
}
