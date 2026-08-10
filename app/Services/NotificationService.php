<?php

namespace App\Services;

use App\Models\RsmNotification;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Support\RsmRole;

/**
 * In-app notifications (bell icon badge). First use case: the "Aktivitas
 * Lain" Kendala/eskalasi workflow (ReportFormService, ObstacleFollowUpController).
 */
class NotificationService
{
    /** @return list<int> */
    public static function recipientIds(string $area, string $role, ?string $regional = null): array
    {
        return RsmUser::query()
            ->where('area', $area)
            ->where('role', $role)
            ->where('is_active', true)
            ->when($role === RsmUser::ROLE_KOORDINATOR && $regional, fn ($q) => $q->where('regional', $regional))
            ->pluck('id')
            ->all();
    }

    /** @param  iterable<int>  $recipientUserIds */
    public static function notify(iterable $recipientUserIds, RsmReport $report, string $type, string $title, string $message): void
    {
        $now = now();
        $rows = collect($recipientUserIds)->unique()->values()->map(fn (int $userId) => [
            'area' => $report->area,
            'recipient_user_id' => $userId,
            'report_id' => $report->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            RsmNotification::insert($rows);
        }
    }

    /** Staff melaporkan Kendala baru: beri tahu koordinator wilayah + semua Senior Manager di area itu. */
    public static function notifyKendala(RsmReport $report): void
    {
        $recipients = array_merge(
            self::recipientIds($report->area, RsmUser::ROLE_KOORDINATOR, $report->wilayah),
            self::recipientIds($report->area, 'senior'),
        );

        self::notify(
            $recipients,
            $report,
            'kendala',
            'Kendala baru dilaporkan',
            sprintf('%s melaporkan kendala pada aktivitas "%s" (%s).', $report->staff_name ?: '-', $report->title, $report->unit_name ?: $report->wilayah)
        );
    }

    /** Korwil/Senior Manager mengeskalasi laporan kendala ke role lain. */
    public static function notifyEscalation(RsmReport $report, string $toRole, RsmUser $actor): void
    {
        self::notify(
            self::recipientIds($report->area, $toRole),
            $report,
            'eskalasi',
            'Eskalasi laporan kendala',
            sprintf('%s mengeskalasi kendala pada aktivitas "%s" kepada %s.', $actor->name, $report->title, RsmRole::label($toRole))
        );
    }

    /** Beri tahu staff pembuat laporan bahwa kendalanya sudah ditindaklanjuti/selesai. */
    public static function notifyStaffClosed(RsmReport $report, string $newStatus): void
    {
        if (! $report->user_id) {
            return;
        }

        self::notify(
            [$report->user_id],
            $report,
            'tindak_lanjut',
            $newStatus === 'Selesai' ? 'Kendala Anda sudah selesai ditangani' : 'Kendala Anda sudah ditindaklanjuti',
            sprintf('Laporan "%s" sekarang berstatus %s.', $report->title, $newStatus)
        );
    }
}
