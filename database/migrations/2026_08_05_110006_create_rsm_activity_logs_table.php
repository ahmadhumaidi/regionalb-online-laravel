<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_activity_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('report_id')->nullable();
            $table->string('area', 40);
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->string('actor_role', 40);
            $table->string('actor_actual_role', 40)->nullable();
            $table->string('actor_view_role', 40)->nullable();
            $table->string('actor_name', 180)->nullable();
            $table->unsignedInteger('impersonator_user_id')->nullable();
            $table->string('impersonator_name', 180)->nullable();
            $table->string('action_name', 120);
            $table->string('old_status', 60)->nullable();
            $table->string('new_status', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('report_id', 'idx_rsm_logs_report');
            $table->index('area', 'idx_rsm_logs_area');
            $table->foreign('report_id', 'fk_rsm_logs_report')
                ->references('id')->on('rsm_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_activity_logs');
    }
};
