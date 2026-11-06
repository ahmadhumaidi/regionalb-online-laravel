<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class AreaFilterTest extends TestCase
{
    public function test_director_defaults_to_all_and_can_filter_both_areas_without_changing_account(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
        ]]);
        Schema::table('partner_campuses', fn (Blueprint $table) => $table->string('wilayah')->nullable());
        foreach (['Regional A', 'Regional B'] as $i => $area) {
            RsmUser::create(['name' => 'Staff '.$area, 'username' => 'staff_'.$i, 'role' => 'staff', 'area' => $area, 'regional' => $i === 0 ? 'Regional 1' : 'Regional 4', 'password_hash' => 'x', 'jabatan' => 'Staff', 'is_active' => true]);
        }
        Route::middleware(['web', 'auth', 'effective_role'])->get('/_test/area', function () {
            $user = auth()->user();
            return response()->json(['area' => $user->viewingArea(), 'references' => ReferenceOptionsService::build($user->viewingArea(), $user)]);
        });
        foreach (['director', 'executive_director'] as $role) {
            $user = RsmUser::create(['name' => 'Director', 'username' => $role, 'role' => $role, 'area' => 'Regional B', 'password_hash' => 'x', 'jabatan' => 'Director', 'is_active' => true]);
            $this->actingAs($user)->get('/_test/area')->assertOk()->assertJsonPath('area', 'all')->assertJsonCount(2, 'references.staff');
            foreach (['Regional A', 'Regional B'] as $area) {
                $this->post('/area', ['area' => $area])->assertRedirect();
                $this->get('/_test/area')->assertJsonPath('area', $area)->assertJsonCount(1, 'references.staff');
            }
            $this->post('/area', ['area' => 'all'])->assertRedirect();
            $this->get('/_test/area')->assertJsonPath('area', 'all')->assertJsonCount(2, 'references.staff');
            $this->assertSame('Regional B', $user->fresh()->area);
        }
        $staff = RsmUser::where('username', 'staff_0')->firstOrFail();
        $this->actingAs($staff)->post('/area', ['area' => 'all'])->assertForbidden();
        $this->actingAs($staff)->get('/_test/area')->assertJsonPath('area', 'Regional A')->assertJsonCount(1, 'references.staff');
        $this->assertCount(7, AreaRegionals::forArea('all'));
    }
}
