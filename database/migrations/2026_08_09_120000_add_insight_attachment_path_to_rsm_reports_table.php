<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Bukti insight/pengeluaran iklan" — a second attachment on ads reports,
 * separate from attachment_path (bukti transfer/invoice anggaran). Staff
 * Unit and Koordinator Wilayah upload the ad platform's insight/spend
 * screenshot here; koordinator's existing attachment_path stays dedicated
 * to the transfer proof.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rsm_reports', 'insight_attachment_path')) {
            Schema::table('rsm_reports', function (Blueprint $table) {
                $table->string('insight_attachment_path', 500)->nullable()->after('attachment_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->dropColumn('insight_attachment_path');
        });
    }
};
