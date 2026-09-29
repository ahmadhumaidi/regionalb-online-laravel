<?php

use App\Support\CampusMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $campuses = DB::table('partner_campuses')->get(['id', 'name', 'display_name']);
        $accounts = DB::table('rsm_social_accounts')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get(['unit_name', 'instagram_username']);

        foreach ($accounts as $account) {
            $username = ltrim(trim((string) $account->instagram_username), '@');
            if ($username === '') {
                continue;
            }

            $campus = $campuses->first(fn ($candidate): bool => CampusMatcher::matches(
                (string) $account->unit_name,
                (string) ($candidate->display_name ?: $candidate->name)
            ));
            if (! $campus) {
                continue;
            }

            DB::table('partner_campuses')
                ->where('id', $campus->id)
                ->whereNull('instagram_username')
                ->update([
                    'instagram_username' => $username,
                    'instagram_url' => 'https://www.instagram.com/'.$username.'/',
                ]);
        }
    }

    public function down(): void
    {
        // Existing social-profile values may have been edited after backfill.
    }
};
