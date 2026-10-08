<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_senior_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->string('area', 40);
            $table->unsignedInteger('user_id');
            $table->date('activity_date');
            $table->string('activity_type', 20);
            $table->string('title', 220);
            $table->string('location', 220)->nullable();
            $table->text('participants')->nullable();
            $table->text('agenda');
            $table->text('result_text');
            $table->text('next_action')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->timestamps();

            $table->index(['area', 'activity_date'], 'idx_senior_activity_area_date');
            $table->index(['user_id', 'activity_date'], 'idx_senior_activity_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_senior_activity_reports');
    }
};
