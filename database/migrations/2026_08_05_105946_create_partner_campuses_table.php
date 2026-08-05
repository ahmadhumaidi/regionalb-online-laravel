<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_campuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 160);
            $table->string('display_name', 160);
            $table->string('kode_kampus', 20)->unique('uq_partner_kode_kampus');
            $table->text('address');
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['latitude', 'longitude'], 'idx_partner_location');
            $table->index('display_name', 'idx_partner_display_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_campuses');
    }
};
