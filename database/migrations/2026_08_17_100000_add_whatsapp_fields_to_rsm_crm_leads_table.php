<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_crm_leads', function (Blueprint $table) {
            $table->string('wa_message_id', 120)->nullable()->unique('uq_rsm_crm_leads_wa_message');
            $table->string('ctwa_clid', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rsm_crm_leads', function (Blueprint $table) {
            $table->dropUnique('uq_rsm_crm_leads_wa_message');
            $table->dropColumn(['wa_message_id', 'ctwa_clid']);
        });
    }
};
