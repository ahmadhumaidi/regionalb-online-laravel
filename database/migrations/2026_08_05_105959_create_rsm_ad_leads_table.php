<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_ad_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('report_id');
            $table->string('lead_name', 180)->nullable();
            $table->string('whatsapp', 80)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('campus_name', 180)->nullable();
            $table->string('major_name', 180)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->text('follow_up_result')->nullable();
            $table->string('progress_status', 120)->nullable();
            $table->string('closing_status', 80)->nullable();
            $table->string('closing_update', 180)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('report_id', 'idx_rsm_ad_leads_report');
            $table->foreign('report_id', 'fk_rsm_ad_leads_report')
                ->references('id')->on('rsm_reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_ad_leads');
    }
};
