<?php

namespace App\Console\Commands;

use App\Models\RsmUser;
use App\Services\BdcReportUsersService;
use App\Services\CollabSourceService;
use App\Services\Dashboard\XpService;
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

            // Collab is the authoritative source for personal registrasi/herreg.
            // Reconcile staff XP immediately after a successful/partial source
            // refresh so XP no longer waits for the user to open Profile.
            if (! empty($data['reports'])) {
                $reconciled = 0;
                $awarded = 0;

                RsmUser::query()
                    ->where('role', RsmUser::ROLE_STAFF)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->chunkById(50, function ($users) use (&$reconciled, &$awarded): void {
                        foreach ($users as $user) {
                            $reconciled++;
                            if (XpService::syncCollabActivity($user) !== null) {
                                $awarded++;
                            }
                        }
                    });

                $this->line("Collab XP: {$reconciled} staff direkonsiliasi, {$awarded} transaksi/baseline dibuat");
            }
        }
        if ($run('bdc')) {
            $data = BdcReportUsersService::refresh();
            $this->line('BDC: '.(!empty($data) ? 'ok' : 'empty'));
        }
        return self::SUCCESS;
    }
}
