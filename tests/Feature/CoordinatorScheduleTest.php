<?php

namespace Tests\Feature;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoordinatorScheduleTest extends TestCase
{
    public function test_saving_visit_report_automatically_marks_schedule_as_finished(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
        ]]);

        $user = RsmUser::create([
            'name' => 'Test Coordinator Schedule Admin',
            'username' => 'test_coordinator_report_admin',
            'password_hash' => 'x',
            'role' => 'super_user',
            'jabatan' => 'Super User',
            'area' => 'Regional B',
            'is_active' => true,
        ]);
        $schedule = $this->schedule('Unit Laporan');

        $response = $this->actingAs($user)
            ->from('/jadwal-koordinator')
            ->patch("/jadwal-koordinator/{$schedule->id}/laporan", [
                'status' => 'Rencana',
                'result_text' => 'Kunjungan telah dilaksanakan.',
                'next_action' => 'Tindak lanjut pekan depan.',
            ]);

        $response->assertRedirect('/jadwal-koordinator');
        $response->assertSessionHasNoErrors();
        $this->assertSame('Selesai', $schedule->fresh()->status);
        $this->assertSame('Kunjungan telah dilaksanakan.', $schedule->fresh()->result_text);
    }

    public function test_update_rejects_duplicate_unit_for_same_coordinator_and_date(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
        ]]);

        $user = RsmUser::create([
            'name' => 'Test Coordinator Schedule Admin',
            'username' => 'test_coordinator_schedule_admin',
            'password_hash' => 'x',
            'role' => 'super_user',
            'jabatan' => 'Super User',
            'area' => 'Regional B',
            'is_active' => true,
        ]);

        $existing = $this->schedule('Unit Tujuan');
        $edited = $this->schedule('Unit Lama');

        $response = $this->actingAs($user)
            ->from('/jadwal-koordinator')
            ->patch("/jadwal-koordinator/{$edited->id}", [
                'unit_name' => $existing->unit_name,
                'visit_type' => 'Visit',
                'agenda' => 'Agenda yang diperbarui',
            ]);

        $response->assertRedirect('/jadwal-koordinator');
        $response->assertSessionHasErrors([
            'unit_name' => 'Jadwal untuk unit ini sudah ada pada tanggal yang sama.',
        ]);
        $this->assertSame('Unit Lama', $edited->fresh()->unit_name);
    }

    private function schedule(string $unitName): RsmCoordinatorSchedule
    {
        return RsmCoordinatorSchedule::create([
            'area' => 'Regional B',
            'schedule_date' => '2026-08-18',
            'koordinator_name' => 'M. Nor Abidin',
            'wilayah' => 'Regional 6',
            'unit_name' => $unitName,
            'visit_type' => 'Visit',
            'agenda' => 'Agenda kunjungan',
            'status' => 'Rencana',
        ]);
    }
}
