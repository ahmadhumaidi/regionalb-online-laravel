<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_deleted_usernames', function (Blueprint $table) {
            $table->string('username', 100)->primary();
            $table->string('deleted_name', 180)->nullable();
            $table->string('deleted_role', 40)->nullable();
            $table->unsignedInteger('deleted_by_user_id')->nullable();
            $table->dateTime('deleted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_deleted_usernames');
    }
};
