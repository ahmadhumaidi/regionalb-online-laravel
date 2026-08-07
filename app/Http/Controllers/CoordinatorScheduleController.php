<?php

namespace App\Http\Controllers;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmUser;
use App\Services\CoordinatorLiburService;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use App\Support\RsmRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Ports render_coordinator_schedule_page() + rsm_coordinator_schedules()/
 * rsm_save/update/report/delete_coordinator_schedule()/
 * rsm_generate_coordinator_schedule_month()/rsm_coordinator_checklist_*()/
 * rsm_coordinator_schedule_whatsapp_text() (dashboard.php:1662-1850,
 * rsm_db.php:2970-3480 — LIVE production copy; the git-mirrored source used
 * earlier in this session was stale and missing checklist/libur/WA-artifact
 * entirely). Edit (unit/tipe/agenda) and Laporan (status/hasil/follow up/
 * checklist/dokumentasi) are two separate legacy actions with separate
 * field sets — kept separate here as update()/report().
 */
class CoordinatorScheduleController extends Controller
{
    private const WHATSAPP_ARTIFACT_PATH = 'coordinator_whatsapp_latest.json';

    /** Port of rsm_coordinator_visit_jobdesk_items() (rsm_db.php:3172-3184). */
    private const JOBDESK_ITEMS = [
        1 => 'Cek tools marketing area kampus',
        2 => 'Cek stok tools marketing kampus',
        3 => 'Cek kondisi ruangan sekretariat',
        4 => 'Cek cara followup dan progres marketing lainnya',
        5 => 'Korwil buat konten medsos dan kolaborasikan dengan medsos kampus',
        6 => 'Cek report kasbonan',
        7 => 'Cek BDC unit',
        8 => 'Koordinasi dengan pejabat kampus',
        9 => 'Cek pencapaian dan herregistrasi per prodi',
    ];

