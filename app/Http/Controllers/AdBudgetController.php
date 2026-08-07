<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\AdBudget\AdBudgetLimitService;
use App\Services\AdBudget\AdBudgetPeriods;
use App\Services\AdBudget\AdBudgetReportsService;
use App\Services\AdBudget\PendingAdReportsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Read-only pass of "Anggaran & Laporan Iklan" (dashboard.php:1182-1221).
 * Approve/reject/revise live in AdBudgetActionController. Ads report
 * creation/editing (including realization reporting) goes through the
 * generic ReportFormController/ReportFormService, role-aware per
 * report_fields_for_type(). Ad-leads Excel import lives in AdLeadController.
 * "Anggaran Perlu Dilaporkan" (staff-only) ports rsm_ads_pending_reports()
 * / render_ads_pending_report_panel() — read-only, no action links.
 */
class AdBudgetController extends Controller
{
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        $period = (string) $request->query('ad_period', '');
        if (! in_array($period, array_column(AdBudgetPeriods::options(), 'value'), true)) {
            $period = AdBudgetPeriods::default();
        }

        $reports = AdBudgetReportsService::build($area, $period, $user);
        $limits = AdBudgetLimitService::build($area, $period, $user);
        $pending = $user->role === 'staff' ? PendingAdReportsService::build($area, $user) : null;

        $totals = $reports['totals'];
        $summaryCards = [
            ['label' => 'Total Anggaran', 'value' => 'Rp '.number_format($totals['requested'], 0, ',', '.'), 'tone' => 'blue', 'note' => 'Anggaran diajukan periode ini'],
            ['label' => 'Total Realisasi', 'value' => 'Rp '.number_format($totals['realization'], 0, ',', '.'), 'tone' => 'green', 'note' => 'Realisasi terlapor periode ini'],
            ['label' => 'Sisa Anggaran', 'value' => 'Rp '.number_format($totals['requested'] - $totals['realization'], 0, ',', '.'), 'tone' => 'amber', 'note' => 'Anggaran dikurangi realisasi'],
            ['label' => 'Leads Iklan', 'value' => number_format($totals['leads'], 0, ',', '.'), 'tone' => 'cyan', 'note' => 'Total leads dari laporan iklan'],
            ['label' => 'Closing Iklan', 'value' => number_format($totals['closing'], 0, ',', '.'), 'tone' => 'purple', 'note' => 'Total closing dari laporan iklan'],
        ];

        return view('anggaran.index', [
            'active' => 'anggaran',
            'period' => $period,
            'periodOptions' => AdBudgetPeriods::options(),
            'summaryCards' => $summaryCards,
            'limits' => $limits,
            'pending' => $pending,
            'groups' => $reports['groups'],
            'totalCount' => $reports['total_count'],
            'shownCount' => $reports['shown_count'],
        ]);
    }
}
