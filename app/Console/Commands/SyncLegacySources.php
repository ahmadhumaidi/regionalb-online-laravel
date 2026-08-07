<?php

namespace App\Console\Commands;

use App\Services\BdcReportUsersService;
use App\Services\CollabSourceService;
use App\Services\PersonnelScheduleService;
use Illuminate\Console\Command;

class SyncLegacySources extends Command
{
    protected $signature = 'rsm:sync-sources {--only= : personalia,collab,bdc atau kosong untuk semua} {--window= : collab saja - batasi ingest daily_metrics ke N hari terakhir (kosong = penuh)}';
    protected $description = 'Refresh snapshot sumber Personalia, Collab, dan BDC';

    public function handle(): int
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $run = static fn (string $name): bool => $only === [] || in_array($name, $only, true);
        if ($run('personalia')) {
            $data = PersonnelScheduleService::sync();
            $this->line('Personalia: '.(!empty($data['errors']) ? 'fallback/error' : 'ok'));
        }
        if ($run('collab')) {
            $window = $this->option('window');
            $windowDays = $window !== null && $window !== '' ? (int) $window : null;
            $data = CollabSourceService::sync($windowDays);
            $this->line(
                'Collab: '.count($data['reports'] ?? []).' report, '.count($data['errors'] ?? []).' error'
                .($windowDays !== null ? " (window {$windowDays} hari)" : ' (full)')
            );
        }
        if ($run('bdc')) {
            $data = BdcReportUsersService::refresh();
            $this->line('BDC: '.(!empty($data) ? 'ok' : 'empty'));
        }
        return self::SUCCESS;
    }
}
