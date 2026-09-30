<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmUser;
use App\Support\AreaRegionals;
use App\Support\RsmRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless(RsmRole::canViewUsersPage($actor), 403);

        $area = $this->selectedArea($request);
        $users = RsmUser::query()
            ->when(
                $actor->role === RsmUser::ROLE_SUPER_USER,
                fn ($query) => $query->when(
                    $area !== 'all',
                    fn ($areaQuery) => $areaQuery->where(fn ($scopedQuery) => $scopedQuery->where('area', $area)->orWhereNull('area')),
                ),
                fn ($query) => $query->where('area', $area)->whereNotIn('role', [RsmUser::ROLE_SUPER_USER, RsmUser::ROLE_SENIOR]),
            )
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('regional')
            ->orderBy('name')
            ->get();

        $regionals = $area === 'all'
            ? array_merge(AreaRegionals::forArea('Regional A'), AreaRegionals::forArea('Regional B'))
            : AreaRegionals::forArea($area);
        $campuses = DB::table('partner_campuses')
            ->whereIn('wilayah', $regionals)
            ->selectRaw('COALESCE(display_name, name) as label')
            ->orderByRaw('COALESCE(display_name, name)')
            ->pluck('label')
            ->unique()
            ->values()
            ->all();
        $manageableRoles = $this->manageableRoles($actor);
        $formArea = $area === 'all' ? ($actor->area ?: 'Regional B') : $area;

        return view('users.index', compact('users', 'area', 'formArea', 'regionals', 'campuses', 'manageableRoles'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless(RsmRole::canViewUsersPage($actor), 403);

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'nik' => 'nullable|string|max:80',
            'username' => 'required|string|max:100|unique:rsm_users,username',
            'user_role' => ['required', Rule::in($this->manageableRoles($actor))],
            'area' => ['required', Rule::in(['Regional A', 'Regional B'])],
            'regional' => ['nullable', Rule::in(AreaRegionals::forArea((string) $request->input('area')))],
            'campus_name' => 'nullable|string|max:200',
            'new_user_password' => 'required|string|min:6',
        ]);

        abort_if($actor->role !== RsmUser::ROLE_SUPER_USER && $data['area'] !== $actor->area, 403);
        $role = $data['user_role'];
        RsmUser::create([
            'name' => $data['name'],
            'nik' => $data['nik'] ?? null,
            'username' => $data['username'],
            'password_hash' => Hash::make($data['new_user_password']),
            'role' => $role,
            'jabatan' => RsmRole::label($role),
            'area' => $data['area'],
            'regional' => $data['regional'] ?? null,
            'campus_name' => $data['campus_name'] ?? null,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        return redirect()->route('users', ['area' => $data['area']])->with('status', 'User berhasil ditambahkan.');
    }

    public function toggle(Request $request, RsmUser $managedUser)
    {
        $this->authorizeManagement($request->user(), $managedUser);
        abort_if($managedUser->id === $request->user()->id, 422, 'Akun aktif tidak bisa dinonaktifkan.');
        $managedUser->update(['is_active' => ! $managedUser->is_active]);

        return back()->with('status', 'Status user diperbarui.');
    }

    public function update(Request $request, RsmUser $managedUser)
    {
        $actor = $request->user();
        $this->authorizeManagement($actor, $managedUser);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'nik' => 'nullable|string|max:80',
            'user_role' => ['required', Rule::in($this->manageableRoles($actor))],
            'regional' => ['nullable', Rule::in(array_values(array_unique(array_filter([
                ...AreaRegionals::forArea($managedUser->area ?: 'Regional B'),
                $managedUser->regional,
            ]))))],
            'campus_name' => 'nullable|string|max:200',
            'jabatan' => 'nullable|string|max:160',
            'phone_number' => 'nullable|string|max:60',
            'work_duration' => 'nullable|string|max:80',
            'bio_text' => 'nullable|string|max:800',
        ]);
        $data['role'] = $data['user_role'];
        unset($data['user_role']);
        $data['jabatan'] = ($data['jabatan'] ?? null) ?: RsmRole::label($data['role']);
        $managedUser->update($data);
        $this->log($actor, $managedUser, 'admin_update_user', 'Memperbarui user '.$managedUser->username);

        return back()->with('status', 'Data user berhasil diperbarui.');
    }

    public function destroy(Request $request, RsmUser $managedUser)
    {
        $actor = $request->user();
        $this->authorizeManagement($actor, $managedUser);
        abort_if($managedUser->id === $actor->id, 422, 'Akun sendiri tidak bisa dihapus.');
        $username = $managedUser->username;
        $this->log($actor, $managedUser, 'admin_delete_user', 'Menghapus user '.$username);
        $managedUser->delete();

        return back()->with('status', 'User berhasil dihapus.');
    }

    public function resetPassword(Request $request, RsmUser $managedUser)
    {
        $this->authorizeManagement($request->user(), $managedUser);
        $data = $request->validate(['new_password' => 'required|string|min:6']);
        $managedUser->update(['password_hash' => Hash::make($data['new_password']), 'must_change_password' => true]);

        return back()->with('status', 'Password user berhasil direset.');
    }

    private function selectedArea(Request $request): string
    {
        $actor = $request->user();
        $requestedArea = (string) $request->query('area', 'all');

        return $actor->role === RsmUser::ROLE_SUPER_USER && in_array($requestedArea, ['all', 'Regional A', 'Regional B'], true)
            ? $requestedArea
            : ($actor->area ?: 'Regional B');
    }

    /** @return list<string> */
    private function manageableRoles(RsmUser $actor): array
    {
        return $actor->role === RsmUser::ROLE_SUPER_USER
            ? RsmUser::ROLES
            : [RsmUser::ROLE_MENTOR, RsmUser::ROLE_KOORDINATOR, RsmUser::ROLE_STAFF];
    }

    private function authorizeManagement(RsmUser $actor, RsmUser $managedUser): void
    {
        abort_unless(RsmRole::canViewUsersPage($actor), 403);
        if ($actor->role !== RsmUser::ROLE_SUPER_USER) {
            abort_unless(
                $managedUser->area === $actor->area
                && in_array($managedUser->role, $this->manageableRoles($actor), true),
                403,
            );
        }
    }

    private function log(RsmUser $actor, RsmUser $managedUser, string $action, string $note): void
    {
        RsmActivityLog::create([
            'area' => $managedUser->area ?: $actor->area ?: 'Regional B',
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role,
            'actor_name' => $actor->name,
            'action_name' => $action,
            'note' => $note,
        ]);
    }
}
