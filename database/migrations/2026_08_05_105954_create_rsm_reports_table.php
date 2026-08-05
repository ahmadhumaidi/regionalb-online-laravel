<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->enum('report_type', ['marketing', 'ads', 'other']);
            $table->date('report_date');
            $table->integer('user_id')->nullable();
            $table->integer('partner_campus_id')->nullable();
            $table->string('wilayah', 120);
            $table->string('unit_name', 180);
            $table->string('staff_name', 180);
            $table->string('created_by_name', 180)->nullable();
            $table->string('created_by_role', 40);
            $table->string('status', 60)->default('Draft');
            $table->string('title', 220);
            $table->string('activity_kind', 120)->nullable();
            $table->string('location_name', 220)->nullable();
            $table->text('target_text')->nullable();
            $table->text('result_text')->nullable();
            $table->unsignedInteger('leads_count')->default(0);
            $table->unsignedInteger('closing_count')->default(0);
            $table->text('notes')->nullable();
            $table->string('platform', 120)->nullable();
            $table->string('ad_period', 40)->nullable();
            $table->string('campaign_name', 220)->nullable();
            $table->string('ad_goal', 80)->nullable();
            $table->decimal('budget_requested', 15, 2)->default(0);
            $table->decimal('budget_approved', 15, 2)->default(0);
            $table->decimal('realization_amount', 15, 2)->default(0);
            $table->decimal('cpl', 15, 2)->default(0);
            $table->string('campaign_link', 500)->nullable();
            $table->string('category', 120)->nullable();
            $table->text('obstacle_text')->nullable();
            $table->text('follow_up_text')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->text('revision_note')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['area', 'report_type'], 'idx_rsm_reports_area_type');
            $table->index('status', 'idx_rsm_reports_status');
            $table->index('report_date', 'idx_rsm_reports_date');
            $table->index('staff_name', 'idx_rsm_reports_staff');
            $table->index('user_id', 'idx_rsm_reports_user');
            $table->index('partner_campus_id', 'idx_rsm_reports_campus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_reports');
    }
};
