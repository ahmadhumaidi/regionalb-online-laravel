<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_ad_budget_limits', function (Blueprint $table) {
            $table->string('unit_name', 160)->default('')->after('wilayah');
            $table->dropUnique('uq_rsm_ad_budget_limits_scope');
            $table->unique(['area', 'ad_period', 'wilayah', 'unit_name'], 'uq_rsm_ad_budget_limits_scope');
        });
    }

    public function down(): void
    {
        Schema::table('rsm_ad_budget_limits', function (Blueprint $table) {
            $table->dropUnique('uq_rsm_ad_budget_limits_scope');
            $table->unique(['area', 'ad_period', 'wilayah'], 'uq_rsm_ad_budget_limits_scope');
            $table->dropColumn('unit_name');
        });
    }
};