    private array $profiles = [
        'Regional 4' => ['name' => 'Hamaruddin', 'home' => 'Makassar'],
        'Regional 5' => ['name' => 'Kundi Harto', 'home' => 'Semarang'],
        'Regional 6' => ['name' => 'M. Nor Abidin', 'home' => 'Surabaya'],
        'Regional 7' => ['name' => 'Nugroho Budi Santoso', 'home' => 'Banyuwangi'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(RsmRole::canViewJadwalKoordinator($user), 403);
        $area = $user->area ?: 'Regional B';
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $regionals = AreaRegionals::forArea($area);

        $filters = [
            'wilayah' => $request->query('wilayah'),
            'koordinator_name' => $request->query('koordinator_name'),
            'status' => $request->query('status'),
        ];
        [$rows, $grouped, $summary] = $this->fetchSchedules($area, $month, $filters, $user);

        $summaryRegionals = ($user->role === 'koordinator' && $user->regional) ? [$user->regional] : $regionals;

        return view('coordinator-schedule.index', [
            'rows' => $rows,
            'grouped' => $grouped,
            'summary' => $summary,
            'month' => $month,
            'area' => $area,
            'regionals' => $regionals,
            'summaryRegionals' => $summaryRegionals,
            'profiles' => $this->profiles,
            'canManage' => RsmRole::canManageCoordinatorSchedule($user),
            'references' => ReferenceOptionsService::build($area, $user),
            'checklistSummary' => $this->checklistSummary($rows),
            'whatsappArtifact' => $this->latestWhatsappArtifact(),
            'jobdeskItems' => self::JOBDESK_ITEMS,
        ]);
    }

    /**
     * Port of rsm_coordinator_schedules() (rsm_db.php:3450-3507), including
     * the libur cross-reference against Jadwal Personalia.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array}
     */
    private function fetchSchedules(string $area, string $month, array $filters, ?RsmUser $user): array
    {
        $query = RsmCoordinatorSchedule::query()
            ->where('area', $area)
            ->whereBetween('schedule_date', [$month.'-01', date('Y-m-t', strtotime($month.'-01'))]);
        if ($user?->role === 'koordinator' && $user->regional) {
            $query->where('wilayah', $user->regional);
        }
        if (! empty($filters['wilayah'])) {
            $query->where('wilayah', $filters['wilayah']);
        }
        if (! empty($filters['koordinator_name'])) {
            $query->where('koordinator_name', $filters['koordinator_name']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $rows = $query->orderBy('schedule_date')->orderBy('wilayah')->orderBy('koordinator_name')->orderBy('unit_name')->get();

        $liburMap = CoordinatorLiburService::map();
        foreach ($rows as $row) {
            $row->libur_code = null;
            if ($liburMap['month'] === '' || $liburMap['month'] !== $month) {
                continue;
            }
            $day = (int) $row->schedule_date->format('j');
            $code = $liburMap['coordinators'][$row->koordinator_name][$day] ?? null;
            if ($code === null) {
                continue;
            }
            $row->libur_code = $code;
            $row->unit_name = $code;
            $row->agenda = '';
            $row->visit_type = '';
        }

        $regionals = AreaRegionals::forArea($area);
        $grouped = [];
        $summary = [];
        foreach ($regionals as $regional) {
            $summary[$regional] = ['total' => 0, 'Fisik' => 0, 'Zoom' => 0, 'Telepon' => 0, 'Selesai' => 0, 'Libur' => 0];
            $grouped[$regional] = collect();
        }
        foreach ($rows as $row) {
            $regional = $row->wilayah ?: '-';
            if (! isset($grouped[$regional])) {
                $grouped[$regional] = collect();
                $summary[$regional] = ['total' => 0, 'Fisik' => 0, 'Zoom' => 0, 'Telepon' => 0, 'Selesai' => 0, 'Libur' => 0];
            }
            $grouped[$regional]->push($row);
            $summary[$regional]['total']++;
            if ($row->libur_code) {
                $summary[$regional]['Libur']++;
            } else {
                $summary[$regional][$row->visit_type] = ($summary[$regional][$row->visit_type] ?? 0) + 1;
            }
            if ($row->status === 'Selesai') {
                $summary[$regional]['Selesai']++;
            }
        }

        return [$rows, $grouped, $summary];
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageCoordinatorSchedule($user), 403);
        $data = $request->validate([
            'schedule_date' => 'required|date',
            'wilayah' => 'required|string|max:80',
            'koordinator_name' => 'required|string|max:160',
            'unit_name' => 'required|string|max:200',
            'visit_type' => ['required', Rule::in(['Fisik', 'Zoom', 'Telepon'])],
            'status' => ['required', Rule::in(['Rencana', 'Dijadwalkan', 'Selesai', 'Reschedule'])],
            'agenda' => 'required|string|max:500',
            'result_text' => 'nullable|string|max:2000',
            'next_action' => 'nullable|string|max:2000',
            'schedule_attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        if ($user->role === 'koordinator') {
            $data['schedule_date'] = now()->toDateString();
        }
        if ($user->role === 'koordinator' && $user->regional !== $data['wilayah']) {
            abort(422, 'Koordinator hanya bisa mengatur regionalnya sendiri.');
        }
        $profile = $this->profiles[$data['wilayah']] ?? ['name' => $data['koordinator_name'], 'home' => ''];
        $kor = RsmUser::where('name', $data['koordinator_name'])->where('regional', $data['wilayah'])->first();
        $schedule = RsmCoordinatorSchedule::updateOrCreate(
            [
                'area' => $user->area ?: 'Regional B',
                'schedule_date' => $data['schedule_date'],
                'wilayah' => $data['wilayah'],
                'unit_name' => $data['unit_name'],
                'koordinator_name' => $data['koordinator_name'],
            ],
            [
                'koordinator_user_id' => $kor?->id,
                'koordinator_home' => $profile['home'],
                'visit_type' => $data['visit_type'],
                'status' => $data['status'],
                'agenda' => $data['agenda'],
                'result_text' => $data['result_text'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'created_by_user_id' => $user->id,
                'created_by_name' => $user->name,
            ]
        );

        $hasChecklistInput = $this->hasChecklistInput($request);
        $attachmentPath = null;
        if ($request->hasFile('schedule_attachment')) {
            if ($schedule->attachment_path) {
                Storage::disk('public')->delete($schedule->attachment_path);
            }
            $attachmentPath = $request->file('schedule_attachment')->store('coordinator-schedules', 'public');
        }
        if ($hasChecklistInput || $attachmentPath !== null) {
            $update = ['checklist_json' => json_encode($this->checklistFromRequest($request), JSON_UNESCAPED_UNICODE)];
            if ($attachmentPath !== null) {
                $update['attachment_path'] = $attachmentPath;
            }
            $schedule->update($update);
        }

        return back()->with('status', 'Jadwal berhasil disimpan.');
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageCoordinatorSchedule($user), 403);
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $area = $user->area ?: 'Regional B';

        $campusesByRegional = DB::table('partner_campuses')
            ->whereNotNull('wilayah')
            ->orderByRaw('COALESCE(display_name, name)')
            ->get(['wilayah', DB::raw('COALESCE(display_name, name) as label')])
            ->groupBy('wilayah')
            ->map(fn ($rows) => $rows->pluck('label')->unique()->values()->all());

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $end = $start->copy()->endOfMonth();
        $count = 0;
        $campusIndex = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $weekday = $day->dayOfWeekIso;
            foreach ($this->profiles as $regional => $profile) {
                if ($user->role === 'koordinator' && $user->regional !== $regional) {
                    continue;
                }

                $isWeeklyReview = $weekday === 5 || $weekday >= 6;
                if ($isWeeklyReview) {
                    $unit = "Rekap dan laporan mingguan {$regional}";
                    $visitType = 'Zoom';
                    $agenda = $weekday >= 6
                        ? 'Follow up weekend, evaluasi leads, dan persiapan kunjungan pekan berikutnya'
                        : 'Rekap mingguan dan follow up kendala kampus';
                } else {
                    $campuses = $campusesByRegional[$regional] ?? [];
                    $index = $campusIndex[$regional] ?? 0;
                    $unit = $campuses !== [] ? $campuses[$index % count($campuses)] : "Kampus prioritas {$regional}";
                    $campusIndex[$regional] = $index + 1;
                    $visitType = $this->scheduleVisitType($regional, $unit);
                    $agenda = 'Monitoring PMB, leads, iklan, dan action plan kampus';
                }

                $kor = RsmUser::where('name', $profile['name'])->where('regional', $regional)->first();
                $created = RsmCoordinatorSchedule::firstOrCreate(
                    [
                        'area' => $area,
                        'schedule_date' => $day->toDateString(),
                        'wilayah' => $regional,
                        'unit_name' => $unit,
                        'koordinator_name' => $profile['name'],
                    ],
                    [
                        'koordinator_user_id' => $kor?->id,
                        'koordinator_home' => $profile['home'],
                        'visit_type' => $visitType,
                        'status' => 'Rencana',
                        'agenda' => $agenda,
                        'created_by_user_id' => $user->id,
                        'created_by_name' => $user->name,
                    ]
                );
                $count += $created->wasRecentlyCreated ? 1 : 0;
            }
        }

        return back()->with('status', "Generate selesai: {$count} agenda baru.");
    }

    /** Port of rsm_schedule_visit_type() (rsm_db.php:3030-3045). */
    private function scheduleVisitType(string $regional, string $unitName): string
    {
        $name = mb_strtolower($unitName);
        $patterns = [
            'Regional 5' => '/semarang|yogyakarta|ivet|lia|unaki|undaris|uby|ima/',
            'Regional 6' => '/surabaya|gresik|stiesia|um |uwk|uwika|unigres|ikip/',
            'Regional 7' => '/banyuwangi|jember|bali|stikom|unibabwi|ubhinus|polnas/',
            'Regional 4' => '/makassar|sulawesi|uts|patria/',
        ];

        if (isset($patterns[$regional]) && preg_match($patterns[$regional], $name)) {
            return 'Fisik';
        }

        return 'Zoom';
    }

    /** Port of rsm_coordinator_schedule_checklist_state() (rsm_db.php:3187-3205). */
    private function checklistState(RsmCoordinatorSchedule $row): array
    {
        $saved = json_decode((string) $row->checklist_json, true);
        $saved = is_array($saved) ? $saved : [];
        $state = [];
        foreach (array_keys(self::JOBDESK_ITEMS) as $itemKey) {
            $entry = $saved[$itemKey] ?? null;
            if (is_array($entry)) {
                $state[$itemKey] = ['checked' => ! empty($entry['checked']), 'note' => trim((string) ($entry['note'] ?? ''))];
            } else {
                $state[$itemKey] = ['checked' => ! empty($entry), 'note' => ''];
            }
        }

        return $state;
    }

    /** Port of rsm_coordinator_checklist_summary() (rsm_db.php:3210-3245). */
    private function checklistSummary($rows): array
    {
        $totals = array_fill_keys(array_keys(self::JOBDESK_ITEMS), 0);
        $checked = array_fill_keys(array_keys(self::JOBDESK_ITEMS), 0);
        $considered = 0;
        foreach ($rows as $row) {
            if ($row->status !== 'Selesai' || blank($row->checklist_json)) {
                continue;
            }
            $considered++;
            foreach ($this->checklistState($row) as $itemKey => $entry) {
                $totals[$itemKey]++;
                if (! empty($entry['checked'])) {
                    $checked[$itemKey]++;
                }
            }
        }
        $summary = [];
        foreach (self::JOBDESK_ITEMS as $itemKey => $label) {
            $total = $totals[$itemKey];
            $done = $checked[$itemKey];
            $summary[] = [
                'key' => $itemKey,
                'label' => $label,
                'checked' => $done,
                'total' => $total,
                'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ];
        }
        usort($summary, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return ['considered' => $considered, 'items' => $summary];
    }

    private function checklistFromRequest(Request $request): array
    {
        $checklist = [];
        foreach (array_keys(self::JOBDESK_ITEMS) as $itemKey) {
            $note = (string) $request->input('checklist_note_'.$itemKey, '');
            if (mb_strlen($note) > 200) {
                $note = mb_substr($note, 0, 200);
            }
            $checklist[$itemKey] = [
                'checked' => $request->input('checklist_'.$itemKey) === '1',
                'note' => $note,
            ];
        }

        return $checklist;
    }

    private function hasChecklistInput(Request $request): bool
    {
        foreach (array_keys(self::JOBDESK_ITEMS) as $itemKey) {
            if ($request->has('checklist_'.$itemKey) || $request->has('checklist_note_'.$itemKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Laporan WhatsApp Kunjungan Koordinator" — super_user only, generates
     * a cached text artifact (not a live per-request dump). Port of the
     * generate_coordinator_whatsapp_report handler (rsm_db.php:3546-3556)
     * + rsm_coordinator_schedule_whatsapp_text() (rsm_db.php:3246-3296).
     */
    public function whatsapp(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'super_user', 403);
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $area = $user->area ?: 'Regional B';

        $text = $this->coordinatorWhatsappText($area);
        $this->saveWhatsappArtifact($text, $month);

        return back()->with('status', 'Laporan WhatsApp kunjungan koordinator berhasil dibuat.');
    }

    private function coordinatorWhatsappText(string $area): string
    {
        // Legacy always builds this for the CURRENT month/day regardless of
        // which $month was requested — the $month param only tags the
        // saved artifact's metadata (rsm_db.php:3246-3251).
        $today = now()->toDateString();
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $todayLabel = $days[(int) now()->format('w')].', '.now()->format('d/m/Y');

        [, $grouped] = $this->fetchSchedules($area, now()->format('Y-m'), [], null);

        $lines = ['*LAPORAN KUNJUNGAN KOORDINATOR WILAYAH*', $area.' - '.$todayLabel, ''];

        foreach (AreaRegionals::forArea($area) as $regional) {
            $regionalRows = $grouped[$regional] ?? collect();
            $todayRows = $regionalRows->filter(fn ($r) => $r->schedule_date->toDateString() === $today)->values();
            $profile = $this->profiles[$regional] ?? ['name' => '-', 'home' => '-'];
            $lines[] = '*'.$regional.' - '.$profile['name'].'*';
            $doneRows = $todayRows->filter(fn ($r) => $r->status === 'Selesai')->values();
            if ($doneRows->isEmpty()) {
                $lines[] = '- Belum ada laporan kunjungan hari ini.';
            } else {
                foreach ($doneRows as $row) {
                    $result = trim((string) $row->result_text);
                    $resultLabel = $result !== '' ? $result : 'Belum ada catatan hasil.';
                    if (mb_strlen($resultLabel) > 140) {
                        $resultLabel = mb_substr($resultLabel, 0, 140).'...';
                    }
                    $lines[] = '- '.$row->unit_name.': '.$resultLabel;
                    if (blank($row->checklist_json)) {
                        $lines[] = '  Checklist: belum diisi';
                    } else {
                        $state = $this->checklistState($row);
                        $lines[] = '  Checklist:';
                        foreach (self::JOBDESK_ITEMS as $itemKey => $itemLabel) {
                            $entry = $state[$itemKey] ?? ['checked' => false, 'note' => ''];
                            $itemStatus = ! empty($entry['checked']) ? 'Lengkap' : 'Belum Lengkap';
                            $itemNote = trim((string) ($entry['note'] ?? ''));
                            $itemLine = '  '.$itemKey.'. '.$itemLabel.' - '.$itemStatus;
                            if ($itemNote !== '') {
                                $itemLine .= ' (catatan: '.$itemNote.')';
                            }
                            $lines[] = $itemLine;
                        }
                    }
                }
            }
            $lines[] = '';
        }

        $lines[] = '_Digenerate otomatis dari RSM Dashboard, '.now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB_';

        return implode("\n", $lines);
    }

    private function saveWhatsappArtifact(string $text, string $month): array
    {
        $artifact = ['text' => $text, 'month' => $month, 'generated_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s')];
        Storage::disk('local')->put(self::WHATSAPP_ARTIFACT_PATH, json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $artifact;
    }

    private function latestWhatsappArtifact(): ?array
    {
        if (! Storage::disk('local')->exists(self::WHATSAPP_ARTIFACT_PATH)) {
            return null;
        }
        $decoded = json_decode(Storage::disk('local')->get(self::WHATSAPP_ARTIFACT_PATH), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** "Edit Jadwal" — unit/tipe/agenda saja, port rsm_update_coordinator_schedule(). */
    public function update(Request $request, RsmCoordinatorSchedule $schedule)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageCoordinatorSchedule($user), 403);
        $this->scope($schedule, $user);
        $data = $request->validate([
            'unit_name' => 'required|string|max:200',
            'visit_type' => ['required', Rule::in(['Fisik', 'Zoom', 'Telepon'])],
            'agenda' => 'required|string|max:500',
        ]);
        $schedule->update($data);

        return back()->with('status', 'Jadwal berhasil diperbarui.');
    }

    /** "Laporan Hasil Kunjungan" — status/hasil/follow up/checklist/dokumentasi, port rsm_report_coordinator_schedule(). */
    public function report(Request $request, RsmCoordinatorSchedule $schedule)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageCoordinatorSchedule($user), 403);
        $this->scope($schedule, $user);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Rencana', 'Dijadwalkan', 'Selesai', 'Reschedule'])],
            'result_text' => 'nullable|string|max:2000',
            'next_action' => 'nullable|string|max:2000',
            'schedule_attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        $data = [
            'status' => $validated['status'],
            'result_text' => $validated['result_text'] ?? null,
            'next_action' => $validated['next_action'] ?? null,
            'checklist_json' => json_encode($this->checklistFromRequest($request), JSON_UNESCAPED_UNICODE),
        ];
        if ($request->hasFile('schedule_attachment')) {
            if ($schedule->attachment_path) {
                Storage::disk('public')->delete($schedule->attachment_path);
            }
            $data['attachment_path'] = $request->file('schedule_attachment')->store('coordinator-schedules', 'public');
        }
        $schedule->update($data);

        return back()->with('status', 'Laporan hasil kunjungan berhasil disimpan.');
    }

    public function attachment(Request $request, RsmCoordinatorSchedule $schedule)
    {
        $this->scope($schedule, $request->user());
        abort_unless($schedule->attachment_path && Storage::disk('public')->exists($schedule->attachment_path), 404);

        return Storage::disk('public')->response($schedule->attachment_path);
    }

    /** Koordinator tidak boleh menghapus jadwal — rsm_delete_coordinator_schedule() melempar error eksplisit untuk role ini. */
    public function destroy(Request $request, RsmCoordinatorSchedule $schedule)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageCoordinatorSchedule($user) && $user->role !== 'koordinator', 403);
        $this->scope($schedule, $user);
        $schedule->delete();

        return back()->with('status', 'Jadwal berhasil dihapus.');
    }

    private function scope(RsmCoordinatorSchedule $schedule, RsmUser $user): void
    {
        abort_unless(
            $schedule->area === ($user->area ?: 'Regional B') && ($user->role !== 'koordinator' || $schedule->wilayah === $user->regional),
            404
        );
    }
}
