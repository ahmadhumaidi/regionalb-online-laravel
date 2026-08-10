<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks who's currently responsible for following up an "Aktivitas Lain"
 * report that has a Kendala: null = koordinator wilayah (default), or a
 * role slug ('senior', 'mentor', 'executive_director', 'director') once
 * escalated. See ObstacleFollowUpController.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rsm_reports', 'escalated_to_role')) {
            Schema::table('rsm_reports', function (Blueprint $table) {
                $table->string('escalated_to_role', 30)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->dropColumn('escalated_to_role');
        });
    }
};
