<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_coordinator_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->date('schedule_date');
            $table->unsignedInteger('koordinator_user_id')->nullable();
            $table->string('koordinator_name', 180);
            $table->string('koordinator_home', 120)->nullable();
            $table->string('wilayah', 120);
            $table->string('unit_name', 180);
            $table->enum('visit_type', ['Fisik', 'Zoom', 'Telepon'])->default('Zoom');
            $table->string('agenda', 220);
            $table->enum('status', ['Rencana', 'Dijadwalkan', 'Selesai', 'Reschedule'])->default('Rencana');
            $table->text('result_text')->nullable();
            $table->text('next_action')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->text('checklist_json')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['area', 'schedule_date', 'koordinator_name', 'unit_name'], 'uq_rsm_coord_schedule_day_unit');
            $table->index(['area', 'schedule_date'], 'idx_rsm_coord_schedule_area_date');
            $table->index(['area', 'wilayah'], 'idx_rsm_coord_schedule_wilayah');
            $table->index('koordinator_name', 'idx_rsm_coord_schedule_korwil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_coordinator_schedules');
    }
};
