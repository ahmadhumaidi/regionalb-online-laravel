<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardNumbers;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Dashboard\StaffProfileMatcher;
use App\Services\Dashboard\TargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Ports "Pencapaian Staff" (dashboard.php:845-901). Read-only: the
 * "Sinkronisasi Data Pencapaian" button and the sync-health panel are
 * deferred — both depend on infrastructure (rsm_bdc_report_user_snapshots
 * fetch/cache, file-based Collab cache reader) not yet built in Laravel,
 * shared with the not-yet-ported BDC Marketing / Sumber Data Collab pages.
 */
class AchievementController extends Controller
{
    /** Mirrors DashboardController::NON_STAFF_ROLES: this table is staff-only, koordinator/senior/etc. shouldn't show up in it even if they have a personal closing row. */
    private const NON_STAFF_ROLES = ['super_user', 'executive_director', 'director', 'senior', 'mentor', 'koordinator'];

    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        $filters = DashboardFilters::fromRequest($request, 'pencapaian');

        // The filter-bar box for staff ("Wilayah"/"Staff") is read-only
        // display only, never actually submitted - this page is meant to
        // show all of Regional B, not just the logged-in staff member's own
        // row, so bypass the services' own staff auto-scoping the same way
        // DashboardController does for its "Pencapaian Staff Regional" panel.
        $scopeUser = $user->role === 'staff'
            ? (clone $user)->setRawAttributes(array_merge($user->getAttributes(), ['role' => 'senior']))
            : $user;

        $performance = CollabMetricsService::personalPerformance($area, $filters, $scopeUser);
        $target = TargetService::build($area, $filters, $scopeUser);
        $regionalTargets = TargetService::regionalSummary($area, $filters, $scopeUser);
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        $registrasi = (float) $performance['totals']['registrasi'];
        $herregistrasi = (float) $performance['totals']['herregistrasi'];
        $targetRegistrasi = (float) ($target['target_registrasi'] ?? 0);
        $targetHerregistrasi = (float) ($target['target_herregistrasi'] ?? 0);

        $summaryCards = [
            [
                'label' => 'Total Registrasi', 'tone' => 'green',
                'value' => number_format($registrasi, 0, ',', '.'),
                'note' => $targetRegistrasi > 0
                    ? DashboardNumbers::percent($registrasi, $targetRegistrasi).'% dari target '.number_format($targetRegistrasi, 0, ',', '.')
                    : 'Target belum diatur',
            ],
            [
                'label' => 'Total Herregistrasi', 'tone' => 'purple',
                'value' => number_format($herregistrasi, 0, ',', '.'),
                'note' => $targetHerregistrasi > 0
                    ? DashboardNumbers::percent($herregistrasi, $targetHerregistrasi).'% dari target '.number_format($targetHerregistrasi, 0, ',', '.')
                    : 'Target belum diatur',
            ],
            ['label' => 'Staff Terbaca', 'tone' => 'blue', 'value' => number_format($performance['totals']['staff_count'], 0, ',', '.'), 'note' => 'Staff dengan data pada periode/filter ini'],
            ['label' => 'Sumber Data', 'tone' => 'slate', 'value' => 'Collab', 'note' => 'Closing Personal Per Regional'],
        ];

        $regionalSummary = collect($performance['regional_summary'])->map(function (array $row) use ($regionalTargets) {
            $targets = $regionalTargets[$row['regional']] ?? null;
            $row['target_registrasi'] = (float) ($targets['target_registrasi'] ?? 0);
            $row['target_herregistrasi'] = (float) ($targets['target_herregistrasi'] ?? 0);

            return $row;
        })->all();

        $profileIndex = StaffProfileMatcher::index($area);
        $rows = collect($performance['rows'])
            ->map(function (array $row) use ($profileIndex) {
                $row['name'] = StaffProfileMatcher::cleanName((string) $row['name']);
                $matchedUser = StaffProfileMatcher::find($profileIndex, $row['name'], (string) ($row['nik'] ?? ''));
                $row['photo'] = $matchedUser?->photoUrl();
                $row['campus_name'] = $matchedUser?->campus_name;
                $row['matched_role'] = $matchedUser?->role;

                return $row;
            })
            ->reject(fn (array $row) => in_array($row['matched_role'] ?? null, self::NON_STAFF_ROLES, true))
            ->values()
            ->all();

        return view('pencapaian.index', [
            'active' => 'pencapaian',
            'filters' => $filters,
            'referenceOptions' => $referenceOptions,
            'summaryCards' => $summaryCards,
            'sources' => $performance['sources'],
            'regionalSummary' => $regionalSummary,
            'rows' => $rows,
        ]);
    }
}
