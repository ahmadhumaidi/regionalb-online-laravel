<?php

namespace App\Services\Reports;

use App\Imports\AdLeadsImport;
use App\Models\RsmActivityLog;
use App\Models\RsmAdLead;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\XpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Ports the "Data Hasil Iklan" (rsm_ad_leads) upload/bulk-update pipeline:
 * rsm_import_ad_leads()/rsm_map_ad_lead_rows()/rsm_normalize_header()/
 * rsm_normalize_closing_status()/rsm_refresh_report_ad_counts()
 * (rsm_db.php:3569-3707) and rsm_update_ad_leads() (rsm_db.php:3380-3453).
 * Legacy hand-rolls XLSX/XML/HTML/delimited parsing because it has no
 * library; this uses maatwebsite/excel for the raw cell extraction instead
 * and ports the header-alias mapping table verbatim for output fidelity.
 */
class AdLeadImportService
{
    private const CLOSING_STATUSES = ['closing', 'daftar', 'herregistrasi'];

    /** Header alias table, port of rsm_map_ad_lead_rows()'s $map (rsm_db.php:3891-3919). */
    private const HEADER_MAP = [
        'nama_lead' => 'lead_name', 'full_name' => 'lead_name', 'nama_lengkap' => 'lead_name',
        'no_hp_wa' => 'whatsapp', 'no_wa' => 'whatsapp', 'nomor_wa' => 'whatsapp', 'phone_number' => 'whatsapp', 'phone' => 'whatsapp', 'telepon' => 'whatsapp',
        'email' => 'email', 'alamat_email' => 'email', 'email_address' => 'email',
        'kampus' => 'campus_name', 'conditional_question_1' => 'campus_name', 'nama_kampus' => 'campus_name',
        'jurusan' => 'major_name', 'conditional_question_2' => 'major_name', 'program_studi' => 'major_name',
        'kota_asal' => 'origin_city', 'city' => 'origin_city', 'kota' => 'origin_city',
        'hasil_follow_up' => 'follow_up_result', 'follow_up' => 'follow_up_result',
        'status_progress' => 'progress_status', 'lead_status' => 'progress_status', 'status' => 'progress_status',
        'status_closing' => 'closing_status', 'closing_status' => 'closing_status', 'status_daftar' => 'closing_status',
        'update_closing' => 'closing_update',
        'catatan_kendala_progress' => 'notes', 'notes' => 'notes', 'catatan' => 'notes',
    ];

    private const NEGATIVE_CLOSING_PATTERNS = [
        'belum closing', 'tidak closing', 'gagal closing', 'batal closing', 'cancel closing',
        'non closing', 'no closing', 'not closing', 'belum daftar', 'tidak daftar',
    ];

    /** Upload/import a "Data Hasil Iklan" file, mirroring rsm_import_ad_leads(). */
    public static function import(RsmReport $report, UploadedFile $file, bool $replaceExisting): int
    {
        $sheets = Excel::toArray(new AdLeadsImport, $file);
        $rows = $sheets[0] ?? [];
        $mapped = self::mapAdLeadRows($rows);

        if ($mapped === []) {
            return 0;
        }

        if ($replaceExisting) {
            $report->adLeads()->delete();
        }

        $count = 0;
        foreach ($mapped as $row) {
            RsmAdLead::create([
                'report_id' => $report->id,
                'lead_name' => self::nullable($row['lead_name'] ?? ''),
                'whatsapp' => self::nullable($row['whatsapp'] ?? ''),
                'email' => self::nullable($row['email'] ?? ''),
                'campus_name' => self::nullable($row['campus_name'] ?? ''),
                'major_name' => self::nullable($row['major_name'] ?? ''),
                'origin_city' => self::nullable($row['origin_city'] ?? ''),
                'follow_up_result' => self::nullable($row['follow_up_result'] ?? ''),
                'progress_status' => self::nullable($row['progress_status'] ?? ''),
                'closing_status' => self::nullable(self::normalizeClosingStatus($row['closing_status'] ?? ($row['closing_update'] ?? ''))),
                'closing_update' => self::nullable($row['closing_update'] ?? ''),
                'notes' => self::nullable($row['notes'] ?? ''),
            ]);
            $count++;
        }

        self::refreshCounts($report);

        if ($count > 0) {
            self::log($report, "Import data hasil iklan {$count} baris.", 'import_ad_leads');
            XpService::syncReportEventXp($report->fresh());
        }

        return $count;
    }

