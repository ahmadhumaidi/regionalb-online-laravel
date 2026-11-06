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
    public function test_journey_uses_daily_and_periodic_sources_without_mixing_live_streams(): void
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
        foreach (['Follow Up BDC' => 30, 'Share FB Group' => 3, 'Live Streaming' => 1, 'Absen Tepat Waktu' => 1] as $report => $value) {
            DB::table('rsm_collab_daily_metrics')->insert(['report_name' => $report, 'metric_date' => '2026-10-01', 'entity_key' => strtolower(str_replace(' ', '-', $report)), 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => $value, 'synced_at' => '2026-10-01 09:15:00']);
        }
        RsmReport::create(['area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-01', 'user_id' => $staff->id, 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Dikirim', 'title' => 'Laporan nyata']);

        foreach (['2026-08-31' => 20, '2026-09-01' => 2, '2026-10-01' => 4, '2026-10-02' => 20] as $metricDate => $value) {
            DB::table('rsm_collab_daily_metrics')->insert(['report_name' => 'Pasang Spanduk', 'metric_date' => $metricDate, 'entity_key' => 'spanduk-'.$metricDate, 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => $value]);
        }
        DB::table('rsm_collab_daily_metrics')->insert(['report_name' => 'Pasang Spanduk', 'metric_date' => '2026-10-01', 'entity_key' => 'spanduk-other-region', 'staff_name' => $staff->name, 'regional' => 'Regional 5', 'value' => 50]);

        $ingest = new \ReflectionMethod(\App\Services\CollabSourceService::class, 'ingestDailyMetrics');
        $canvassingRows = [
            ['No', 'NIK - Nama', '', 'Oct 2026', 'Oct 2026', 'Total'],
            ['No', 'NIK - Nama', '', '01', '02', 'Total'],
            array_fill(0, 6, 'Regional 4 - Coordinator'),
            ['1', 'SG.001 - '.$staff->name, '', '3', '20', '23'],
            array_fill(0, 6, 'Total Regional 4 - Coordinator'),
            array_fill(0, 6, 'Regional 5 - Coordinator'),
            ['2', 'SG.002 - '.$outsideRegionalStaff->name, '', '1', '0', '1'],
        ];
        $ingest->invoke(null, 'Canvasing', ['tables' => [$canvassingRows]]);
        $ingest->invoke(null, 'Canvasing', ['tables' => [$canvassingRows]]);
        $this->assertSame(3.0, (float) DB::table('rsm_collab_daily_metrics')->where('report_name', 'Canvasing')->where('staff_name', $staff->name)->where('metric_date', '2026-10-01')->value('value'));
        DB::table('rsm_collab_daily_metrics')->insert(['report_name' => 'Sebar Brosur', 'metric_date' => '2026-10-01', 'entity_key' => 'brosur', 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => 200]);

        foreach (['2026-07-31' => 9000, '2026-08-01' => 800] as $metricDate => $value) {
            DB::table('rsm_collab_daily_metrics')->insert(['report_name' => 'Sebar Brosur', 'metric_date' => $metricDate, 'entity_key' => 'brosur-'.$metricDate, 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => $value]);
        }
        $row = collect(StaffJourneyService::build($viewer, '2026-10-01')['staff'])->firstWhere('id', $staff->id);

        $this->assertTrue($row['activities']['spanduk']['tracked']);
        $this->assertTrue($row['activities']['spanduk']['done']);
        $this->assertSame(6.0, $row['activities']['spanduk']['actual']);
        $previousRow = collect(StaffJourneyService::build($viewer, '2026-09-30')['staff'])->firstWhere('id', $staff->id);
        $this->assertSame(22.0, $previousRow['activities']['spanduk']['actual']);
        $emptyRow = collect(StaffJourneyService::build($viewer, '2026-10-01')['staff'])->firstWhere('id', $secondUnitStaff->id);
        $this->assertTrue($emptyRow['activities']['spanduk']['tracked']);
        $this->assertFalse($emptyRow['activities']['spanduk']['done']);
        $this->assertSame(0.0, $emptyRow['activities']['spanduk']['actual']);

        $this->assertSame(1000, $row['activities']['brosur']['target']);
        $this->assertTrue($row['activities']['brosur']['done']);
        $this->assertSame(1000.0, $row['activities']['brosur']['actual']);
        $this->assertTrue($row['activities']['canvassing']['done']);
        $this->assertSame(3.0, $row['activities']['canvassing']['actual']);
        $this->assertFalse($row['activities']['live_night']['done']);
        $this->assertTrue($row['activities']['live_day']['done']);
        $nextWeek = collect(StaffJourneyService::build($viewer, '2026-10-05')['staff'])->firstWhere('id', $staff->id);
        $this->assertSame(1000.0, $nextWeek['activities']['brosur']['actual']);
        $november = collect(StaffJourneyService::build($viewer, '2026-11-01')['staff'])->firstWhere('id', $staff->id);
        $this->assertSame(200.0, $november['activities']['brosur']['actual']);
        $this->assertFalse($november['activities']['brosur']['done']);
        $this->assertSame(0.0, $nextWeek['activities']['canvassing']['actual']);
        DB::table('rsm_collab_daily_metrics')->insert(['report_name' => 'Absen Staff', 'metric_date' => '2026-10-01', 'entity_key' => 'late-staff', 'staff_name' => $secondUnitStaff->name, 'regional' => 'Regional 4', 'value' => 1]);
        $lateRow = collect(StaffJourneyService::build($viewer, '2026-10-01')['staff'])->firstWhere('id', $secondUnitStaff->id);
        $this->assertFalse($lateRow['activities']['absen']['done']);

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
        $regionalBStaff = collect(StaffJourneyService::build($coordinator, '2026-10-01', 'Regional B')['staff']);
        $this->assertContains($outsideRegionalStaff->id, $regionalBStaff->pluck('id')->all());
        $this->assertSame(['Regional B'], $regionalBStaff->pluck('area')->unique()->values()->all());
        $restrictedStaff = collect(StaffJourneyService::build($staff, '2026-10-01', 'Regional B')['staff']);
        $this->assertNotContains($outsideRegionalStaff->id, $restrictedStaff->pluck('id')->all());

        $unitStaff = collect(StaffJourneyService::build($staff, '2026-10-01')['staff']);
        $this->assertEqualsCanonicalizing([$staff->id, $secondUnitStaff->id], $unitStaff->pluck('id')->all());
        $this->assertNotContains($outsideRegionalStaff->id, $unitStaff->pluck('id')->all());

        $leaderboard = collect(StaffJourneyService::leaderboard($viewer, '2026-10-01', '2026-10-01'));
        $leaderboardRow = $leaderboard->firstWhere('id', $staff->id);
        $this->assertSame(9, $leaderboardRow['activity_total']);
        $this->assertSame($staff->id, $leaderboard->first()['id']);

        $ownLeaderboard = collect(StaffJourneyService::leaderboard($coordinator, '2026-10-01', '2026-10-01'));
        $this->assertNotContains($outsideRegionalStaff->id, $ownLeaderboard->pluck('id')->all());
        $regionalBLeaderboard = collect(StaffJourneyService::leaderboard($coordinator, '2026-10-01', '2026-10-01', 'Regional B'));
        $this->assertContains($outsideRegionalStaff->id, $regionalBLeaderboard->pluck('id')->all());
        $this->assertSame(['Regional B'], $regionalBLeaderboard->pluck('area')->unique()->values()->all());
        $staffLeaderboard = collect(StaffJourneyService::leaderboard($staff, '2026-10-01', '2026-10-01', 'Regional B'));
        $this->assertNotContains($outsideRegionalStaff->id, $staffLeaderboard->pluck('id')->all());

        DB::enableQueryLog();
        DB::flushQueryLog();
        $performance = StaffJourneyService::profilePerformance($staff, '2026-10');
        $performanceQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, $performanceQueryCount, 'Monthly profile chart must load sources once, rather than once per week.');
        $this->assertCount(5, $performance['weeks']);
        $this->assertSame('2026-10-01', $performance['weeks'][0]['from']);
        $this->assertSame('2026-10-07', $performance['weeks'][0]['to']);
        $this->assertSame('2026-10-31', $performance['weeks'][4]['to']);
        $this->assertEquals(24, $performance['weeks'][0]['values']['spanduk']);
        $this->assertEquals(30, $performance['weeks'][0]['values']['fu_bdc']);
        $this->assertEquals(1, $performance['weeks'][0]['values']['instagram']);
        $this->assertEquals(0, $performance['weeks'][4]['values']['fu_bdc']);
        $previous = StaffJourneyService::profilePerformance($staff, '2026-09');
        $this->assertEquals(2, $previous['weeks'][0]['values']['spanduk']);
        $november = StaffJourneyService::profilePerformance($staff, '2026-11');
        $this->assertCount(5, $november['weeks']);
        $this->assertSame('2026-11-07', $november['weeks'][0]['to']);
        $this->assertSame('2026-11-29', $november['weeks'][4]['from']);
        $this->assertSame('2026-11-30', $november['weeks'][4]['to']);
        $february = StaffJourneyService::profilePerformance($staff, '2026-02');
        $this->assertCount(4, $february['weeks']);
        $this->assertSame('2026-02-28', $february['weeks'][3]['to']);

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

        RsmReport::create(['area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-06', 'user_id' => $staff->id, 'wilayah' => 'Regional 4', 'unit_name' => 'Kampus Real', 'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Dikirim', 'title' => 'Live Streaming Night', 'activity_kind' => 'Live Streaming Night']);
        $beforeSync = collect(StaffJourneyService::build($viewer, '2026-10-07')['staff'])->firstWhere('id', $staff->id);
        $this->assertFalse($beforeSync['activities']['live_night']['done']);
        DB::table('rsm_collab_daily_metrics')->insert([
            'report_name' => 'Live Streaming Night', 'metric_date' => '2026-10-06',
            'entity_key' => 'night-ari', 'staff_name' => $staff->name, 'regional' => 'Regional 4', 'value' => 1,
        ]);
        $row = collect(StaffJourneyService::build($viewer, '2026-10-07')['staff'])->firstWhere('id', $staff->id);
        $this->assertTrue($row['activities']['live_night']['done']);
        $this->assertFalse($row['activities']['live_day']['done']);


        $this->assertTrue($row['activities']['affiliate']['tracked']);
        $this->assertTrue($row['activities']['affiliate']['done']);
        $this->assertSame(1, $row['activities']['affiliate']['actual']);
        $this->assertSame(0, $row['completed_daily'], 'KPI mingguan tidak boleh menambah checkpoint harian.');
    }
}
