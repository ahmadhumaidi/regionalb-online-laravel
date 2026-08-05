<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_campuses', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('partner_campus_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'partner_campus_id']);
            $table->index('partner_campus_id', 'idx_user_campuses_campus');
            $table->foreign('partner_campus_id', 'fk_user_campuses_campus')
                ->references('id')->on('partner_campuses')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_user_campuses_user')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_campuses');
    }
};
