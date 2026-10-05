<?php

namespace Tests\Feature;

use App\Jobs\SyncAllSources;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualSourceSyncTest extends TestCase
{
    public function test_staff_can_start_all_source_syncs(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
        ]]);
        Queue::fake();

        $staff = RsmUser::create([
            'name' => 'Staff Sinkron', 'username' => 'staff_sync', 'password_hash' => 'x',
            'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff Unit', 'area' => 'Regional B',
            'regional' => 'Regional 4', 'campus_name' => 'Kampus Uji', 'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->from(route('dashboard'))
            ->post(route('sources.sync'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('notice', 'Sinkronisasi semua sumber dimulai di background: Personalia, Collab, Absensi, dan BDC.');
        Queue::assertPushed(SyncAllSources::class, 1);
    }

    public function test_guest_cannot_trigger_manual_source_sync(): void
    {
        $this->post(route('sources.sync'))->assertRedirect(route('login'));
    }
}
