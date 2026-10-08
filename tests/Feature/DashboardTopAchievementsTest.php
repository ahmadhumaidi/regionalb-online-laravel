<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTopAchievementsTest extends TestCase
{
    public function test_top_achievements_combine_both_areas_and_respect_regional_filter(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
        ]]);
        for ($i = 1; $i <= 7; $i++) {
            $area = $i <= 3 ? 'Regional A' : 'Regional B';
            RsmUser::create(['name' => 'Staff '.$i, 'username' => 'staff'.$i, 'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => $area, 'regional' => 'Regional '.$i, 'is_active' => true]);
            foreach (['Closing Kampus Regional', 'Closing Personal Per Regional'] as $report) {
                DB::table('rsm_collab_daily_metrics')->insert([
                    'report_name' => $report, 'metric_date' => '2026-10-01', 'entity_key' => 'entity'.$i,
                    'regional' => 'Regional '.$i, 'campus_name' => 'Campus '.$i, 'staff_name' => 'Staff '.$i,
                    'value' => [1 => 100, 2 => 50, 3 => 80, 4 => 70, 5 => 20, 6 => 90, 7 => 30][$i],
                ]);
            }
        }
        $user = new RsmUser(['username' => 'raffandy', 'role' => 'executive_director', 'area' => 'Regional A']);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-08', 'wilayah' => '', 'unit_name' => '', 'staff_name' => ''];
        $controller = new DashboardController;
        $all = new \ReflectionMethod($controller, 'allAreaAchievements');
        $staff = new \ReflectionMethod($controller, 'topStaffAchievement');
        [$campuses, $staffRows] = $all->invoke($controller, $filters, $user);
        [$topStaff, $max] = $staff->invoke($controller, 'Regional A', $user, $staffRows);
        $this->assertCount(7, $campuses['rows']);
        $this->assertSame(['Regional 1', 'Regional 6', 'Regional 3', 'Regional 4', 'Regional 2'], array_column(array_slice($campuses['rows'], 0, 5), 'regional'));
        $this->assertSame(['Staff 1', 'Staff 6', 'Staff 3', 'Staff 4', 'Staff 2'], array_column($topStaff, 'name'));
        $this->assertSame(100.0, $max);
        $this->assertSame('staff', $topStaff[1]['user_role']);

        $filters['wilayah'] = 'Regional 6';
        [$campuses, $staffRows] = $all->invoke($controller, $filters, $user);
        $this->assertCount(1, $campuses['rows']);
        $this->assertCount(1, $staffRows['rows']);
        $this->assertSame('Regional 6', $campuses['rows'][0]['regional']);

        \Illuminate\Support\Facades\Auth::setUser($user);
        $request = \Illuminate\Http\Request::create('/pencapaian', 'GET', ['date_from' => '2026-10-01', 'date_to' => '2026-10-08']);
        $data = (new \App\Http\Controllers\AchievementController)->index($request)->getData();
        $this->assertCount(7, $data['rowsByRegional']);
        $this->assertCount(7, $data['referenceOptions']['regionals']);
        $this->assertSame('440', $data['summaryCards'][0]['value']);
        $this->assertSame('7', $data['summaryCards'][2]['value']);
        $request->query->set('wilayah', 'Regional 6');
        $data = (new \App\Http\Controllers\AchievementController)->index($request)->getData();
        $this->assertCount(1, $data['rowsByRegional']);
        $this->assertSame('Regional 6', $data['rowsByRegional'][0]['regional']);
        $this->assertSame('90', $data['summaryCards'][0]['value']);

        $request->setUserResolver(fn () => $user);
        $request->query->set('wilayah', '');
        $data = (new \App\Http\Controllers\ClosingCampusController)->index($request)->getData();
        $this->assertCount(7, $data['regionals']);
        $this->assertCount(7, $data['grouped']);
        $this->assertSame(440.0, array_sum(array_column($data['campus']['rows'], 'registrasi')));
        $this->assertSame(100.0, $data['campus']['max_value']);
        $request->query->set('wilayah', 'Regional 6');
        $data = (new \App\Http\Controllers\ClosingCampusController)->index($request)->getData();
        $this->assertCount(1, $data['campus']['rows']);
        $this->assertSame('Regional 6', $data['campus']['rows'][0]['regional']);
        $request->query->set('unit_name', 'Campus 1');
        $data = (new \App\Http\Controllers\ClosingCampusController)->index($request)->getData();
        $this->assertSame([], $data['campus']['rows']);
    }
}
