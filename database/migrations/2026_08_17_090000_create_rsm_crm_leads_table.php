<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_crm_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40)->default('Regional B');
            $table->string('wilayah', 120)->nullable();
            $table->string('campus_name', 180)->nullable();
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->string('lead_name', 180);
            $table->string('whatsapp', 80)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('major_name', 180)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->enum('source', ['CTWA', 'Organic', 'Referral', 'Walk-in', 'Lainnya'])->default('Lainnya');
            $table->enum('status', ['Baru', 'Dihubungi', 'Follow Up', 'Closing', 'Gagal'])->default('Baru');
            $table->text('follow_up_result')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['area', 'wilayah'], 'idx_rsm_crm_leads_wilayah');
            $table->index('owner_user_id', 'idx_rsm_crm_leads_owner');
            $table->index('status', 'idx_rsm_crm_leads_status');
            $table->index('source', 'idx_rsm_crm_leads_source');
            $table->foreign('owner_user_id', 'fk_rsm_crm_leads_owner')
                ->references('id')->on('rsm_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_crm_leads');
    }
};
