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

    public function test_roles_above_senior_manager_see_schedules_across_selected_areas(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
        ]]);
        $schedule = $this->schedule('Unit Regional B');
        foreach ([['raffandy', 'executive_director'], ['other_ed', 'executive_director'], ['director', 'director'], ['admin', 'super_user']] as [$username, $role]) {
            $user = new RsmUser(['username' => $username, 'role' => $role, 'area' => 'Regional A']);
            $controller = new \App\Http\Controllers\CoordinatorScheduleController;
            $fetch = new \ReflectionMethod($controller, 'fetchSchedules');

            [$rows, , $summary] = $fetch->invoke($controller, $user->viewingArea(), '2026-08', [], $user);
            $this->assertSame([$schedule->id], $rows->modelKeys());
            $this->assertSame(1, $summary['Regional 6']['total']);

            $user->selectedArea = 'Regional A';
            [$rows] = $fetch->invoke($controller, $user->viewingArea(), '2026-08', [], $user);
            $this->assertCount(0, $rows);

            $user->selectedArea = 'Regional B';
            [$rows] = $fetch->invoke($controller, $user->viewingArea(), '2026-08', [], $user);
            $this->assertSame([$schedule->id], $rows->modelKeys());
            (new \ReflectionMethod($controller, 'scope'))->invoke($controller, $schedule, $user);
        }
    }

    public function test_generation_uses_active_coordinators_and_keeps_each_schedule_in_its_area(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
        ]]);
        \Illuminate\Support\Facades\Schema::table('partner_campuses', function ($table) {
            $table->string('wilayah')->nullable();
        });
        $users = [];
        foreach ([['Regional A', 'Regional 1'], ['Regional B', 'Regional 4']] as [$area, $regional]) {
            $users[$area] = RsmUser::create([
                'name' => 'Korwil '.$regional, 'username' => 'korwil_'.$regional,
                'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Korwil',
                'area' => $area, 'regional' => $regional, 'is_active' => true,
            ]);
            \Illuminate\Support\Facades\DB::table('partner_campuses')->insert([
                'name' => 'Kampus '.$regional, 'display_name' => 'Kampus '.$regional,
                'kode_kampus' => $regional, 'address' => '-', 'wilayah' => $regional,
            ]);
        }
        RsmUser::create([
            'name' => 'Korwil Nonaktif', 'username' => 'inactive_korwil',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Korwil',
            'area' => 'Regional A', 'regional' => 'Regional 2', 'is_active' => false,
        ]);
        $senior = RsmUser::create([
            'name' => 'Senior A', 'username' => 'senior_a', 'password_hash' => 'x',
            'role' => 'senior', 'jabatan' => 'Senior', 'area' => 'Regional A', 'is_active' => true,
        ]);
        $this->actingAs($senior)->from('/jadwal-koordinator')->post('/jadwal-koordinator/generate', ['month' => '2026-10'])
            ->assertRedirect('/jadwal-koordinator')->assertSessionHasNoErrors();
        $this->assertSame(31, RsmCoordinatorSchedule::count());
        $this->assertSame([$users['Regional A']->id], RsmCoordinatorSchedule::distinct()->pluck('koordinator_user_id')->all());
        $this->assertSame(['Regional A'], RsmCoordinatorSchedule::distinct()->pluck('area')->all());
        $this->assertSame('Kampus Regional 1', RsmCoordinatorSchedule::whereDate('schedule_date', '2026-10-01')->first()->unit_name);

        // Retrying must not duplicate the existing agendas.
        $this->post('/jadwal-koordinator/generate', ['month' => '2026-10'])->assertSessionHasNoErrors();
        $this->assertSame(31, RsmCoordinatorSchedule::count());

        $this->actingAs($users['Regional B'])->post('/jadwal-koordinator/generate', ['month' => '2026-10'])->assertSessionHasNoErrors();
        $this->assertSame(31, RsmCoordinatorSchedule::where('area', 'Regional B')->where('koordinator_user_id', $users['Regional B']->id)->count());

        $senior->role = 'director';
        $senior->save();
        $this->actingAs($senior)->post('/jadwal-koordinator/generate', ['month' => '2026-11'])->assertSessionHasNoErrors();
        $this->assertSame(30, RsmCoordinatorSchedule::where('area', 'Regional A')->whereMonth('schedule_date', 11)->count());
        $this->assertSame(30, RsmCoordinatorSchedule::where('area', 'Regional B')->whereMonth('schedule_date', 11)->count());
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
