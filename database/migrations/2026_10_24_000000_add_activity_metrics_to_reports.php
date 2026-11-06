<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rsm_reports', 'rsm_senior_activity_reports'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->unsignedTinyInteger('urgency')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['rsm_reports', 'rsm_senior_activity_reports'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['duration_minutes', 'urgency']);
            });
        }
    }
};
