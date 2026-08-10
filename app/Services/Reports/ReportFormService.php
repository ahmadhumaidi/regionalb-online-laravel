<?php

namespace App\Services\Reports;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\AdBudget\AdBudgetPeriods;
use App\Services\Dashboard\ReportScope;
use App\Services\NotificationService;
use App\Support\RsmRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportFormService
{
    private const SENIOR_ROLES = ['super_user', 'executive_director', 'director', 'senior'];

    public static function config(string $type): array
    {
        return match ($type) {
            RsmReport::TYPE_MARKETING => [
                'title' => 'Kegiatan Marketing',
                'label' => 'kegiatan',
                'options' => ['Follow up leads', 'Kunjungan sekolah', 'Kunjungan instansi', 'Sebar brosur', 'Pasang spanduk', 'Event kampus', 'Presentasi PMB', 'Aktivitas digital', 'Lainnya'],
                'statuses' => ['Draft', 'Dikirim'],
                'fields' => ['report_date', 'wilayah', 'unit_name', 'staff_name', 'activity_kind', 'title', 'location_name', 'target_text', 'result_text', 'leads_count', 'notes', 'attachment_path'],
            ],
            RsmReport::TYPE_OTHER => [
                'title' => 'Aktivitas Lain',
                'label' => 'aktivitas',
                'options' => ['Meeting internal', 'Briefing', 'Training', 'Koordinasi kampus', 'Koordinasi mitra', 'Pelayanan calon mahasiswa', 'Administrasi PMB', 'Follow up pembayaran', 'Lainnya'],
                'statuses' => ['Draft', 'Dikirim'],
                'fields' => ['report_date', 'wilayah', 'unit_name', 'staff_name', 'category', 'title', 'result_text', 'obstacle_text', 'follow_up_text', 'attachment_path'],
            ],
            RsmReport::TYPE_ADS => [
                'title' => 'Anggaran & Laporan Iklan',
                'label' => 'anggaran',
                'options' => ['Meta Ads', 'IG', 'FB', 'Google Ads', 'TikTok Ads', 'WhatsApp Blast', 'Marketplace/Portal', 'Lainnya'],
                'statuses' => ['Pengajuan'],
                'fields' => ['report_date', 'ad_period', 'wilayah', 'unit_name', 'platform', 'campaign_name', 'ad_goal', 'budget_requested', 'attachment_path'],
            ],
            default => throw new \InvalidArgumentException('Jenis laporan tidak didukung.'),
        };
    }

    public static function canCreate(string $type, RsmUser $user): bool
    {
        return $type !== RsmReport::TYPE_ADS
            || in_array($user->role, [RsmUser::ROLE_KOORDINATOR, RsmUser::ROLE_SUPER_USER], true);
    }

    public static function canEdit(RsmReport $report, RsmUser $user): bool
    {
        if ($report->area !== ($user->area ?: 'Regional B')) {
            return false;
        }
        if (in_array($user->role, self::SENIOR_ROLES, true)) {
            return true;
        }
        if ($report->report_type === RsmReport::TYPE_ADS && $user->role === RsmUser::ROLE_KOORDINATOR) {
            return $report->wilayah === $user->regional;
        }
        if ($report->report_type === RsmReport::TYPE_ADS && $user->role === RsmUser::ROLE_STAFF) {
            return in_array(mb_strtolower((string) $report->status), [
                'diverifikasi', 'disetujui', 'transfer-/-invoice', 'transfer / invoice', 'berjalan', 'dilaporkan-unit', 'dilaporkan unit', 'revisi',
            ], true);
        }

        return $user->role === RsmUser::ROLE_STAFF
            && in_array(mb_strtolower((string) $report->status), ['draft', 'revisi'], true)
            && $report->staff_name === $user->name;
    }

    public static function canDelete(RsmReport $report, RsmUser $user): bool
    {
        return self::canEdit($report, $user)
            && ($report->report_type === RsmReport::TYPE_ADS || in_array(mb_strtolower((string) $report->status), ['draft', 'revisi'], true) || in_array($user->role, self::SENIOR_ROLES, true));
    }

    public static function visible(RsmReport $report, RsmUser $user): bool
    {
        if ($report->area !== ($user->area ?: 'Regional B')) {
            return false;
        }

        return ReportScope::apply(RsmReport::query()->whereKey($report->id), $user)->exists();
    }

    /**
     * Field sets shown on the ads edit form per role, mirroring
     * report_fields_for_type('ads', $role) (dashboard.php:1986-2001).
     * Keyed by internal column name, not the Indonesian label (labels
     * belong to the view).
     */
    public static function adsEditFieldsForRole(string $role): array
    {
        return match ($role) {
            RsmUser::ROLE_STAFF => ['campaign_name', 'ad_goal', 'realization_amount', 'cpl', 'campaign_link', 'attachment_path', 'ad_leads_file', 'insight_attachment_path', 'notes'],
            RsmUser::ROLE_KOORDINATOR => ['report_date', 'ad_period', 'wilayah', 'unit_name', 'platform', 'budget_requested', 'attachment_path', 'ad_leads_file', 'insight_attachment_path', 'notes'],
            default => in_array($role, self::SENIOR_ROLES, true)
                ? ['report_date', 'ad_period', 'wilayah', 'unit_name', 'platform', 'budget_requested', 'budget_approved', 'attachment_path', 'ad_leads_file', 'notes']
                : [],
        };
    }

    public static function create(string $type, array $data, ?UploadedFile $attachment, RsmUser $user): RsmReport
    {
        if (! self::canCreate($type, $user)) {
            abort(403);
        }

        $report = DB::transaction(function () use ($type, $data, $attachment, $user) {
            $normalized = self::normalize($type, $data, $user);
            if ($type !== RsmReport::TYPE_ADS || $user->role !== RsmUser::ROLE_SUPER_USER) {
                self::validateBudget($type, $normalized);
            }
            $report = RsmReport::create($normalized);
            self::storeAttachment($report, $attachment);
            self::log($report, $user, 'create_'.$type, null, $report->status);
            self::notifyIfKendalaSubmitted($report, null);

            return $report->fresh();
        });

        return $report;
    }

    public static function update(RsmReport $report, array $data, ?UploadedFile $attachment, RsmUser $user, ?UploadedFile $insightAttachment = null): RsmReport
    {
        abort_unless(self::canEdit($report, $user), 403);
        abort_unless(self::visible($report, $user), 404);

        return DB::transaction(function () use ($report, $data, $attachment, $user, $insightAttachment) {
            $oldStatus = $report->status;
            $normalized = $report->report_type === RsmReport::TYPE_ADS
                ? self::normalizeAds($data, $user, $report)
                : self::normalize($report->report_type, $data, $user, $report);
            if ($report->report_type !== RsmReport::TYPE_ADS || (float) $normalized['budget_requested'] !== (float) $report->budget_requested) {
                self::validateBudget($report->report_type, $normalized, $report);
            }
            $report->fill($normalized);
            $report->save();
            self::storeAttachment($report, $attachment);
            self::storeInsightAttachment($report, $insightAttachment);
            self::log($report, $user, 'edit', $oldStatus, $report->status);
            self::notifyIfKendalaSubmitted($report, $oldStatus);

            return $report->fresh();
        });
    }

    public static function delete(RsmReport $report, RsmUser $user): void
    {
        abort_unless(self::canDelete($report, $user), 403);
        abort_unless(self::visible($report, $user), 404);

        DB::transaction(function () use ($report, $user) {
            self::log($report, $user, 'delete', $report->status, null);
            $report->delete();
        });
    }

    private static function normalize(string $type, array $data, RsmUser $user, ?RsmReport $existing = null): array
    {
        $staff = $user->role === RsmUser::ROLE_STAFF ? $user->name : trim((string) ($data['staff_name'] ?? ''));
        $wilayah = $user->role === RsmUser::ROLE_STAFF ? (string) $user->regional : trim((string) ($data['wilayah'] ?? ''));
        $unit = $user->role === RsmUser::ROLE_STAFF ? (string) $user->campus_name : trim((string) ($data['unit_name'] ?? ''));
        $staffRow = RsmUser::query()->where('role', RsmUser::ROLE_STAFF)->where('name', $staff)->when($wilayah !== '', fn ($q) => $q->where('regional', $wilayah))->first();
        $campus = DB::table('partner_campuses')->where('display_name', $unit)->orWhere('name', $unit)->first();

        if ($user->role === RsmUser::ROLE_KOORDINATOR && $campus && $campus->wilayah && $campus->wilayah !== $user->regional) {
            throw ValidationException::withMessages(['unit_name' => 'Kampus tersebut bukan bagian dari wilayah Anda.']);
        }

        $status = $type === RsmReport::TYPE_ADS ? 'Pengajuan' : ((string) ($data['status'] ?? 'Draft'));
        if ($existing && $user->role !== RsmUser::ROLE_STAFF) {
            $status = in_array($status, ['Draft', 'Revisi', 'Dikirim'], true) ? $status : $existing->status;
        }

        // "Aktivitas Lain" dengan Kendala terisi otomatis masuk antrian
        // tindak lanjut korwil/Senior Manager begitu disimpan - staff tidak
        // perlu (dan tidak bisa) memilih status manual untuk kasus ini.
        // Guard ke Draft/Dikirim saja supaya laporan yang sudah masuk alur
        // tindak-lanjut (Ditindak Lanjuti/Selesai) tidak ke-reset kalau
        // diedit ulang oleh senior-tier. Lihat ObstacleFollowUpController
        // untuk alur setelah "Dikirim".
        if ($type === RsmReport::TYPE_OTHER) {
            $obstacleText = trim((string) ($data['obstacle_text'] ?? $existing?->obstacle_text ?? ''));
            $notYetInFollowUpFlow = $existing === null || in_array($existing->status, ['Draft', 'Dikirim'], true);
            if ($obstacleText !== '' && $notYetInFollowUpFlow) {
                $status = 'Dikirim';
            }
        }

        // Laporan pengeluaran Senior Manager: dibuat dan langsung disetujui oleh
        // Super User sendiri, tidak terikat wilayah/kampus koordinator maupun
        // plafon anggaran regional (lihat create(), skip validateBudget()).
        $isSeniorExpense = $type === RsmReport::TYPE_ADS && $user->role === RsmUser::ROLE_SUPER_USER;
        if ($isSeniorExpense) {
            $wilayah = 'Regional B';
            $unit = 'Regional B';
            $status = 'Disetujui';
        }

        return [
            'area' => $user->area ?: 'Regional B',
            'report_type' => $type,
            'report_date' => $data['report_date'] ?? now()->toDateString(),
            'user_id' => $staffRow?->id,
            'partner_campus_id' => $campus?->id ?? $staffRow?->partner_campus_id,
            'wilayah' => $wilayah ?: 'Belum diisi',
            'unit_name' => $unit ?: 'Belum diisi',
            'staff_name' => $staff ?: $user->name,
            'created_by_name' => $existing?->created_by_name ?: $user->name,
            'created_by_role' => $existing?->created_by_role ?: $user->role,
            'status' => $status,
            'title' => $type === RsmReport::TYPE_ADS ? (trim((string) ($data['campaign_name'] ?? '')) ?: '-') : (trim((string) ($data['title'] ?? '')) ?: 'Laporan RSM'),
            'activity_kind' => $data['activity_kind'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'target_text' => $data['target_text'] ?? null,
            'result_text' => $data['result_text'] ?? null,
            'leads_count' => (int) ($data['leads_count'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'platform' => $data['platform'] ?? null,
            'ad_period' => $data['ad_period'] ?? AdBudgetPeriods::default(),
            'campaign_name' => $data['campaign_name'] ?? null,
            'ad_goal' => $data['ad_goal'] ?? null,
            'budget_requested' => (float) ($data['budget_requested'] ?? 0),
            'budget_approved' => $isSeniorExpense ? (float) ($data['budget_requested'] ?? 0) : ($existing?->budget_approved ?? 0),
            'realization_amount' => $existing?->realization_amount ?? 0,
            'cpl' => $existing?->cpl ?? 0,
            'campaign_link' => $data['campaign_link'] ?? null,
            'category' => $data['category'] ?? null,
            'obstacle_text' => $data['obstacle_text'] ?? null,
            'follow_up_text' => $data['follow_up_text'] ?? null,
        ];
    }

    /**
     * Ports the ads branch of rsm_update_report() (rsm_db.php:2489-2565).
     * Unlike normalize(), every column defaults to the *existing* report's
     * value when not present in $data — matching legacy's per-field
     * $postedValue()/$postedNumber() fallback — since the edit form only
     * posts the subset of fields report_fields_for_type() shows for the
     * current role (adsEditFieldsForRole()).
     */
    private static function normalizeAds(array $data, RsmUser $user, RsmReport $existing): array
    {
        $posted = static fn (string $key, mixed $fallback) => array_key_exists($key, $data) ? $data[$key] : $fallback;

        $wilayah = trim((string) $posted('wilayah', $existing->wilayah));
        $unit = trim((string) $posted('unit_name', $existing->unit_name));
        $staff = trim((string) $posted('staff_name', $existing->staff_name));
        $staffRow = RsmUser::query()->where('role', RsmUser::ROLE_STAFF)->where('name', $staff)->when($wilayah !== '', fn ($q) => $q->where('regional', $wilayah))->first();
        $campus = DB::table('partner_campuses')->where('display_name', $unit)->orWhere('name', $unit)->first();

        if ($user->role === RsmUser::ROLE_KOORDINATOR && $campus && $campus->wilayah && $campus->wilayah !== $user->regional) {
            throw ValidationException::withMessages(['unit_name' => 'Kampus tersebut bukan bagian dari wilayah Anda.']);
        }

        // CPL is derived, not staff-entered: realisasi / jumlah data hasil
        // iklan yang sudah diupload. leads_count reflects whatever was
        // imported in an *earlier* request; if a new ad_leads_file is also
        // uploaded in this same submission, AdLeadImportService::refreshCounts()
        // recomputes both leads_count and cpl again afterwards with the
        // fresh count, so this is just the best estimate available now.
        $newRealizationAmount = (float) $posted('realization_amount', $existing->realization_amount);
        $existingLeadsCount = (int) $existing->leads_count;
        $computedCpl = $existingLeadsCount > 0 ? round($newRealizationAmount / $existingLeadsCount, 2) : 0.0;

        $base = [
            'area' => $user->area ?: 'Regional B',
            'report_type' => RsmReport::TYPE_ADS,
            'report_date' => $posted('report_date', optional($existing->report_date)->toDateString()),
            'user_id' => $staffRow?->id ?? $existing->user_id,
            'partner_campus_id' => $campus?->id ?? $staffRow?->partner_campus_id ?? $existing->partner_campus_id,
            'wilayah' => $wilayah ?: 'Belum diisi',
            'unit_name' => $unit ?: 'Belum diisi',
            'staff_name' => $staff ?: $existing->staff_name,
            'created_by_name' => $existing->created_by_name ?: $user->name,
            'created_by_role' => $existing->created_by_role ?: $user->role,
            'status' => $existing->status,
            'title' => trim((string) $posted('campaign_name', $existing->campaign_name)) ?: '-',
            'platform' => $posted('platform', $existing->platform),
            'ad_period' => trim((string) $posted('ad_period', $existing->ad_period)) ?: AdBudgetPeriods::default(),
            'campaign_name' => $posted('campaign_name', $existing->campaign_name),
            'ad_goal' => $posted('ad_goal', $existing->ad_goal),
            'budget_requested' => (float) $posted('budget_requested', $existing->budget_requested),
            'budget_approved' => (float) $posted('budget_approved', $existing->budget_approved),
            'realization_amount' => $newRealizationAmount,
            'leads_count' => $existingLeadsCount,
            'closing_count' => (int) $existing->closing_count,
            'cpl' => $computedCpl,
            'campaign_link' => $posted('campaign_link', $existing->campaign_link),
            'notes' => $posted('notes', $existing->notes),
        ];

        return match (true) {
            $user->role === RsmUser::ROLE_STAFF => array_merge($base, [
                'report_date' => optional($existing->report_date)->toDateString(),
                'user_id' => $existing->user_id,
                'partner_campus_id' => $existing->partner_campus_id,
                'wilayah' => $existing->wilayah,
                'unit_name' => $existing->unit_name,
                'staff_name' => $user->name,
                'platform' => $existing->platform,
                'ad_period' => $existing->ad_period,
                'budget_requested' => (float) $existing->budget_requested,
                'budget_approved' => (float) $existing->budget_approved,
                'status' => 'Dilaporkan Unit',
            ]),
            $user->role === RsmUser::ROLE_KOORDINATOR => array_merge($base, [
                'budget_approved' => (float) $existing->budget_approved,
                'realization_amount' => (float) $existing->realization_amount,
                'cpl' => (float) $existing->cpl,
            ]),
            in_array($user->role, self::SENIOR_ROLES, true) => array_merge($base, [
                'realization_amount' => (float) $existing->realization_amount,
                'cpl' => (float) $existing->cpl,
            ]),
            default => $base,
        };
    }

    private static function validateBudget(string $type, array $data, ?RsmReport $existing = null): void
    {
        if ($type !== RsmReport::TYPE_ADS) {
            return;
        }
        $requested = (float) $data['budget_requested'];
        $limit = DB::table('rsm_ad_budget_limits')->where(['area' => $data['area'], 'ad_period' => $data['ad_period'], 'wilayah' => $data['wilayah']])->value('budget_limit');
        if ((float) $limit <= 0) {
            throw ValidationException::withMessages(['budget_requested' => 'Plafon anggaran untuk wilayah dan periode ini belum ditetapkan.']);
        }
        $used = DB::table('rsm_reports')->where('area', $data['area'])->where('report_type', RsmReport::TYPE_ADS)->where('ad_period', $data['ad_period'])->where('wilayah', $data['wilayah'])->whereRaw('LOWER(status) <> ?', ['ditolak'])->when($existing, fn ($q) => $q->where('id', '<>', $existing->id))->sum('budget_requested');
        if ($requested <= 0 || $requested > ((float) $limit - (float) $used + 0.0001)) {
            throw ValidationException::withMessages(['budget_requested' => 'Anggaran melebihi sisa plafon atau tidak valid.']);
        }
    }

    public static function storeAttachment(RsmReport $report, ?UploadedFile $attachment): void
    {
        if (! $attachment) {
            return;
        }
        $path = $attachment->store('reports', 'public');
        $report->attachment_path = $path;
        $report->save();
    }

    /** "Bukti insight/pengeluaran iklan" — separate from storeAttachment()'s transfer/invoice proof. */
    public static function storeInsightAttachment(RsmReport $report, ?UploadedFile $insightAttachment): void
    {
        if (! $insightAttachment) {
            return;
        }
        $path = $insightAttachment->store('reports', 'public');
        $report->insight_attachment_path = $path;
        $report->save();
    }

    private static function log(RsmReport $report, RsmUser $user, string $action, ?string $oldStatus, ?string $newStatus): void
    {
        RsmActivityLog::create([
            'report_id' => $report->id,
            'area' => $report->area,
            'actor_user_id' => $user->id,
            'actor_role' => $user->role,
            'actor_actual_role' => $user->role,
            'actor_view_role' => $user->role,
            'actor_name' => $user->name,
            'action_name' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'ip_address' => request()->ip(),
        ]);
    }

    /** Fires once per genuine transition into "Dikirim" with a Kendala filled in - not on every re-save while already Dikirim. */
    private static function notifyIfKendalaSubmitted(RsmReport $report, ?string $oldStatus): void
    {
        if ($report->report_type !== RsmReport::TYPE_OTHER || $report->status !== 'Dikirim') {
            return;
        }
        if ($oldStatus === 'Dikirim') {
            return;
        }
        if (trim((string) $report->obstacle_text) === '') {
            return;
        }

        NotificationService::notifyKendala($report);
    }
}
