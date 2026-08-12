<?php

namespace App\Console\Commands;

use App\Models\RsmGamificationTransaction;
use App\Models\RsmUser;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\XpService;
use Illuminate\Console\Command;

/**
 * One-time opening-balance import for the Gamification Phase 1 XP ledger
 * (see XpService/RsmGamificationTransaction) - every active user's current
 * personal point total (GamificationService::personalProfileXp(), the same
 * personal-only formula the ongoing daily sync uses) becomes a single
 * `legacy_xp_import` transaction, so switching to the ledger doesn't reset
 * anyone back to 0 XP.
 *
 * Idempotent: re-running skips any user who already has a legacy_xp_import
 * row for the deterministic `legacy_xp_import:{user_id}` idempotency key.
 */
class BackfillGamificationXp extends Command
{
    protected $signature = 'gamification:backfill-xp {--dry-run : Compute and report without writing any transactions}';

    protected $description = 'Import each active user\'s current calculated XP as a one-time lifetime XP ledger opening balance';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        RsmUser::query()->where('is_active', true)->orderBy('id')->chunkById(50, function ($users) use (&$processed, &$imported, &$skipped, &$failed, $dryRun) {
            foreach ($users as $user) {
                $processed++;
                $idempotencyKey = 'legacy_xp_import:'.$user->id;

                try {
                    $alreadyImported = RsmGamificationTransaction::query()
                        ->where('user_id', $user->id)
                        ->where('event_type', 'legacy_xp_import')
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists();

                    if ($alreadyImported) {
                        $skipped++;

                        continue;
                    }

                    $legacyXp = GamificationService::personalProfileXp($user);

                    if ($legacyXp <= 0) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        XpService::awardXp(
                            user: $user,
                            eventType: 'legacy_xp_import',
                            xp: $legacyXp,
                            reason: 'Initial lifetime XP imported from legacy gamification calculation',
                            idempotencyKey: $idempotencyKey,
                        );
                    }

                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Gagal untuk user {$user->id} ({$user->name}): {$e->getMessage()}");
                }
            }
        });

        $this->line(($dryRun ? '[DRY RUN] ' : '')."processed={$processed} imported={$imported} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
