<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rsm_coordinator_schedules', function (Blueprint $table) {
            $table->dropColumn('checklist_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rsm_coordinator_schedules', function (Blueprint $table) {
            $table->text('checklist_json')->nullable();
        });
    }
};
