<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_campuses', function (Blueprint $table) {
            $table->string('instagram_username', 180)->nullable()->after('logo_url');
            $table->string('instagram_url', 500)->nullable()->after('instagram_username');
        });
    }

    public function down(): void
    {
        Schema::table('partner_campuses', function (Blueprint $table) {
            $table->dropColumn(['instagram_username', 'instagram_url']);
        });
    }
};
