<?php

namespace Tests\Feature;

use App\Models\RsmCrmLead;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Standalone CRM lead system (rsm_crm_leads — no FK to rsm_reports).
 * Covers CrmLeadScope's staff/koordinator/senior visibility tiers and the
 * CrmLeadController CRUD/status-update endpoints, including CTWA as a
 * lead source.
 */
class CrmLeadTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_17_090000_create_rsm_crm_leads_table.php',
            'database/migrations/2026_08_17_100000_add_whatsapp_fields_to_rsm_crm_leads_table.php',
        ]]);
    }

    private function makeUser(int $id, string $role, ?string $regional = 'Regional 6'): RsmUser
    {
        return RsmUser::create([
            'id' => $id, 'name' => 'CRM Test '.$id, 'username' => 'crm_test_'.$id,
            'password_hash' => 'x', 'role' => $role, 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => $regional, 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    public function test_staff_can_create_a_ctwa_lead_and_sees_it_in_their_own_list(): void
    {
        $this->migrate();
        $staff = $this->makeUser(960001, 'staff');

        $response = $this->actingAs($staff)->post(route('crm.store'), [
            'lead_name' => 'Budi Santoso', 'whatsapp' => '081234567890', 'source' => 'CTWA',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rsm_crm_leads', [
            'lead_name' => 'Budi Santoso', 'source' => 'CTWA', 'owner_user_id' => $staff->id, 'status' => 'Baru',
        ]);

        $index = $this->actingAs($staff)->get(route('crm'));
        $index->assertOk();
        $index->assertSee('Budi Santoso');
        $index->assertSee('1', false);

        RsmCrmLead::query()->delete();
        $staff->delete();
    }

    public function test_staff_cannot_see_another_staffs_lead(): void
    {
        $this->migrate();
        $ownerStaff = $this->makeUser(960002, 'staff');
        $otherStaff = $this->makeUser(960003, 'staff');
        RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => $ownerStaff->id,
            'created_by_name' => $ownerStaff->name, 'lead_name' => 'Owned Lead', 'source' => 'Organic', 'status' => 'Baru',
        ]);

        $response = $this->actingAs($otherStaff)->get(route('crm'));

        $response->assertOk();
        $response->assertDontSee('Owned Lead');

        RsmCrmLead::query()->delete();
        $ownerStaff->delete();
        $otherStaff->delete();
    }

    public function test_koordinator_sees_all_leads_in_their_wilayah(): void
    {
        $this->migrate();
        $koordinator = $this->makeUser(960004, 'koordinator', 'Regional 6');
        $staffInWilayah = $this->makeUser(960005, 'staff', 'Regional 6');
        $staffOutside = $this->makeUser(960006, 'staff', 'Regional 7');
        RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => $staffInWilayah->id,
            'created_by_name' => $staffInWilayah->name, 'lead_name' => 'Inside Wilayah', 'source' => 'CTWA', 'status' => 'Baru',
        ]);
        RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 7', 'owner_user_id' => $staffOutside->id,
            'created_by_name' => $staffOutside->name, 'lead_name' => 'Outside Wilayah', 'source' => 'CTWA', 'status' => 'Baru',
        ]);

        $response = $this->actingAs($koordinator)->get(route('crm'));

        $response->assertOk();
        $response->assertSee('Inside Wilayah');
        $response->assertDontSee('Outside Wilayah');

        RsmCrmLead::query()->delete();
        $koordinator->delete();
        $staffInWilayah->delete();
        $staffOutside->delete();
    }

    public function test_senior_sees_leads_from_every_wilayah(): void
    {
        $this->migrate();
        $senior = $this->makeUser(960007, 'senior', null);
        $staffA = $this->makeUser(960008, 'staff', 'Regional 6');
        $staffB = $this->makeUser(960009, 'staff', 'Regional 7');
        RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => $staffA->id,
            'created_by_name' => $staffA->name, 'lead_name' => 'Lead A', 'source' => 'CTWA', 'status' => 'Baru',
        ]);
        RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 7', 'owner_user_id' => $staffB->id,
            'created_by_name' => $staffB->name, 'lead_name' => 'Lead B', 'source' => 'Organic', 'status' => 'Closing',
        ]);

        $response = $this->actingAs($senior)->get(route('crm'));

        $response->assertOk();
        $response->assertSee('Lead A');
        $response->assertSee('Lead B');

        RsmCrmLead::query()->delete();
        $senior->delete();
        $staffA->delete();
        $staffB->delete();
    }

    public function test_staff_cannot_edit_or_delete_another_staffs_lead(): void
    {
        $this->migrate();
        $ownerStaff = $this->makeUser(960010, 'staff');
        $otherStaff = $this->makeUser(960011, 'staff');
        $lead = RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => $ownerStaff->id,
            'created_by_name' => $ownerStaff->name, 'lead_name' => 'Protected Lead', 'source' => 'CTWA', 'status' => 'Baru',
        ]);

        $updateResponse = $this->actingAs($otherStaff)->patch(route('crm.update', $lead), [
            'lead_name' => 'Hacked Name', 'source' => 'CTWA',
        ]);
        $deleteResponse = $this->actingAs($otherStaff)->delete(route('crm.destroy', $lead));

        $updateResponse->assertForbidden();
        $deleteResponse->assertForbidden();
        $this->assertDatabaseHas('rsm_crm_leads', ['id' => $lead->id, 'lead_name' => 'Protected Lead']);

        RsmCrmLead::query()->delete();
        $ownerStaff->delete();
        $otherStaff->delete();
    }

    public function test_koordinator_cannot_manage_a_lead_outside_their_wilayah(): void
    {
        $this->migrate();
        $koordinator = $this->makeUser(960012, 'koordinator', 'Regional 6');
        $staffOutside = $this->makeUser(960013, 'staff', 'Regional 7');
        $lead = RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 7', 'owner_user_id' => $staffOutside->id,
            'created_by_name' => $staffOutside->name, 'lead_name' => 'Outside Lead', 'source' => 'CTWA', 'status' => 'Baru',
        ]);

        $response = $this->actingAs($koordinator)->delete(route('crm.destroy', $lead));

        $response->assertForbidden();
        $this->assertDatabaseHas('rsm_crm_leads', ['id' => $lead->id]);

        RsmCrmLead::query()->delete();
        $koordinator->delete();
        $staffOutside->delete();
    }

    public function test_owner_can_update_lead_status_and_follow_up_result(): void
    {
        $this->migrate();
        $staff = $this->makeUser(960014, 'staff');
        $lead = RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => $staff->id,
            'created_by_name' => $staff->name, 'lead_name' => 'Status Lead', 'source' => 'CTWA', 'status' => 'Baru',
        ]);

        $response = $this->actingAs($staff)->patch(route('crm.status', $lead), [
            'status' => 'Closing', 'follow_up_result' => 'Sudah daftar ulang.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rsm_crm_leads', [
            'id' => $lead->id, 'status' => 'Closing', 'follow_up_result' => 'Sudah daftar ulang.',
        ]);

        RsmCrmLead::query()->delete();
        $staff->delete();
    }

    public function test_senior_can_assign_wilayah_and_owner_to_an_unassigned_lead(): void
    {
        $this->migrate();
        $senior = $this->makeUser(960015, 'senior', null);
        $staff = $this->makeUser(960016, 'staff', 'Regional 6');
        $koordinator = $this->makeUser(960017, 'koordinator', 'Regional 6');
        $lead = RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => null, 'owner_user_id' => null,
            'lead_name' => 'Unassigned CTWA Lead', 'source' => 'CTWA', 'status' => 'Baru',
            'wa_message_id' => 'wamid.ASSIGN1',
        ]);

        // Before assignment, neither koordinator nor staff can see it.
        $this->actingAs($koordinator)->get(route('crm'))->assertDontSee('Unassigned CTWA Lead');
        $this->actingAs($staff)->get(route('crm'))->assertDontSee('Unassigned CTWA Lead');

        $response = $this->actingAs($senior)->patch(route('crm.update', $lead), [
            'lead_name' => 'Unassigned CTWA Lead', 'source' => 'CTWA',
            'wilayah' => 'Regional 6', 'owner_user_id' => $staff->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rsm_crm_leads', ['id' => $lead->id, 'wilayah' => 'Regional 6', 'owner_user_id' => $staff->id]);

        // After assignment, the scope picks it up automatically for both tiers.
        $this->actingAs($koordinator)->get(route('crm'))->assertSee('Unassigned CTWA Lead');
        $this->actingAs($staff)->get(route('crm'))->assertSee('Unassigned CTWA Lead');

        RsmCrmLead::query()->delete();
        $senior->delete();
        $staff->delete();
        $koordinator->delete();
    }

    public function test_koordinator_cannot_reassign_wilayah_or_owner_via_update(): void
    {
        $this->migrate();
        $koordinator = $this->makeUser(960018, 'koordinator', 'Regional 6');
        $otherStaff = $this->makeUser(960019, 'staff', 'Regional 7');
        $lead = RsmCrmLead::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'owner_user_id' => null,
            'created_by_name' => $koordinator->name, 'lead_name' => 'Koordinator Lead', 'source' => 'CTWA', 'status' => 'Baru',
        ]);

        $response = $this->actingAs($koordinator)->patch(route('crm.update', $lead), [
            'lead_name' => 'Koordinator Lead Renamed', 'source' => 'CTWA',
            'wilayah' => 'Regional 7', 'owner_user_id' => $otherStaff->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rsm_crm_leads', [
            'id' => $lead->id, 'lead_name' => 'Koordinator Lead Renamed',
            'wilayah' => 'Regional 6', 'owner_user_id' => null,
        ]);

        RsmCrmLead::query()->delete();
        $koordinator->delete();
        $otherStaff->delete();
    }
}
