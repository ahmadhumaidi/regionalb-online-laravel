<?php

namespace Tests\Feature;

use App\Models\RsmNotification;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090000_add_escalated_to_role_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090001_create_rsm_notifications_table.php',
        ]]);
    }

    public function test_kendala_notification_reaches_koordinator_and_senior_tier_roles(): void
    {
        $this->migrate();

        $recipients = collect([
            ['id' => 910001, 'name' => 'Notif Super', 'role' => RsmUser::ROLE_SUPER_USER, 'regional' => null],
            ['id' => 910002, 'name' => 'Notif Executive', 'role' => RsmUser::ROLE_EXECUTIVE_DIRECTOR, 'regional' => null],
            ['id' => 910003, 'name' => 'Notif Director', 'role' => RsmUser::ROLE_DIRECTOR, 'regional' => null],
            ['id' => 910004, 'name' => 'Notif Senior', 'role' => RsmUser::ROLE_SENIOR, 'regional' => null],
            ['id' => 910005, 'name' => 'Notif Korwil', 'role' => RsmUser::ROLE_KOORDINATOR, 'regional' => 'Regional 6'],
            ['id' => 910006, 'name' => 'Wrong Korwil', 'role' => RsmUser::ROLE_KOORDINATOR, 'regional' => 'Regional 7'],
            ['id' => 910007, 'name' => 'Inactive Senior', 'role' => RsmUser::ROLE_SENIOR, 'regional' => null, 'is_active' => false],
        ])->map(fn (array $user) => RsmUser::create([
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => 'notif_'.$user['id'],
            'password_hash' => 'x',
            'role' => $user['role'],
            'jabatan' => $user['role'],
            'regional' => $user['regional'],
            'area' => 'Regional B',
            'is_active' => $user['is_active'] ?? true,
        ]));

        $report = RsmReport::create([
            'area' => 'Regional B',
            'report_type' => RsmReport::TYPE_OTHER,
            'report_date' => now(),
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Staff Kendala',
            'created_by_role' => RsmUser::ROLE_STAFF,
            'status' => 'Dikirim',
            'title' => 'Kendala test',
            'obstacle_text' => 'Ada kendala',
        ]);

        NotificationService::notifyKendala($report);

        $expectedIds = $recipients
            ->whereIn('name', ['Notif Super', 'Notif Executive', 'Notif Director', 'Notif Senior', 'Notif Korwil'])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
        $notifiedIds = RsmNotification::query()->pluck('recipient_user_id')->sort()->values()->all();

        $this->assertSame($expectedIds, $notifiedIds);

        RsmNotification::query()->delete();
        $report->delete();
        $recipients->each->delete();
    }

    public function test_escalation_to_senior_notifies_every_senior_tier_role(): void
    {
        $this->migrate();

        $actor = RsmUser::create([
            'id' => 910101,
            'name' => 'Kundi Test',
            'username' => 'kundi_test',
            'password_hash' => 'x',
            'role' => RsmUser::ROLE_KOORDINATOR,
            'jabatan' => 'Koordinator Wilayah',
            'regional' => 'Regional 5',
            'area' => 'Regional B',
            'is_active' => true,
        ]);
        $recipients = collect([
            ['id' => 910102, 'name' => 'Ahmad Super', 'role' => RsmUser::ROLE_SUPER_USER],
            ['id' => 910103, 'name' => 'Executive Test', 'role' => RsmUser::ROLE_EXECUTIVE_DIRECTOR],
            ['id' => 910104, 'name' => 'Director Test', 'role' => RsmUser::ROLE_DIRECTOR],
            ['id' => 910105, 'name' => 'Senior Test', 'role' => RsmUser::ROLE_SENIOR],
            ['id' => 910106, 'name' => 'Mentor Test', 'role' => RsmUser::ROLE_MENTOR],
        ])->map(fn (array $user) => RsmUser::create([
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => 'escalation_'.$user['id'],
            'password_hash' => 'x',
            'role' => $user['role'],
            'jabatan' => $user['role'],
            'area' => 'Regional B',
            'is_active' => true,
        ]));

        $report = RsmReport::create([
            'area' => 'Regional B',
            'report_type' => RsmReport::TYPE_OTHER,
            'report_date' => now(),
            'wilayah' => 'Regional 5',
            'unit_name' => 'Universitas AKI Semarang',
            'staff_name' => 'Fuad',
            'created_by_role' => RsmUser::ROLE_STAFF,
            'status' => 'Dikirim',
            'title' => 'Kendala eskalasi',
            'obstacle_text' => 'Butuh senior tier',
        ]);

        NotificationService::notifyEscalation($report, RsmUser::ROLE_SENIOR, $actor);

        $expectedIds = $recipients
            ->whereIn('role', [
                RsmUser::ROLE_SUPER_USER,
                RsmUser::ROLE_EXECUTIVE_DIRECTOR,
                RsmUser::ROLE_DIRECTOR,
                RsmUser::ROLE_SENIOR,
            ])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
        $notifiedIds = RsmNotification::query()->pluck('recipient_user_id')->sort()->values()->all();

        $this->assertSame($expectedIds, $notifiedIds);

        RsmNotification::query()->delete();
        $report->delete();
        $recipients->each->delete();
        $actor->delete();
    }
}
