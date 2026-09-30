<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_campuses') || ! Schema::hasColumn('partner_campuses', 'wilayah')) {
            return;
        }

        // Complete the original Collab-based mapping from active Regional A
        // account assignments so Regional 1-3 campuses can be scoped reliably.
        DB::table('rsm_users')
            ->where('area', 'Regional A')
            ->whereIn('regional', ['Regional 1', 'Regional 2', 'Regional 3'])
            ->whereNotNull('campus_name')
            ->where('campus_name', '<>', '')
            ->select(['campus_name', 'regional'])
            ->distinct()
            ->orderBy('campus_name')
            ->each(function (object $user): void {
                DB::table('partner_campuses')
                    ->whereNull('wilayah')
                    ->where(function ($query) use ($user): void {
                        $query->where('name', $user->campus_name)
                            ->orWhere('display_name', $user->campus_name);
                    })
                    ->update(['wilayah' => $user->regional]);
            });

        if (! $this->hasIndex('partner_campuses', 'idx_partner_campuses_wilayah')) {
            Schema::table('partner_campuses', function (Blueprint $table): void {
                $table->index('wilayah', 'idx_partner_campuses_wilayah');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_campuses') && $this->hasIndex('partner_campuses', 'idx_partner_campuses_wilayah')) {
            Schema::table('partner_campuses', function (Blueprint $table): void {
                $table->dropIndex('idx_partner_campuses_wilayah');
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === $name,
        );
    }
};
