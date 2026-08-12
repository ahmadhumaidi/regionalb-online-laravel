<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->unsignedInteger('impressions_count')->default(0)->after('realization_amount');
            $table->decimal('cpm', 15, 2)->default(0)->after('cpl');
        });
    }

    public function down(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->dropColumn(['impressions_count', 'cpm']);
        });
    }
};
