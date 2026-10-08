<?php

namespace Tests\Feature;

use App\Models\RsmSeniorActivityReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeniorActivityReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_10_06_000000_create_rsm_senior_activity_reports_table.php',
        ]]);
    }

    public function test_senior_manager_can_create_visit_report(): void
    {
        $senior = $this->user(RsmUser::ROLE_SENIOR, 910001);

        $this->actingAs($senior)->post(route('laporan-senior.store'), [
            'activity_date' => '2026-10-06',
            'activity_type' => 'Kunjungan',
            'title' => 'Kunjungan evaluasi PMB',
            'location' => 'Kampus Contoh',
            'participants' => 'Rektor dan tim marketing',
            'agenda' => 'Evaluasi pencapaian dan kendala.',
            'result_text' => 'Disepakati perbaikan follow up leads.',
            'next_action' => 'Monitoring pekan depan.',
        ])->assertRedirect(route('laporan-senior.index'));

        $this->assertDatabaseHas('rsm_senior_activity_reports', [
            'user_id' => $senior->id,
            'activity_type' => 'Kunjungan',
            'title' => 'Kunjungan evaluasi PMB',
        ]);
    }

    public function test_staff_cannot_access_senior_activity_reports(): void
    {
        $staff = $this->user(RsmUser::ROLE_STAFF, 910002);

        $this->actingAs($staff)->get(route('laporan-senior.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('laporan-senior.store'), [])->assertForbidden();
    }

    public function test_senior_manager_cannot_edit_another_managers_report(): void
    {
        $owner = $this->user(RsmUser::ROLE_SENIOR, 910003);
        $other = $this->user(RsmUser::ROLE_SENIOR, 910004);
        $report = RsmSeniorActivityReport::create([
            'area' => 'Regional B', 'user_id' => $owner->id, 'activity_date' => '2026-10-06',
            'activity_type' => 'Rapat', 'title' => 'Rapat area', 'agenda' => 'Evaluasi', 'result_text' => 'Selesai',
        ]);

        $this->actingAs($other)->get(route('laporan-senior.edit', $report))->assertNotFound();
    }

    public function test_menu_is_only_visible_to_senior_tier(): void
    {
        $seniorKeys = collect(\App\Support\Menu::sections(new RsmUser(['role' => RsmUser::ROLE_SENIOR])))
            ->flatMap(fn (array $section) => array_column($section['items'], 'key'));
        $staffKeys = collect(\App\Support\Menu::sections(new RsmUser(['role' => RsmUser::ROLE_STAFF])))
            ->flatMap(fn (array $section) => array_column($section['items'], 'key'));

        $this->assertContains('laporan-senior', $seniorKeys);
        $this->assertNotContains('laporan-senior', $staffKeys);
    }

    private function user(string $role, int $id): RsmUser
    {
        return RsmUser::create([
            'id' => $id,
            'name' => 'User '.$id,
            'username' => 'user_'.$id,
            'password_hash' => 'x',
            'role' => $role,
            'jabatan' => $role,
            'area' => 'Regional B',
            'is_active' => true,
        ]);
    }
}
