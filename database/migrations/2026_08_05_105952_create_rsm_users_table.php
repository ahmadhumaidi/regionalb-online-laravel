<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 180);
            $table->string('nik', 80)->nullable();
            $table->string('username', 100)->unique('uq_rsm_users_username');
            $table->string('password_hash', 255);
            $table->enum('role', [
                'super_user', 'executive_director', 'director', 'senior', 'mentor', 'koordinator', 'staff',
            ])->default('staff');
            $table->string('jabatan', 120);
            $table->string('regional', 120)->nullable();
            $table->string('area', 40)->nullable();
            $table->string('campus_name', 180)->nullable();
            $table->string('phone_number', 80)->nullable();
            $table->string('work_duration', 120)->nullable();
            $table->text('bio_text')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->boolean('must_change_password')->default(true);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('role', 'idx_rsm_users_role');
            $table->index('regional', 'idx_rsm_users_regional');
            $table->index('area', 'idx_rsm_users_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_users');
    }
};
