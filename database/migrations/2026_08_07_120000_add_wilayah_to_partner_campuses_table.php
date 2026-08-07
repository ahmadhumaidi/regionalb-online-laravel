<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * partner_campuses had no regional/wilayah column at all, so nothing
 * scoped the "unit/kampus" dropdown to a koordinator's own regional -
 * root cause of a koordinator being able to pick a campus from another
 * regional entirely. Backfill from rsm_collab_daily_metrics, which already
 * carries a clean, unambiguous campus_name -> regional mapping synced from
 * the authoritative external Collab source (every campus_name maps to
 * exactly one regional there).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('partner_campuses', 'wilayah')) {
            Schema::table('partner_campuses', function (Blueprint $table) {
                $table->string('wilayah', 120)->nullable()->after('kode_kampus');
            });
        }

        DB::statement(
            "UPDATE partner_campuses pc
             JOIN (
                 SELECT DISTINCT campus_name, regional
                 FROM rsm_collab_daily_metrics
                 WHERE campus_name IS NOT NULL
             ) c ON c.campus_name COLLATE utf8mb4_unicode_ci = pc.display_name COLLATE utf8mb4_unicode_ci
                 OR c.campus_name COLLATE utf8mb4_unicode_ci = pc.name COLLATE utf8mb4_unicode_ci
             SET pc.wilayah = c.regional"
        );
    }

    public function down(): void
    {
        Schema::table('partner_campuses', function (Blueprint $table) {
            $table->dropColumn('wilayah');
        });
    }
};
