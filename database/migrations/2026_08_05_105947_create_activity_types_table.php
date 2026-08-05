<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120)->unique('uq_activity_type_name');
            $table->string('color', 20)->default('#1e428d');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_types');
    }
};
