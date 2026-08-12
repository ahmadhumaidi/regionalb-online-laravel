<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_badge_settings', function (Blueprint $table) {
            $table->string('indicator_key', 80)->nullable()->after('badge_key');
        });
    }

    public function down(): void
    {
        Schema::table('rsm_badge_settings', function (Blueprint $table) {
            $table->dropColumn('indicator_key');
        });
    }
};
