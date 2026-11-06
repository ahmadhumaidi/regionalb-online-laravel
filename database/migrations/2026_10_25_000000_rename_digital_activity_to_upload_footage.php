<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameActivity('Aktivitas digital', 'Upload Footage');
    }

    public function down(): void
    {
        $this->renameActivity('Upload Footage', 'Aktivitas digital');
    }

    private function renameActivity(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            foreach (['activity_kind', 'category', 'title'] as $column) {
                DB::table('rsm_reports')
                    ->whereIn('report_type', ['marketing', 'other'])
                    ->whereRaw('LOWER(TRIM('.$column.')) = ?', [mb_strtolower($from)])
                    ->update([$column => $to]);
            }
        });
    }
};
