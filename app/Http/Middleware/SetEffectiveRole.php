<?php

namespace App\Http\Middleware;

use App\Support\RsmRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ports dashboard.php's `?role=` preview (lines 236-245): a user may view
 * the dashboard as any role they outrank via allowed_effective_roles(),
 * without a real identity switch (that's ImpersonationController's job).
 * The resolved role is shared to every view as `effectiveRole`.
 */
class SetEffectiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $homeArea = $user->area;
        $allowedAreas = $user->accessibleAreas();
        $selectedArea = $request->session()->get('selected_area.'.$user->id, $homeArea);
        if (in_array($selectedArea, $allowedAreas, true)) {
            // Existing services scope queries by user->area. Keep this session
            // selection clean so profile saves cannot overwrite the home area.
            $user->area = $selectedArea;
            $user->syncOriginalAttribute('area');
        }
        View::share('allowedAreas', $allowedAreas);
        $actualRole = $user->role;
        $allowedRoleKeys = RsmRole::allowedEffectiveRoles($actualRole);

        $requestedRole = strtolower((string) $request->query('role', $actualRole));
        $effectiveRole = in_array($requestedRole, $allowedRoleKeys, true) ? $requestedRole : $actualRole;

        $request->attributes->set('actualRole', $actualRole);
        $request->attributes->set('effectiveRole', $effectiveRole);
        $request->attributes->set('allowedRoleKeys', $allowedRoleKeys);

        View::share('actualRole', $actualRole);
        View::share('effectiveRole', $effectiveRole);
        View::share('allowedRoleKeys', $allowedRoleKeys);

        try {
            return $next($request);
        } finally {
            $user->area = $homeArea;
            $user->syncOriginalAttribute('area');
        }
    }
}
