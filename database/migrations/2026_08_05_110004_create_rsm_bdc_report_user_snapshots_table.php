<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_bdc_report_user_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('snapshot_date');
            $table->dateTime('fetched_at');
            $table->string('nik', 80);
            $table->string('name', 180);
            $table->string('campus_name', 180)->nullable();
            $table->string('wilayah', 120)->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('data_baru_count')->default(0);
            $table->unsignedInteger('cold_count')->default(0);
            $table->unsignedInteger('warm_count')->default(0);
            $table->unsignedInteger('hot_count')->default(0);
            $table->unsignedInteger('closing_count')->default(0);
            $table->unsignedInteger('wawancara_count')->default(0);
            $table->unsignedInteger('belum_herreg_count')->default(0);
            $table->unsignedInteger('herreg_count')->default(0);
            $table->unsignedInteger('fu_hari_ini_count')->default(0);
            $table->longText('raw_payload')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['snapshot_date', 'nik'], 'uq_bdc_snapshot_user_day');
            $table->index('wilayah', 'idx_bdc_snapshot_wilayah');
            $table->index('name', 'idx_bdc_snapshot_name');
            $table->index('fetched_at', 'idx_bdc_snapshot_fetched');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_bdc_report_user_snapshots');
    }
};
