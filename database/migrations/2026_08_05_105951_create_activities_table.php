<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('campus_id')->nullable();
            $table->unsignedInteger('activity_type_id');
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_path_2', 500)->nullable();
            $table->string('photo_path_3', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address', 255)->nullable();
            $table->integer('prospect_count')->default(0);
            $table->integer('quantity')->default(0);
            $table->enum('location_status', ['baru', 'aktif', 'perlu_revisit', 'jenuh'])->default('aktif');
            $table->text('notes')->nullable();
            $table->char('source_key', 64)->nullable()->unique('uq_activities_source_key');
            $table->string('source_nik', 40)->nullable();
            $table->string('source_staff_name', 120)->nullable();
            $table->string('source_regional', 120)->nullable();
            $table->string('source_campus_name', 160)->nullable();
            $table->string('import_source', 40)->default('manual');
            $table->unsignedInteger('imported_by')->nullable();
            $table->timestamp('imported_at')->nullable()->useCurrent();
            $table->dateTime('created_at');

            $table->index('created_at', 'idx_created_at');
            $table->index(['latitude', 'longitude'], 'idx_location');
            $table->index('location_status', 'idx_status');
            $table->index('user_id', 'idx_activities_user');
            $table->index('campus_id', 'idx_activities_campus');
            $table->index('activity_type_id', 'idx_activities_type');
            $table->index('imported_by', 'idx_activities_imported_by');

            $table->foreign('campus_id', 'fk_activities_campus')
                ->references('id')->on('partner_campuses')->nullOnDelete();
            $table->foreign('imported_by', 'fk_activities_imported_by')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('activity_type_id', 'fk_activities_type')
                ->references('id')->on('activity_types');
            $table->foreign('user_id', 'fk_activities_user')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
