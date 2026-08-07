<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardNumbers;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Dashboard\TargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Orchestrates the Dashboard Utama page exactly like legacy dashboard.php's
 * variable-computation block (lines ~318-507) — including its two
 * non-obvious override rules, preserved here on purpose:
 *
 * 1. Displayed Registrasi/Herregistrasi come from the Collab-sourced staff
 *    totals, not this page's own rsm_reports-derived KPI numbers (those
 *    still power the budget/cost-per-registrasi denominators only).
 * 2. A logged-in `staff` user gets a *second*, koordinator-scoped Collab
 *    pull (their own regional, no staff filter) to populate the
 *    "Pencapaian Staff Regional" panel — not their own personal totals.
 */
class DashboardController extends Controller
{
    private const SENIOR_TIER = ['super_user', 'executive_director', 'director', 'senior'];

    private const NON_STAFF_ROLES = ['super_user', 'executive_director', 'director', 'senior', 'mentor', 'koordinator'];

    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        $filters = DashboardFilters::fromRequest($request, 'dashboard');

        $campusClosing = CollabMetricsService::campusTotals($filters, $area, $user);
        $overview = DashboardOverviewService::build($area, $filters, $user);
        $staffAchievement = CollabMetricsService::staffPerformance($area, $filters, $user);
        $regionalStaffAchievement = $this->regionalStaffAchievement($area, $filters, $user, $staffAchievement);
        $gamification = GamificationService::build($area, $filters, $user);
        $target = TargetService::build($area, $filters, $user);
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        $displayLeads = (float) $overview['kpi']['leads'];
        $displayRegistrasi = (float) ($staffAchievement['totals']['registrasi'] ?? $overview['kpi']['registrasi']);
        $displayHerregistrasi = (float) ($staffAchievement['totals']['herregistrasi'] ?? $overview['kpi']['herregistrasi']);
        $displayConversionRate = DashboardNumbers::percent($displayRegistrasi, $displayLeads);
        $displayCostPerRegistrasi = DashboardNumbers::divide((float) $overview['budget']['spend'], $displayRegistrasi);
        $registrationSource = $staffAchievement['sources']['registrasi']['label'] ?? 'Closing Collab';
        $herregistrationSource = $staffAchievement['sources']['herregistrasi']['label'] ?? 'Herreg Collab';

        $targetRegistrasi = (float) ($target['target_registrasi'] ?? 0);
        $targetProgress = $targetRegistrasi > 0 ? DashboardNumbers::percent($displayRegistrasi, $targetRegistrasi) : 0.0;

        $summaryCards = [
            ['label' => 'Leads', 'value' => number_format($displayLeads, 0, ',', '.'), 'tone' => 'blue', 'note' => 'Total lead pada periode/filter ini'],
            ['label' => 'Follow Up', 'value' => number_format($overview['kpi']['follow_up'], 0, ',', '.'), 'tone' => 'cyan', 'note' => 'Dari detail lead yang sudah ditindaklanjuti'],
            ['label' => 'Registrasi', 'value' => number_format($displayRegistrasi, 0, ',', '.'), 'tone' => 'green', 'note' => 'Acuan: '.$registrationSource],
            ['label' => 'Herregistrasi', 'value' => number_format($displayHerregistrasi, 0, ',', '.'), 'tone' => 'purple', 'note' => 'Acuan: '.$herregistrationSource],
            ['label' => 'Conversion Rate', 'value' => number_format($displayConversionRate, 2, ',', '.').'%', 'tone' => 'amber', 'note' => 'Registrasi dibagi leads'],
            ['label' => 'Target PMB', 'value' => $targetRegistrasi > 0 ? number_format($targetRegistrasi, 0, ',', '.') : 'Belum diatur', 'tone' => 'slate', 'note' => $targetRegistrasi > 0 ? 'Target bulan ini' : 'Target belum diatur untuk periode/filter ini'],
        ];

        $registrationRecap = [
            'registrasi' => $displayRegistrasi,
            'registrasi_source' => $registrationSource,
            'target_registrasi' => $targetRegistrasi,
            'target_progress' => $targetProgress,
            'target_staff_count' => $target['staff_target_count'] ?? 0,
            'leads' => $displayLeads,
            'herregistrasi' => $displayHerregistrasi,
            'herregistrasi_source' => $herregistrationSource,
            'cost_per_registrasi' => $displayCostPerRegistrasi,
        ];

        [$topStaffAchievement, $topStaffMaxValue] = $this->topStaffAchievement($area, $user, $regionalStaffAchievement);

        return view('dashboard.index', [
            'active' => 'dashboard',
            'area' => $area,
            'filters' => $filters,
            'referenceOptions' => $referenceOptions,
            'summaryCards' => $summaryCards,
            'registrationRecap' => $registrationRecap,
            'budget' => $overview['budget'],
            'campusClosing' => $campusClosing,
            'topStaffAchievement' => $topStaffAchievement,
            'topStaffMaxValue' => $topStaffMaxValue,
            'gamification' => $gamification,
            'ranking' => $overview['ranking'],
            'dailyReports' => $overview['daily_reports'],
        ]);
    }

    private function regionalStaffAchievement(string $area, array $filters, RsmUser $user, array $staffAchievement): array
    {
        if ($user->role !== 'staff' || trim((string) $user->regional) === '') {
            return $staffAchievement;
        }

        $regionalFilters = $filters;
        $regionalFilters['wilayah'] = $user->regional;
        $regionalFilters['staff_name'] = '';

        $regionalUser = (clone $user)->setRawAttributes(array_merge($user->getAttributes(), ['role' => 'koordinator']));

        return CollabMetricsService::staffPerformance($area, $regionalFilters, $regionalUser);
    }

    private function topStaffAchievement(string $area, RsmUser $user, array $regionalStaffAchievement): array
    {
        $achievementUsers = RsmUser::query()->where('area', $area)->get()->keyBy(fn (RsmUser $u) => mb_strtolower(trim($u->name)));

        $rows = collect($regionalStaffAchievement['rows'])
            ->filter(fn (array $row) => trim($row['name']) !== '' && $row['name'] !== '-')
            ->map(function (array $row) use ($achievementUsers) {
                $match = $achievementUsers->get(mb_strtolower(trim($row['name'])));
                $row['photo_path'] = $match?->photo_path;
                $row['campus_name'] = $match?->campus_name;
                $row['user_role'] = $match?->role;

                return $row;
            })
            ->filter(fn (array $row) => ! in_array($row['user_role'], self::NON_STAFF_ROLES, true))
            ->values();

        $maxValue = (float) ($rows->max('registrasi') ?? 0);

        if (in_array($user->role, self::SENIOR_TIER, true)) {
            $rows = $rows->take(5)->values();
        }

        return [$rows->all(), $maxValue];
    }
}
