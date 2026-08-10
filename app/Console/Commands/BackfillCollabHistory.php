<?php

namespace App\Console\Commands;

use App\Services\CollabSourceService;
use Illuminate\Console\Command;

/**
 * One-time historical backfill for a Collab report that's missing months
 * the 30-min cron never had a chance to archive live (e.g. a report added
 * to the system after those months already passed). See
 * CollabSourceService::backfillRange() for the actual fetch mechanism.
 */
class BackfillCollabHistory extends Command
{
    protected $signature = 'rsm:backfill-collab {report} {--from=} {--to=} {--force : Re-fetch months that are already archived}';

    protected $description = 'Backfill historical monthly archive + daily metrics for a Collab report source';

    public function handle(): int
    {
        $reportName = (string) $this->argument('report');
        if (! in_array($reportName, CollabSourceService::knownReports(), true)) {
            $this->error("Report tidak dikenal: {$reportName}");
            $this->line('Pilihan: '.implode(', ', CollabSourceService::knownReports()));

            return self::FAILURE;
        }

        $from = (string) $this->option('from');
        if ($from === '') {
            $this->error('Wajib isi --from=YYYY-MM');

            return self::FAILURE;
        }
        $to = (string) ($this->option('to') ?: now()->format('Y-m'));
        $force = (bool) $this->option('force');

        $this->line("Backfill \"{$reportName}\" dari {$from} sampai {$to}".($force ? ' (force re-fetch)' : ''));

        $results = CollabSourceService::backfillRange($reportName, $from, $to, $force);
        if ($results === []) {
            $this->error('Gagal login ke cb.web.id (cek COLLAB_USERNAME/COLLAB_PASSWORD).');

            return self::FAILURE;
        }

        foreach ($results as $month => $ok) {
            $this->line($month.': '.($ok ? 'OK' : 'gagal/kosong'));
        }

        return self::SUCCESS;
    }
}
