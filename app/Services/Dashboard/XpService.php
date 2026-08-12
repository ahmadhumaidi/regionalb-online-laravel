<?php

namespace App\Services\Dashboard;

use App\Models\RsmGamificationTransaction;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\DB;

/**
 * Lifetime XP ledger + progressive level calculation.
 *
 * Report/lead XP is awarded in real time at its authoritative mutation
 * points. Collab-sourced registration/herregistration XP is reconciled
 * separately because Collab arrives as periodic aggregate snapshots rather
 * than individual local events.
 */
class XpService
{
    private const STATUS_APPROVED = ['Diverifikasi', 'Disetujui', 'Disetujui Senior Manager', 'Selesai', 'Berjalan'];

    private const AD_VERIFIED_STATUSES = ['diverifikasi', 'selesai'];

    private const LEVEL_BASE = 100;

    private const LEVEL_LINEAR_STEP = 50;

    private const LEVEL_EXP_MULTIPLIER = 20;

    private const LEVEL_EXP_POWER = 1.5;

    private const MAX_LEVEL_ITERATIONS = 1000;

    public static function awardXp(
        RsmUser $user,
        string $eventType,
        int $xp,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        int $leaguePoints = 0,
        int $auraEffect = 0,
        array $metadata = [],
    ): RsmGamificationTransaction {
        if ($sourceId === null && $idempotencyKey === null) {
            throw new \InvalidArgumentException('awardXp() requires a source_id or an idempotency_key to stay idempotent.');
        }

        return DB::transaction(function () use ($user, $eventType, $xp, $sourceType, $sourceId, $reason, $idempotencyKey, $leaguePoints, $auraEffect, $metadata) {
            $existing = RsmGamificationTransaction::query()
                ->where('user_id', $user->id)
                ->where('event_type', $eventType)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            return RsmGamificationTransaction::create([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $idempotencyKey,
                'xp' => $xp,
                'league_points' => $leaguePoints,
                'aura_effect' => $auraEffect,
                'reason' => $reason,
                'metadata_json' => $metadata === [] ? null : $metadata,
            ]);
        });
    }

    public static function getLifetimeXp(RsmUser $user): int
    {
        return (int) RsmGamificationTransaction::query()->where('user_id', $user->id)->sum('xp');
    }

    /**
     * Compatibility entry point used by ProfileController.
     *
     * Staff Collab XP is now reconciled against its own Collab watermark,
     * never against total lifetime XP (which also contains legacy/report XP).
     * Non-staff keep the previous team-average reconciliation until their
     * gamification model is redesigned in a later phase.
     */
    public static function syncPersonalActivity(RsmUser $user): ?RsmGamificationTransaction
    {
        if ($user->role === RsmUser::ROLE_STAFF) {
            return self::syncCollabActivity($user);
        }

        $livePoints = GamificationService::profileSyncXp($user);
        $banked = self::getLifetimeXp($user);
        $delta = $livePoints - $banked;

        if ($delta <= 0) {
            return null;
        }

        return self::awardXp(
            user: $user,
            eventType: 'activity_sync',
            xp: $delta,
            reason: 'Personal activity point growth since last sync',
            idempotencyKey: 'activity_sync:'.$user->id.':'.now()->toDateString(),
        );
    }

    /**
     * Reconcile Collab registration/herregistration XP from aggregate data.
     *
     * The first run only records a zero-XP baseline because historical Collab
     * activity is already included in legacy_xp_import. Later runs award only
     * growth above the highest Collab target ever observed. This makes the
     * process safe when a source temporarily drops/re-syncs and prevents the
     * same historical growth being awarded twice.
     */
    public static function syncCollabActivity(RsmUser $user): ?RsmGamificationTransaction
    {
        if ($user->role !== RsmUser::ROLE_STAFF) {
            return null;
        }

        $currentTarget = max(0, GamificationService::personalCollabOnlyXp($user));
        $history = RsmGamificationTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['collab_xp_baseline', 'collab_xp_sync'])
            ->get(['metadata_json']);

        if ($history->isEmpty()) {
            return self::awardXp(
                user: $user,
                eventType: 'collab_xp_baseline',
                xp: 0,
                reason: 'Collab XP baseline after lifetime-ledger cutover',
                idempotencyKey: 'collab_xp_baseline:'.$user->id,
                metadata: ['collab_target_xp' => $currentTarget],
            );
        }

