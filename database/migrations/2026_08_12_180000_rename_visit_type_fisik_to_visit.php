<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the "Fisik" visit_type option to "Visit" on Jadwal Koordinator
 * schedules (rsm_coordinator_schedules) - widen the enum first so existing
 * "Fisik" rows aren't rejected/truncated by MySQL while they're updated,
 * then narrow it back down. See CoordinatorScheduleController.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rsm_coordinator_schedules MODIFY visit_type ENUM('Fisik', 'Visit', 'Zoom', 'Telepon') DEFAULT 'Zoom'");
        DB::table('rsm_coordinator_schedules')->where('visit_type', 'Fisik')->update(['visit_type' => 'Visit']);
        DB::statement("ALTER TABLE rsm_coordinator_schedules MODIFY visit_type ENUM('Visit', 'Zoom', 'Telepon') DEFAULT 'Zoom'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE rsm_coordinator_schedules MODIFY visit_type ENUM('Fisik', 'Visit', 'Zoom', 'Telepon') DEFAULT 'Zoom'");
        DB::table('rsm_coordinator_schedules')->where('visit_type', 'Visit')->update(['visit_type' => 'Fisik']);
        DB::statement("ALTER TABLE rsm_coordinator_schedules MODIFY visit_type ENUM('Fisik', 'Zoom', 'Telepon') DEFAULT 'Zoom'");
    }
};