    /** Bulk-save per-lead closing_status/notes, mirroring rsm_update_ad_leads(). */
    public static function bulkUpdate(RsmReport $report, array $statuses, array $notes): int
    {
        $leadIds = array_values(array_filter(array_map('intval', array_keys($statuses)), static fn (int $id): bool => $id > 0));
        if ($leadIds === []) {
            return 0;
        }

        $existing = $report->adLeads()->whereIn('id', $leadIds)->get()->keyBy('id');
        $changes = 0;

        DB::transaction(function () use ($leadIds, $existing, $statuses, $notes, &$changes) {
            foreach ($leadIds as $id) {
                if (! isset($existing[$id])) {
                    continue;
                }
                $lead = $existing[$id];
                $newStatus = self::normalizeClosingStatus((string) ($statuses[$id] ?? ''));
                $newNotes = trim((string) ($notes[$id] ?? ''));
                $oldStatus = (string) ($lead->closing_status ?? '');
                $oldNotes = (string) ($lead->notes ?? '');
                if (mb_strtolower(trim($oldStatus)) === mb_strtolower(trim($newStatus)) && trim($oldNotes) === $newNotes) {
                    continue;
                }
                $lead->update(['closing_status' => $newStatus, 'notes' => $newNotes !== '' ? $newNotes : null]);
                $changes++;
            }
        });

        if ($changes > 0) {
            self::refreshCounts($report);
            self::log($report, "{$changes} baris data hasil iklan diperbarui.", 'update_ad_leads');
            XpService::syncReportEventXp($report->fresh());
        }

        return $changes;
    }

    /** Recompute leads_count/closing_count and derived CPM/CPL from rsm_ad_leads, port of rsm_refresh_report_ad_counts(). */
    public static function refreshCounts(RsmReport $report): void
    {
        $leadsCount = $report->adLeads()->count();
        $closingCount = $report->adLeads()->whereRaw('LOWER(closing_status) IN (?,?,?)', self::CLOSING_STATUSES)->count();
        $realizationAmount = (float) $report->realization_amount;
        $impressionsCount = (int) $report->impressions_count;

        $report->forceFill([
            'leads_count' => $leadsCount,
            'closing_count' => $closingCount,
            'cpl' => $leadsCount > 0 ? round($realizationAmount / $leadsCount, 2) : 0.0,
            'cpm' => $impressionsCount > 0 ? round(($realizationAmount / $impressionsCount) * 1000, 2) : 0.0,
        ])->save();
    }

    /** Port of rsm_map_ad_lead_rows() (rsm_db.php:3889-3941). */
    private static function mapAdLeadRows(array $rows): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map([self::class, 'normalizeHeader'], array_map('strval', $rows[0]));

        $result = [];
        foreach (array_slice($rows, 1) as $cells) {
            $item = array_fill_keys(array_values(self::HEADER_MAP), '');
            foreach ($cells as $index => $value) {
                $header = $headers[$index] ?? '';
                if (isset(self::HEADER_MAP[$header])) {
                    $item[self::HEADER_MAP[$header]] = trim((string) $value);
                }
            }
            if (implode('', $item) !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** Port of rsm_normalize_header() (rsm_db.php:3949-3954). */
    private static function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(['/', '.', '-'], ' ', $header);
        $header = preg_replace('/[^a-z0-9]+/i', '_', $header) ?? $header;

        return trim($header, '_');
    }

    /** Port of rsm_normalize_closing_status() (rsm_db.php:3660-3693). */
    private static function normalizeClosingStatus(string $status): string
    {
        $rawStatus = mb_strtolower(trim($status));
        if ($rawStatus === '') {
            return 'Belum closing';
        }

        $normalized = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $rawStatus)) ?? $rawStatus;

        foreach (self::NEGATIVE_CLOSING_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return str_contains($normalized, 'potensi') ? 'Potensi closing' : 'Belum closing';
            }
        }

        if (str_contains($normalized, 'potensi')) {
            return 'Potensi closing';
        }
        if (str_contains($normalized, 'her') || str_contains($normalized, 'daftar ulang')) {
            return 'Herregistrasi';
        }
        if (str_contains($normalized, 'registrasi') || str_contains($normalized, 'daftar') || str_contains($normalized, 'closing') || str_contains($normalized, 'close')) {
            return 'Closing';
        }

        return ucfirst($normalized);
    }

    /** Port of rsm_nullable() (rsm_db.php:3704-3707). */
    private static function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function log(RsmReport $report, string $note, string $action): void
    {
        /** @var RsmUser|null $user */
        $user = auth()->user();

        RsmActivityLog::create([
            'report_id' => $report->id,
            'area' => $report->area,
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role,
            'actor_actual_role' => $user?->role,
            'actor_view_role' => $user?->role,
            'actor_name' => $user?->name,
            'action_name' => $action,
            'old_status' => null,
            'new_status' => null,
            'note' => $note,
            'ip_address' => request()->ip(),
        ]);
    }
}
