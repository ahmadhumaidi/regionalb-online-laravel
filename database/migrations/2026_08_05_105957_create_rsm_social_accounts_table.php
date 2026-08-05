<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_social_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->string('wilayah', 120);
            $table->string('unit_name', 180);
            $table->string('instagram_username', 180);
            $table->string('instagram_business_id', 180)->nullable();
            $table->string('facebook_page_id', 180)->nullable();
            $table->text('facebook_page_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('connected_at')->nullable();
            $table->string('pic_name', 180)->nullable();
            $table->string('pic_phone', 80)->nullable();
            $table->string('connection_status', 60)->default('Manual');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['area', 'unit_name', 'instagram_username'], 'uq_rsm_social_account');
            $table->index(['area', 'wilayah', 'unit_name'], 'idx_rsm_social_account_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_social_accounts');
    }
};
