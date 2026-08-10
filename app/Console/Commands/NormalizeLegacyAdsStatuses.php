<?php

namespace App\Console\Commands;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data fix: "Draft" and "Transfer / Invoice" were retired from the
 * ads status machine (see AdBudgetActionController), but reports created
 * before that change still carry those exact status strings in the
 * database. This command moves them onto the current workflow so they show
 * up in the right queue instead of just displaying stale text forever.
 *
 * Per Ahmad Humaidi (developer): Draft -> Disetujui, Transfer / Invoice ->
 * Dilaporkan Unit.
 */
class NormalizeLegacyAdsStatuses extends Command
{
    protected $signature = 'rsm:normalize-legacy-ads-statuses {--dry-run : Tampilkan jumlah laporan yang akan diubah tanpa menyimpan perubahan}';

    protected $description = 'Migrasi status laporan iklan lama "Draft" -> Disetujui dan "Transfer / Invoice" -> Dilaporkan Unit';

    /** @var array<string, string> */
    private const MAPPING = [
        'draft' => 'Disetujui',
        'transfer / invoice' => 'Dilaporkan Unit',
        'transfer-/-invoice' => 'Dilaporkan Unit',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $reports = RsmReport::query()
            ->where('report_type', RsmReport::TYPE_ADS)
            ->get()
            ->filter(fn (RsmReport $report) => array_key_exists(mb_strtolower(trim((string) $report->status)), self::MAPPING));

        if ($reports->isEmpty()) {
            $this->line('Tidak ada laporan iklan berstatus Draft/Transfer / Invoice.');

            return self::SUCCESS;
        }

        $this->line(($dryRun ? '[DRY RUN] ' : '')."Ditemukan {$reports->count()} laporan:");
        foreach ($reports as $report) {
            $newStatus = self::MAPPING[mb_strtolower(trim((string) $report->status))];
            $this->line("  #{$report->id} {$report->campaign_name} ({$report->wilayah}): {$report->status} -> {$newStatus}");
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($reports): void {
            foreach ($reports as $report) {
                $oldStatus = $report->status;
                $newStatus = self::MAPPING[mb_strtolower(trim((string) $oldStatus))];

                $report->status = $newStatus;
                $report->save();

                RsmActivityLog::create([
                    'report_id' => $report->id,
                    'area' => $report->area,
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'actor_name' => 'rsm:normalize-legacy-ads-statuses',
                    'action_name' => 'normalize_legacy_status',
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'note' => 'Migrasi otomatis: status lama dihapus dari alur laporan iklan.',
                ]);
            }
        });

        $this->info("Selesai. {$reports->count()} laporan diperbarui.");

        return self::SUCCESS;
    }
}
