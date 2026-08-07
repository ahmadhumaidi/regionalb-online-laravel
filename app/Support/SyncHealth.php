<?php

namespace App\Support;

use App\Models\RsmBdcReportUserSnapshot;
use App\Services\CollabSourceService;

/**
 * Ports rsm_sync_health_status() from public_html/rsm_db.php.
 */
class SyncHealth
{
    public static function status(): array
    {
        $collab = CollabSourceService::snapshot();

        $bdc = RsmBdcReportUserSnapshot::query()
            ->selectRaw('MAX(fetched_at) as last_fetched_at, MAX(snapshot_date) as last_snapshot_date, COUNT(*) as rows_count')
            ->first();

        return [
            'collab' => [
                'status' => self::collabHasData($collab) ? 'OK' : 'Belum ada cache',
                'synced_at' => (string) ($collab['synced_at'] ?? ''),
                'errors' => self::collabErrors($collab),
            ],
            'bdc' => [
                'status' => (int) ($bdc?->rows_count ?? 0) > 0 ? 'OK' : 'Belum ada snapshot',
                'synced_at' => (string) ($bdc?->last_fetched_at ?? ''),
                'snapshot_date' => (string) ($bdc?->last_snapshot_date ?? ''),
                'rows_count' => (int) ($bdc?->rows_count ?? 0),
            ],
        ];
    }

    private static function collabHasData(array $collab): bool
    {
        foreach ((array) ($collab['reports'] ?? []) as $report) {
            if (! empty($report['rows'])) {
                return true;
            }
        }

        return false;
    }

    private static function collabErrors(array $collab): array
    {
        $errors = [];
        foreach ((array) ($collab['reports'] ?? []) as $name => $report) {
            if ((string) ($report['error'] ?? '') !== '') {
                $errors[$name] = $report['error'];
            }
        }

        return $errors;
    }
}
