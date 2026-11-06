<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->string('activity_name', 220)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rsm_reports', function (Blueprint $table) {
            $table->dropColumn('activity_name');
        });
    }
};
