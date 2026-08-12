<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifetime XP ledger (Gamification Refactor Phase 1 — see XpService).
 * Every XP award is an append-only transaction row instead of a live
 * recalculation, so a user's XP survives changes to the underlying report/
 * Collab data it was originally computed from.
 *
 * Idempotency: awards tied to a concrete source (e.g. an approved report)
 * are deduped on (user_id, event_type, source_type, source_id). Awards with
 * no natural source (e.g. the one-time legacy backfill, or a daily activity
 * reconciliation) must instead supply a deterministic idempotency_key -
 * XpService::awardXp() enforces that at least one of source_id/
 * idempotency_key is present before insert, since MySQL/SQLite unique
 * indexes treat NULL columns as distinct from one another (a NULL/NULL/NULL
 * combination would never collide on its own).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_gamification_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('event_type', 60);
            $table->string('source_type', 60)->nullable();
            $table->unsignedInteger('source_id')->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->integer('xp')->default(0);
            $table->integer('league_points')->default(0);
            $table->integer('aura_effect')->default(0);
            $table->string('reason', 255)->nullable();
            $table->json('metadata_json')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_rsm_gam_tx_user')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
            $table->index(['user_id', 'event_type'], 'idx_rsm_gam_tx_user_event');
            $table->unique(
                ['user_id', 'event_type', 'source_type', 'source_id', 'idempotency_key'],
                'uq_rsm_gam_tx_idempotency'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_gamification_transactions');
    }
};
