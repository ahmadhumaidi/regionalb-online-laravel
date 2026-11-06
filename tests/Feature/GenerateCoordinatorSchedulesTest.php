<?php

namespace Tests\Feature;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenerateCoordinatorSchedulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
        ]]);
        Schema::table('partner_campuses', fn ($table) => $table->string('wilayah')->nullable());
    }

    public function test_command_generates_for_both_areas_and_preserves_edited_schedules_on_retry(): void
    {
        foreach ([['Regional A', 'Regional 1', true], ['Regional B', 'Regional 6', true], ['Regional B', 'Regional 4', false]] as [$area, $regional, $active]) {
            RsmUser::create(['name' => 'Korwil '.$regional, 'username' => 'cron_'.$regional,
                'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator',
                'area' => $area, 'regional' => $regional, 'is_active' => $active]);
            DB::table('partner_campuses')->insert(['name' => 'Kampus '.$regional,
                'display_name' => 'Kampus '.$regional, 'kode_kampus' => $regional,
                'address' => '-', 'wilayah' => $regional]);
        }

        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 18:00:00', 'UTC'));
        $this->artisan('rsm:generate-coordinator-schedules', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('rsm_coordinator_schedules', 0);
        $this->artisan('rsm:generate-coordinator-schedules')->assertSuccessful();
        $this->assertDatabaseCount('rsm_coordinator_schedules', 60);
        $this->assertSame(30, RsmCoordinatorSchedule::where('area', 'Regional A')->count());
        $this->assertSame(30, RsmCoordinatorSchedule::where('area', 'Regional B')->count());
        $this->assertSame(0, RsmCoordinatorSchedule::where('wilayah', 'Regional 4')->count());
        $this->assertSame('2026-11-01', RsmCoordinatorSchedule::orderBy('schedule_date')->firstOrFail()->schedule_date->toDateString());

        $existing = RsmCoordinatorSchedule::firstOrFail();
        $existing->update(['unit_name' => 'Kampus diubah manual', 'status' => 'Selesai', 'result_text' => 'Sudah dikunjungi']);
        $this->artisan('rsm:generate-coordinator-schedules')->assertSuccessful();
        $this->assertDatabaseCount('rsm_coordinator_schedules', 60);
        $this->assertSame('Kampus diubah manual', $existing->fresh()->unit_name);
        $this->assertSame('Selesai', $existing->fresh()->status);
        $this->assertSame('Sudah dikunjungi', $existing->fresh()->result_text);
        $this->travelBack();
    }

    public function test_monthly_schedule_runs_on_first_day_at_nine_jakarta_time(): void
    {
        // Resolve the console kernel so Laravel registers application schedules.
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'rsm:generate-coordinator-schedules'));
        $this->assertNotNull($event);
        $this->assertSame('0 9 1 * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->artisan('rsm:generate-coordinator-schedules', ['--month' => '2026-13'])->assertFailed();
        $this->assertDatabaseCount('rsm_coordinator_schedules', 0);
    }
}
