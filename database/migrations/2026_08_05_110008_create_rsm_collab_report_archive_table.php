<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_collab_report_archive', function (Blueprint $table) {
            $table->increments('id');
            $table->string('report_name', 80);
            $table->string('report_month', 7);
            $table->longText('payload');
            $table->dateTime('archived_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['report_name', 'report_month'], 'uq_rsm_collab_report_archive_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_collab_report_archive');
    }
};
