<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_monthly_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->char('target_month', 7);
            $table->enum('scope_type', ['regional', 'wilayah', 'unit', 'staff'])->default('regional');
            $table->string('scope_key', 260);
            $table->string('wilayah', 120)->nullable();
            $table->string('unit_name', 180)->nullable();
            $table->string('staff_name', 180)->nullable();
            $table->unsignedInteger('target_leads')->default(0);
            $table->unsignedInteger('target_follow_up')->default(0);
            $table->unsignedInteger('target_registrasi')->default(0);
            $table->unsignedInteger('target_herregistrasi')->default(0);
            $table->decimal('target_anggaran', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['area', 'target_month', 'scope_key'], 'uq_rsm_monthly_targets_scope');
            $table->index(['area', 'target_month'], 'idx_rsm_monthly_targets_area_month');
            $table->index(['scope_type', 'scope_key'], 'idx_rsm_monthly_targets_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_monthly_targets');
    }
};
