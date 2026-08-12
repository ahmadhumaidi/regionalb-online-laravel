<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, mission, day) a staff member has claimed their Daily
 * Mission reward for — see ProfileController::claimMission(). The energy/
 * stars columns snapshot the reward catalog at claim time so a later
 * change to the catalog doesn't rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_daily_mission_claims', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('mission_key', 40);
            $table->date('claim_date');
            $table->unsignedInteger('energy')->default(0);
            $table->unsignedInteger('stars')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['user_id', 'mission_key', 'claim_date'], 'uq_rsm_daily_mission_claims');
            $table->foreign('user_id', 'fk_rsm_daily_mission_claims_user')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_daily_mission_claims');
    }
};
