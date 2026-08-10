<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rsm_reports', 'leader_follow_up_text')) {
            Schema::table('rsm_reports', function (Blueprint $table) {
                $table->text('leader_follow_up_text')->nullable()->after('follow_up_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rsm_reports', 'leader_follow_up_text')) {
            Schema::table('rsm_reports', function (Blueprint $table) {
                $table->dropColumn('leader_follow_up_text');
            });
        }
    }
};
