<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmSocialAccount;
use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use App\Services\Dashboard\StaffJourneyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/rsm-journey-'.uniqid()]);
        Storage::forgetDisk('local');
        $scheduleCells = array_fill(0, 36, '');
        $scheduleCells[0] = 'SG.001';
        $scheduleCells[1] = $staff->name;
        $scheduleCells[5] = 'L';
        $scheduleRow = '<tr>'.collect($scheduleCells)->map(fn (string $cell): string => '<td>'.$cell.'</td>')->implode('').'</tr>';
        Storage::disk('local')->put('jadwal_personalia.json', json_encode([
            'zonas' => [2 => ['fetched_at' => '2026-10-01 06:00:00', 'table_html' => '<table>'.$scheduleRow.'</table>']],
        ]));
        foreach (['feed', 'story', 'facebook', 'tiktok'] as $type) {
            RsmSocialPost::create(['account_id' => $account->id, 'area' => 'Regional B', 'post_date' => '2026-10-01', 'media_type' => $type]);
        }
        foreach (['Follow Up BDC' => 30, 'Share FB Group' => 3, 'Live Streaming' => 1, 'Absen Staff' => 1] as $report => $value) {
            DB::table('rsm_collab_daily_metrics')->insert(['report_name' => $report, 'metric_date' => '2026-10-01', 'entity_key' => strtolower(str_replace(' ', '-', $report)), 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => $value, 'synced_at' => '2026-10-01 09:15:00']);
        }
        RsmReport::create(['area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-01', 'user_id' => $staff->id, 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Dikirim', 'title' => 'Laporan nyata']);

        $row = collect(StaffJourneyService::build($viewer, '2026-10-01')['staff'])->firstWhere('id', $staff->id);

        $this->assertSame('Regional B', $row['area']);
        $this->assertTrue($row['is_libur']);
        $this->assertSame('L', $row['schedule_status']);
        $this->assertSame(9, $row['completed_daily']);
        $this->assertTrue($row['activities']['fu_bdc']['done']);
        $this->assertTrue($row['activities']['tiktok']['tracked']);
        $this->assertTrue($row['activities']['tiktok']['done']);
        $this->assertTrue($row['activities']['absen']['tracked']);
        $this->assertTrue($row['activities']['absen']['done']);
        $this->assertCount(9, $row['checkpoint_times']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $row['checkpoint_time']);
        $this->assertSame($row['checkpoint_time'], $row['finish_time']);

        $coordinatorStaff = collect(StaffJourneyService::build($coordinator, '2026-10-01')['staff']);
        $this->assertEqualsCanonicalizing([$staff->id, $secondUnitStaff->id], $coordinatorStaff->pluck('id')->all());
        $this->assertNotContains($outsideRegionalStaff->id, $coordinatorStaff->pluck('id')->all());

        $unitStaff = collect(StaffJourneyService::build($staff, '2026-10-01')['staff']);
        $this->assertEqualsCanonicalizing([$staff->id, $secondUnitStaff->id], $unitStaff->pluck('id')->all());
        $this->assertNotContains($outsideRegionalStaff->id, $unitStaff->pluck('id')->all());

        $leaderboard = collect(StaffJourneyService::leaderboard($viewer, '2026-10-01', '2026-10-01'));
        $leaderboardRow = $leaderboard->firstWhere('id', $staff->id);
        $this->assertSame(9, $leaderboardRow['activity_total']);
        $this->assertSame($staff->id, $leaderboard->first()['id']);

    }

    public function test_affiliate_group_report_is_detected_as_weekly_kpi(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105957_create_rsm_social_accounts_table.php',
            'database/migrations/2026_08_05_105958_create_rsm_social_posts_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
        ]]);

        $viewer = RsmUser::create(['name' => 'Journey Admin', 'username' => 'journey_admin_affiliate', 'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User', 'is_active' => true]);
        $staff = RsmUser::create(['name' => 'Ari Oktavian Aji', 'username' => 'ari_affiliate', 'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Kampus Real', 'is_active' => true]);
        RsmReport::create(['area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-05', 'user_id' => $staff->id, 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Disetujui', 'title' => 'Sapa Grup Affiliate', 'activity_kind' => 'Sapa Grup Affiliate']);

        $row = collect(StaffJourneyService::build($viewer, '2026-10-07')['staff'])->firstWhere('id', $staff->id);

        $this->assertTrue($row['activities']['affiliate']['tracked']);
        $this->assertTrue($row['activities']['affiliate']['done']);
        $this->assertSame(1, $row['activities']['affiliate']['actual']);
        $this->assertSame(0, $row['completed_daily'], 'KPI mingguan tidak boleh menambah checkpoint harian.');
    }
}
