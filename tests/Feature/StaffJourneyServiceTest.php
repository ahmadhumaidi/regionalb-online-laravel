<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmSocialAccount;
use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use App\Services\Dashboard\StaffJourneyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffJourneyServiceTest extends TestCase
{
    public function test_journey_uses_real_daily_sources_and_marks_untracked_tasks(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105957_create_rsm_social_accounts_table.php',
            'database/migrations/2026_08_05_105958_create_rsm_social_posts_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
        ]]);

        $viewer = RsmUser::create(['name' => 'Journey Admin', 'username' => 'journey_admin', 'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User', 'is_active' => true]);
        $coordinator = RsmUser::create(['name' => 'Journey Korwil', 'username' => 'journey_korwil', 'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah', 'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Kantor Regional', 'is_active' => true]);
        $staff = RsmUser::create(['name' => 'Journey Real Staff', 'username' => 'journey_real', 'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Kampus Real', 'is_active' => true]);
        $secondUnitStaff = RsmUser::create(['name' => 'Journey Second Unit', 'username' => 'journey_second', 'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Kampus Kedua', 'is_active' => true]);
        $outsideRegionalStaff = RsmUser::create(['name' => 'Journey Outside Regional', 'username' => 'journey_outside', 'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 5', 'campus_name' => 'Kampus Luar', 'is_active' => true]);
        $account = RsmSocialAccount::create(['area' => 'Regional B', 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'instagram_username' => '@kampusreal']);
        foreach (['feed', 'story', 'facebook', 'tiktok'] as $type) {
            RsmSocialPost::create(['account_id' => $account->id, 'area' => 'Regional B', 'post_date' => '2026-10-01', 'media_type' => $type]);
        }
        foreach (['Follow Up BDC' => 30, 'Share FB Group' => 5, 'Live Streaming' => 1, 'Absen Staff' => 1] as $report => $value) {
            DB::table('rsm_collab_daily_metrics')->insert(['report_name' => $report, 'metric_date' => '2026-10-01', 'entity_key' => strtolower(str_replace(' ', '-', $report)), 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => $value]);
        }
        RsmReport::create(['area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-01', 'user_id' => $staff->id, 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Dikirim', 'title' => 'Laporan nyata']);

        $row = collect(StaffJourneyService::build($viewer, '2026-10-01')['staff'])->firstWhere('id', $staff->id);

        $this->assertSame(9, $row['completed_daily']);
        $this->assertTrue($row['activities']['fu_bdc']['done']);
        $this->assertTrue($row['activities']['tiktok']['tracked']);
        $this->assertTrue($row['activities']['tiktok']['done']);
        $this->assertTrue($row['activities']['absen']['tracked']);
        $this->assertTrue($row['activities']['absen']['done']);

        $coordinatorStaff = collect(StaffJourneyService::build($coordinator, '2026-10-01')['staff']);
        $this->assertEqualsCanonicalizing([$staff->id, $secondUnitStaff->id], $coordinatorStaff->pluck('id')->all());
        $this->assertNotContains($outsideRegionalStaff->id, $coordinatorStaff->pluck('id')->all());

        $unitStaff = collect(StaffJourneyService::build($staff, '2026-10-01')['staff']);
        $this->assertEqualsCanonicalizing([$staff->id, $secondUnitStaff->id], $unitStaff->pluck('id')->all());
        $this->assertNotContains($outsideRegionalStaff->id, $unitStaff->pluck('id')->all());
    }
}
