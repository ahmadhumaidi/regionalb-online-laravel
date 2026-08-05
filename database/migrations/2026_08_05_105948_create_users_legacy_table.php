<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120);
            $table->string('username', 80)->unique();
            $table->string('password_hash', 255)->nullable();
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->string('regional', 120)->nullable();
            $table->string('campus', 160)->nullable();
            $table->unsignedInteger('partner_campus_id')->nullable();
            $table->boolean('must_set_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('partner_campus_id', 'idx_users_partner_campus');
            $table->foreign('partner_campus_id', 'fk_users_partner_campus')
                ->references('id')->on('partner_campuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
