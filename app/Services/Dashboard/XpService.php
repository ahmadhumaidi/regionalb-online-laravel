<?php

namespace App\Services\Dashboard;

use App\Models\RsmGamificationTransaction;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\DB;

/**
 * Gamification Refactor Phase 1: "Lifetime XP Ledger + Level Progression".
 *
 * Before this, the Profile page's XP was a live recalculation of report/
 * Collab stats (see GamificationService::profileSummary()) - it could go up
 * OR down as source data changed, and never persisted. XpService turns XP
 * into an append-only ledger (rsm_gamification_transactions): once awarded,
 * XP is never clawed back just because the report/lead it was earned from
 * later gets edited, rejected, or deleted.
 *
 * GamificationService is intentionally left owning the point FORMULA
 * (report/lead/Collab weights) and the League thresholds - this class only
 * owns the ledger (award/read) and the level curve.
 *
 * Phase 2 added real-time awarding: syncReportEventXp() is called from
 * every authoritative report/lead mutation point (see its own docblock)
 * instead of waiting for syncPersonalActivity()'s daily reconciliation to
 * notice the change next time the user opens their Profile page.
 * syncPersonalActivity() still exists and still runs - it now only covers
 * the Collab-sourced (registrasi/herreg) slice for staff, which has no
 * real-time trigger point in this codebase (see
 * GamificationService::profileSyncXp()).
 */
class XpService
{
    /** Mirrors GamificationService::STATUS_APPROVED - a report currently sitting in one of these statuses counts as "approved" for the report_approved event, fired once regardless of how many approved statuses it passes through afterward. */
    private const STATUS_APPROVED = ['Diverifikasi', 'Disetujui', 'Disetujui Senior Manager', 'Selesai', 'Berjalan'];

    private const AD_VERIFIED_STATUSES = ['diverifikasi', 'selesai'];

    private const LEVEL_BASE = 100;

    private const LEVEL_LINEAR_STEP = 50;

    private const LEVEL_EXP_MULTIPLIER = 20;

    private const LEVEL_EXP_POWER = 1.5;

    /** Safety bound so a corrupt/absurd XP value can't spin this into an unbounded loop. */
    private const MAX_LEVEL_ITERATIONS = 1000;

    /**
     * Idempotent XP award. Two calls with the same
     * (user, event_type, source_type, source_id) - or, for source-less
     * events, the same (user, event_type, idempotency_key) - only ever
     * insert one row; the second call just returns the existing one.
     *
     * Every award needs a source_id OR an idempotency_key (never neither),
     * since a unique index can't dedupe a row where every distinguishing
     * column is NULL.
     */
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
     * One-time-per-day reconciliation for whatever ISN'T covered by
     * real-time events (see syncReportEventXp()) - compares
     * GamificationService::profileSyncXp() (live) against what's already
     * banked, and awards the difference if the live total has grown. Never
     * awards a negative delta, so XP already banked survives a later drop
     * in the live total.
     *
     * Gamification Phase 2: this used to cover the *entire* point formula
     * (report_total, approved_reports, leads_total, follow_up_total,
     * closing_iklan, complete_follow_up_notes, registrasi, herreg) for
     * staff. Now that report/lead-driven components are awarded in real
     * time at their source, profileSyncXp() only returns the
     * Collab-sourced remainder (registrasi/herreg) for staff, so this can't
     * double-count them. Koordinator/senior tier are unchanged (still the
     * full team-average formula - no real-time equivalent exists for an
     * average).
     *
     * Idempotency key is per user per day, so repeat page loads the same
     * day never double-award - the first view of a day banks whatever
     * growth happened since the last visit.
     */
    public static function syncPersonalActivity(RsmUser $user): ?RsmGamificationTransaction
    {
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
     * Gamification Phase 2: real-time XP at the authoritative source,
     * called from every place a report/lead is actually created or
     * transitioned - ReportFormService::create()/update(),
     * ReportStatusController/AdBudgetActionController/
     * ObstacleFollowUpController's transition() methods, and
     * AdLeadImportService::import()/bulkUpdate(). Idempotent per
     * (event_type, source_type, source_id) via awardXp() - safe to call
     * redundantly from multiple mutation paths on the same report, since
     * each event can only ever be inserted once regardless of how many
     * times this runs. Deliberately staff-only (see
     * GamificationService::resolveStaffAuthor()) - a koordinator/senior's
     * XP is a team average with no single-event equivalent.
     */
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

    /**
     * @return array{
     *     level: int,
     *     current_xp: int,
     *     current_level_start_xp: int,
     *     next_level_xp: int,
     *     xp_into_level: int,
     *     xp_needed: int,
     *     progress_percent: int,
     * }
     */
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

    /**
     * XP required to go from level $level to $level + 1. Progressive curve
     * (starting point suggested during Phase 1 planning): early levels are
     * cheap, cost grows both linearly and via a super-linear (^1.5) term so
     * higher levels take meaningfully longer - replaces the old flat
     * "200 XP = 1 level" rule that produced unrealistic levels (200+) once
     * lifetime XP got into the tens of thousands.
     */
    private static function xpStepForLevel(int $level): int
    {
        return (int) (self::LEVEL_BASE
            + ($level - 1) * self::LEVEL_LINEAR_STEP
            + floor((($level - 1) ** self::LEVEL_EXP_POWER) * self::LEVEL_EXP_MULTIPLIER));
    }
}
