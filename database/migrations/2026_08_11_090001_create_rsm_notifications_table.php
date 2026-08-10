<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (bell icon badge) — first use case is the
 * "Aktivitas Lain" Kendala/eskalasi workflow, see NotificationService and
 * ObstacleFollowUpController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->unsignedInteger('recipient_user_id');
            $table->unsignedInteger('report_id')->nullable();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();

            $table->index(['recipient_user_id', 'is_read'], 'idx_rsm_notif_recipient_unread');
            $table->foreign('recipient_user_id', 'fk_rsm_notif_recipient')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
            $table->foreign('report_id', 'fk_rsm_notif_report')
                ->references('id')->on('rsm_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_notifications');
    }
};
