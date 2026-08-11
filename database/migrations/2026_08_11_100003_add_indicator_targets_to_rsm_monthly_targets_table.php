<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_monthly_targets', function (Blueprint $table) {
            $table->json('indicator_targets')->nullable()->after('target_anggaran');
        });
    }

    public function down(): void
    {
        Schema::table('rsm_monthly_targets', function (Blueprint $table) {
            $table->dropColumn('indicator_targets');
        });
    }
};