        $highestTarget = $history->reduce(function (int $max, RsmGamificationTransaction $transaction): int {
            $metadata = is_array($transaction->metadata_json) ? $transaction->metadata_json : [];

            return max($max, (int) ($metadata['collab_target_xp'] ?? 0));
        }, 0);

        if ($currentTarget <= $highestTarget) {
            return null;
        }

        $delta = $currentTarget - $highestTarget;

        return self::awardXp(
            user: $user,
            eventType: 'collab_xp_sync',
            xp: $delta,
            reason: 'Registrasi/herregistrasi Collab bertambah',
            idempotencyKey: 'collab_xp_sync:'.$user->id.':'.$currentTarget,
            metadata: [
                'collab_target_xp' => $currentTarget,
                'previous_target_xp' => $highestTarget,
            ],
        );
    }

    public static function syncReportEventXp(RsmReport $report): void
    {
        $staff = GamificationService::resolveStaffAuthor($report);
        if (! $staff) {
            return;
        }

        self::awardXp($staff, 'report_created', 5, 'report', $report->id, 'Laporan dibuat');

        if (in_array($report->status, self::STATUS_APPROVED, true)) {
            self::awardXp($staff, 'report_approved', 10, 'report', $report->id, 'Laporan disetujui/diverifikasi');
        }

        $isVerifiedAds = $report->report_type === RsmReport::TYPE_ADS
            && in_array(mb_strtolower(trim((string) $report->status)), self::AD_VERIFIED_STATUSES, true);

        $report->loadMissing('adLeads');
        $leads = $report->adLeads;
        if ($leads->isEmpty()) {
            return;
        }

        $buckets = ClosingStatusClassifier::buckets(
            $leads->pluck('closing_status')->filter(fn ($value) => trim((string) $value) !== '')
        );

        foreach ($leads as $lead) {
            self::awardXp($staff, 'lead_created', 2, 'ad_lead', $lead->id, 'Lead ditambahkan');

            $hasFollowUp = filled($lead->follow_up_result) || filled($lead->progress_status);
            if ($hasFollowUp) {
                self::awardXp($staff, 'lead_follow_up', 4, 'ad_lead', $lead->id, 'Follow up lead');
            }
            if (filled($lead->follow_up_result) && filled($lead->notes)) {
                self::awardXp($staff, 'lead_notes_complete', 5, 'ad_lead', $lead->id, 'Catatan follow up lengkap');
            }
            if ($isVerifiedAds && in_array(mb_strtolower(trim((string) $lead->closing_status)), $buckets['registrasi'], true)) {
                self::awardXp($staff, 'closing_iklan', 10, 'ad_lead', $lead->id, 'Closing dari data hasil iklan');
            }
        }
    }

    public static function calculateLevel(int $xp): array
    {
        $xp = max(0, $xp);
        $level = 1;
        $cumulative = 0;

        while ($level < self::MAX_LEVEL_ITERATIONS) {
            $step = self::xpStepForLevel($level);
            if ($cumulative + $step > $xp) {
                break;
            }
            $cumulative += $step;
            $level++;
        }

        $currentLevelStartXp = $cumulative;
        $xpNeeded = self::xpStepForLevel($level);
        $nextLevelXp = $currentLevelStartXp + $xpNeeded;
        $xpIntoLevel = $xp - $currentLevelStartXp;
        $progressPercent = $xpNeeded > 0
            ? (int) min(100, max(0, round(($xpIntoLevel / $xpNeeded) * 100)))
            : 100;

        return [
            'level' => $level,
            'current_xp' => $xp,
            'current_level_start_xp' => $currentLevelStartXp,
            'next_level_xp' => $nextLevelXp,
            'xp_into_level' => $xpIntoLevel,
            'xp_needed' => $xpNeeded,
            'progress_percent' => $progressPercent,
        ];
    }

    private static function xpStepForLevel(int $level): int
    {
        return (int) (self::LEVEL_BASE
            + ($level - 1) * self::LEVEL_LINEAR_STEP
            + floor((($level - 1) ** self::LEVEL_EXP_POWER) * self::LEVEL_EXP_MULTIPLIER));
    }
}
