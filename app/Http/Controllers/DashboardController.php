<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\BdcReportUsersService;
use App\Services\CollabSourceService;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Orchestrates the Dashboard Utama page. One non-obvious rule preserved on
 * purpose: a logged-in `staff` user gets a *second*, koordinator-scoped
 * Collab pull (their own regional, no staff filter) to populate the
 * "Pencapaian Staff Regional" panel — not their own personal totals
 * (regionalStaffAchievement()).
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
        $staffAchievement = CollabMetricsService::personalPerformance($area, $filters, $user);
        $regionalStaffAchievement = $this->regionalStaffAchievement($area, $filters, $user, $staffAchievement);
        $gamification = GamificationService::build($area, $filters, $user);
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        // Rekap Pencapaian's "Total Closing Kampus" card: same "Closing Kampus
        // Regional" collab report as /sumber-collab, not the staff-level
        // registrasi total used by the summary cards above.
        $totalClosingKampus = (float) array_sum(array_column($campusClosing['rows'], 'registrasi'));

        // Summary tiles: all 6 read straight from /sumber-collab report totals
        // (regional 4-7 only, via allowedRegionals()/AreaRegionals) instead of
        // this page's own rsm_reports-derived KPIs, per explicit request.
        $herregKampusRows = CollabMetricsService::campusTotals($filters, $area, $user, 'Herreg Kampus Regional');
        $totalHerregKampus = (float) array_sum(array_column($herregKampusRows['rows'], 'registrasi'));
        $totalClosingPersonal = CollabMetricsService::personalTotal($filters, $area, $user, 'Closing Personal Per Regional');
        $totalHerregPersonal = CollabMetricsService::personalTotal($filters, $area, $user, 'Herreg Personal Per Regional');
        $totalRealisasiIklan = (float) $overview['budget']['spend'];
        $pmbTotals = CollabSourceService::pmbRegionalTotals(AreaRegionals::forArea($area));

        $summaryCards = [
            ['label' => 'Closing Kampus', 'value' => number_format($totalClosingKampus, 0, ',', '.'), 'tone' => 'blue-dark'],
            ['label' => 'Herreg Kampus', 'value' => number_format($totalHerregKampus, 0, ',', '.'), 'tone' => 'blue'],
            ['label' => 'Closing Personal', 'value' => number_format($totalClosingPersonal, 0, ',', '.'), 'tone' => 'blue-light'],
            ['label' => 'Herreg Personal', 'value' => number_format($totalHerregPersonal, 0, ',', '.'), 'tone' => 'red'],
            ['label' => 'Realisasi Iklan', 'value' => 'Rp '.number_format($totalRealisasiIklan, 0, ',', '.'), 'tone' => 'orange'],
            ['label' => 'Reg / Herreg All', 'value' => number_format($pmbTotals['daftar'], 0, ',', '.').' / '.number_format($pmbTotals['herreg'], 0, ',', '.'), 'tone' => 'yellow'],
        ];

        $regionalRecaps = array_map(
            fn (string $regional) => $this->regionalRecap($area, $filters, $user, $regional),
            $this->recapRegionals($area, $user)
        );

        [$topStaffAchievement, $topStaffMaxValue] = $this->topStaffAchievement($area, $user, $regionalStaffAchievement);

        return view('dashboard.index', [
            'active' => 'dashboard',
            'area' => $area,
            'filters' => $filters,
            'isSeniorTier' => in_array($user->role, self::SENIOR_TIER, true),
            'referenceOptions' => $referenceOptions,
            'summaryCards' => $summaryCards,
            'regionalRecaps' => $regionalRecaps,
            'campusClosing' => $campusClosing,
            'topStaffAchievement' => $topStaffAchievement,
            'topStaffMaxValue' => $topStaffMaxValue,
            'gamification' => $gamification,
            'ranking' => $overview['ranking'],
            'dailyReports' => $overview['daily_reports'],
        ]);
    }

    /**
     * Regionals to render a recap card for. Mirrors
     * CollabMetricsService::allowedRegionals(): koordinator/staff only ever
     * see their own regional's data regardless of which regional a card
     * claims to be, so a staff/koordinator user only gets their own card
     * instead of 4 identically-scoped cards under different labels.
     */
    private function recapRegionals(string $area, RsmUser $user): array
    {
        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            return [$user->regional];
        }

        return AreaRegionals::forArea($area);
    }

    /**
     * The Dashboard's "Regional 4" recap card: the same 6 /sumber-collab
     * totals as the summary tiles above, narrowed to just this one
     * regional, plus 6 /bdc-users column totals for staff in that regional.
     */
    private function regionalRecap(string $area, array $filters, RsmUser $user, string $regional): array
    {
        $regionalFilters = array_merge($filters, ['wilayah' => $regional]);

        $closingKampus = CollabMetricsService::campusTotals($regionalFilters, $area, $user);
        $herregKampus = CollabMetricsService::campusTotals($regionalFilters, $area, $user, 'Herreg Kampus Regional');
        $closingPersonal = CollabMetricsService::personalTotal($regionalFilters, $area, $user, 'Closing Personal Per Regional');
        $herregPersonal = CollabMetricsService::personalTotal($regionalFilters, $area, $user, 'Herreg Personal Per Regional');
        $realisasiIklan = (float) DashboardOverviewService::build($area, $regionalFilters, $user)['budget']['spend'];
        $pmb = CollabSourceService::pmbRegionalTotals([$regional]);
        $bdc = BdcReportUsersService::regionalTotals($regional, $user->role === 'staff' ? $user->campus_name : null);
        $koordinator = RsmUser::query()->where('area', $area)->where('role', 'koordinator')->where('regional', $regional)->where('is_active', true)->first();

        return [
            'label' => $regional,
            'koordinator_name' => $koordinator?->name ?: '-',
            'koordinator_jabatan' => $koordinator?->jabatan ?: \App\Support\RsmRole::label('koordinator'),
            'koordinator_photo' => $koordinator?->photoUrl(),
            'total_closing_kampus' => (float) array_sum(array_column($closingKampus['rows'], 'registrasi')),
            'total_herreg_kampus' => (float) array_sum(array_column($herregKampus['rows'], 'registrasi')),
            'total_closing_personal' => $closingPersonal,
            'total_herreg_personal' => $herregPersonal,
            'total_realisasi_iklan' => $realisasiIklan,
            'pmb_daftar' => $pmb['daftar'],
            'pmb_herreg' => $pmb['herreg'],
            'bdc_total' => $bdc['total'],
            'bdc_data_baru' => $bdc['data_baru'],
            'bdc_fu_hari_ini' => $bdc['fu_hari_ini'],
            'bdc_closing' => $bdc['closing'],
            'bdc_wawancara' => $bdc['wawancara'],
            'bdc_belum_herreg' => $bdc['belum_herreg'],
        ];
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

        return CollabMetricsService::personalPerformance($area, $regionalFilters, $regionalUser);
    }

    private function topStaffAchievement(string $area, RsmUser $user, array $regionalStaffAchievement): array
    {
        $achievementUsers = RsmUser::query()->where('area', $area)->get()->keyBy(fn (RsmUser $u) => mb_strtolower(trim($u->name)));

        $rows = collect($regionalStaffAchievement['rows'])
            ->filter(fn (array $row) => trim($row['name']) !== '' && $row['name'] !== '-')
            ->map(function (array $row) use ($achievementUsers) {
                $match = $achievementUsers->get(mb_strtolower(trim($row['name'])));
                $row['photo_path'] = $match?->photoUrl();
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
