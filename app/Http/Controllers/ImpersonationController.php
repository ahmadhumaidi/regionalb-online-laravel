<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Support\RsmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ports rsm_impersonate_user()/rsm_stop_impersonation(): a senior+ role can
 * temporarily act as another user in the same area. Session key
 * `impersonation.original_id` plays the role of the legacy
 * `$_SESSION['rsm_original_user_id']` — kept as a distinct mechanism from
 * the lighter-weight `?role=` preview handled by SetEffectiveRole.
 */
class ImpersonationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $actor = $this->actingAdmin($request);

        abort_unless(RsmRole::canImpersonate($actor), 403, 'Hanya Senior Manager ke atas yang bisa masuk sebagai user lain.');

        $target = RsmUser::where('is_active', true)->find($data['user_id']);
        abort_if(! $target, 422, 'User tujuan tidak ditemukan atau tidak aktif.');

        if (! empty($target->area) && $target->area !== $actor->area) {
            abort(422, 'User tujuan berada di luar area ini.');
        }

        if ($target->id === $actor->id) {
            return $this->destroy($request);
        }

        if (! $request->session()->has('impersonation.original_id')) {
            $request->session()->put('impersonation.original_id', $actor->id);
        }

        Auth::loginUsingId($target->id);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $originalId = (int) $request->session()->get('impersonation.original_id', 0);

        if ($originalId > 0) {
            Auth::loginUsingId($originalId);
        }

        $request->session()->forget('impersonation.original_id');

        return back();
    }

    private function actingAdmin(Request $request): RsmUser
    {
        $originalId = (int) $request->session()->get('impersonation.original_id', 0);

        if ($originalId > 0) {
            $original = RsmUser::find($originalId);
            if ($original) {
                return $original;
            }
        }

        return Auth::user();
    }
}
