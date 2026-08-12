<?php

namespace App\Services\Dashboard;

use App\Models\RsmGamificationTransaction;
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
 * owns the ledger (award/read) and the level curve, and calls back into
 * GamificationService::personalProfileXp() to know how many points a user's
 * personal activity is currently worth.
 */
class XpService
{
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
     * One-time-per-day reconciliation: compares the user's current
     * personal-activity point total (GamificationService::personalProfileXp
     * - live, recalculated) against what's already banked in the ledger,
     * and awards the difference if the live total has grown. Never awards a
     * negative delta, so XP already banked survives a later drop in the
     * live total (a report getting rejected/deleted, etc).
     *
     * Idempotency key is per user per day, so repeat page loads the same
     * day never double-award - the first view of a day banks whatever
     * growth happened since the last visit.
     */
    public static function syncPersonalActivity(RsmUser $user): ?RsmGamificationTransaction
    {
        $livePoints = GamificationService::personalProfileXp($user);
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
