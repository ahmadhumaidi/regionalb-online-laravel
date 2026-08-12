<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_badge_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('badge_key', 80)->unique();
            $table->decimal('target_value', 12, 2)->default(0);
            $table->unsignedInteger('updated_by_user_id')->nullable();
            $table->string('updated_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_badge_settings');
    }
};
