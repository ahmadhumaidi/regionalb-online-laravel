<?php

namespace App\Http\Controllers;

use App\Models\RsmCrmLead;
use App\Models\RsmUser;
use App\Services\Crm\CrmLeadScope;
use App\Support\AreaRegionals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Standalone lead/CTWA CRM — not tied to rsm_reports (see
 * app/Services/Crm/CrmLeadScope.php for the row-visibility rules). Modeled
 * after CoordinatorScheduleController: one index page with filters +
 * summary + list, create/edit/status/delete all posted from that same
 * page via native <dialog> modals.
 */
class CrmLeadController extends Controller
{
    private const SOURCES = ['CTWA', 'Organic', 'Referral', 'Walk-in', 'Lainnya'];

    private const STATUSES = ['Baru', 'Dihubungi', 'Follow Up', 'Closing', 'Gagal'];

    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        $query = CrmLeadScope::apply(RsmCrmLead::query()->with('owner'), $user);
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->query('source'));
        }
        if ($request->filled('wilayah') && $user->role !== 'staff') {
            $query->where('wilayah', $request->query('wilayah'));
        }
        $isFullAccess = CrmLeadScope::isFullAccessRole($user);
        if ($isFullAccess && $request->boolean('unassigned')) {
            $query->whereNull('wilayah');
        }

        $rows = $query->orderByDesc('created_at')->get();

        $summary = [
            'total' => $rows->count(),
            'ctwa' => $rows->where('source', 'CTWA')->count(),
            'closing' => $rows->where('status', 'Closing')->count(),
            'baru' => $rows->where('status', 'Baru')->count(),
        ];

        return view('crm.index', [
            'active' => 'crm',
            'rows' => $rows,
            'summary' => $summary,
            'sources' => self::SOURCES,
            'statuses' => self::STATUSES,
            'regionals' => AreaRegionals::forArea($area),
            'user' => $user,
            'isFullAccess' => $isFullAccess,
            'staffOptions' => $isFullAccess
                ? RsmUser::where('role', 'staff')->where('area', $area)->orderBy('name')->get(['id', 'name', 'regional'])
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'lead_name' => 'required|string|max:180',
            'whatsapp' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:180',
            'campus_name' => 'nullable|string|max:180',
            'major_name' => 'nullable|string|max:180',
            'origin_city' => 'nullable|string|max:120',
            'source' => ['required', Rule::in(self::SOURCES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'wilayah' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        $wilayah = $user->role === 'staff' ? $user->regional : ($data['wilayah'] ?? $user->regional);
        if ($user->role === 'koordinator' && $wilayah !== $user->regional) {
            abort(422, 'Koordinator hanya bisa menambah lead untuk wilayahnya sendiri.');
        }

        RsmCrmLead::create([
            'area' => $user->area ?: 'Regional B',
            'wilayah' => $wilayah,
            'campus_name' => $data['campus_name'] ?? null,
            'owner_user_id' => $user->id,
            'created_by_name' => $user->name,
            'lead_name' => $data['lead_name'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'major_name' => $data['major_name'] ?? null,
            'origin_city' => $data['origin_city'] ?? null,
            'source' => $data['source'],
            'status' => $data['status'] ?? 'Baru',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Lead berhasil ditambahkan.');
    }

    public function update(Request $request, RsmCrmLead $lead)
    {
        $user = $request->user();
        $this->authorizeManage($user, $lead);

        $rules = [
            'lead_name' => 'required|string|max:180',
            'whatsapp' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:180',
            'campus_name' => 'nullable|string|max:180',
            'major_name' => 'nullable|string|max:180',
            'origin_city' => 'nullable|string|max:120',
            'source' => ['required', Rule::in(self::SOURCES)],
            'notes' => 'nullable|string|max:2000',
        ];

        // Only the full-access tier may (re)assign wilayah/owner — e.g. claiming
        // an unassigned lead captured by WhatsAppWebhookController. Koordinator/
        // staff submitting these fields (they're not in the edit form for them)
        // is simply ignored rather than rejected.
        $isFullAccess = CrmLeadScope::isFullAccessRole($user);
        if ($isFullAccess) {
            $rules['wilayah'] = 'nullable|string|max:120';
            $rules['owner_user_id'] = ['nullable', 'integer', Rule::exists('rsm_users', 'id')->where(fn ($q) => $q->where('role', 'staff'))];
        }

        $data = $request->validate($rules);
        if (! $isFullAccess) {
            unset($data['wilayah'], $data['owner_user_id']);
        }

        $lead->update($data);

        return back()->with('status', 'Lead berhasil diperbarui.');
    }

    public function updateStatus(Request $request, RsmCrmLead $lead)
    {
        $user = $request->user();
        $this->authorizeManage($user, $lead);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'follow_up_result' => 'nullable|string|max:2000',
        ]);

        $lead->update([
            'status' => $data['status'],
            'follow_up_result' => $data['follow_up_result'] ?? null,
        ]);

        return back()->with('status', 'Status lead berhasil diperbarui.');
    }

    public function destroy(Request $request, RsmCrmLead $lead)
    {
        $user = $request->user();
        $this->authorizeManage($user, $lead);
        $lead->delete();

        return back()->with('status', 'Lead berhasil dihapus.');
    }

    private function authorizeManage(RsmUser $user, RsmCrmLead $lead): void
    {
        abort_unless($lead->area === ($user->area ?: 'Regional B'), 404);
        abort_unless(CrmLeadScope::canManage($user, (string) $lead->wilayah, $lead->owner_user_id), 403);
    }
}
