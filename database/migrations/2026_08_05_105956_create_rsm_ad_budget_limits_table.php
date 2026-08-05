<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_ad_budget_limits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('area', 40);
            $table->string('ad_period', 40);
            $table->string('wilayah', 120);
            $table->decimal('budget_limit', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['area', 'ad_period', 'wilayah'], 'uq_rsm_ad_budget_limits_scope');
            $table->index(['area', 'ad_period'], 'idx_rsm_ad_budget_limits_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_ad_budget_limits');
    }
};
