<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_collab_daily_metrics', function (Blueprint $table) {
            $table->increments('id');
            $table->string('report_name', 80);
            $table->date('metric_date');
            $table->string('entity_key', 191);
            $table->string('staff_nik', 80)->nullable();
            $table->string('staff_name', 180)->nullable();
            $table->string('regional', 120)->nullable();
            $table->string('campus_name', 180)->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->dateTime('synced_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['report_name', 'metric_date', 'entity_key'], 'uq_rsm_collab_daily_metrics');
            $table->index(['report_name', 'metric_date', 'regional'], 'idx_rsm_collab_daily_metrics_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_collab_daily_metrics');
    }
};
