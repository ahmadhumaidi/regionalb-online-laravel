<?php

namespace App\Http\Controllers;

use App\Models\RsmCollabDailyMetric;
use App\Models\RsmUser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ClosingTargetSimulationController extends Controller
{
    private const REPORT_NAME = 'Closing Kampus Regional';

    private const DEFAULT_CAMPUSES = ['Unas', 'UBY', 'UM Surabaya', 'STIE GICI Bogor', 'UICI'];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === RsmUser::ROLE_SUPER_USER, 403);

        $availableCampuses = RsmCollabDailyMetric::query()
            ->where('report_name', self::REPORT_NAME)
            ->whereNotNull('campus_name')
            ->where('campus_name', '!=', '')
            ->select('campus_name')
            ->selectRaw('MAX(regional) as regional')
            ->groupBy('campus_name')
            ->orderBy('campus_name')
            ->get()
            ->map(fn ($row): array => ['name' => (string) $row->campus_name, 'regional' => (string) ($row->regional ?? '')]);

        $availableNames = $availableCampuses->pluck('name')->all();
        $defaults = array_values(array_intersect(self::DEFAULT_CAMPUSES, $availableNames));
        if ($defaults === []) {
            $defaults = array_slice($availableNames, 0, 5);
        }

        $validated = $request->validate([
            'from_month' => ['nullable', 'date_format:Y-m'],
            'to_month' => ['nullable', 'date_format:Y-m'],
            'campuses' => ['nullable', 'array', 'max:20'],
            'campuses.*' => ['string', 'max:180'],
            'campuses_present' => ['nullable', 'boolean'],
            'target' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $fromMonth = (string) ($validated['from_month'] ?? '2025-09');
        $toMonth = (string) ($validated['to_month'] ?? '2026-09');
        $from = Carbon::createFromFormat('Y-m-d', $fromMonth.'-01')->startOfMonth();
        $to = Carbon::createFromFormat('Y-m-d', $toMonth.'-01')->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
            $fromMonth = $from->format('Y-m');
            $toMonth = $to->format('Y-m');
        }

        if ($from->diffInMonths($to) > 35) {
            $to = $from->copy()->addMonths(35)->endOfMonth();
            $toMonth = $to->format('Y-m');
        }

        $campusInput = $request->boolean('campuses_present') ? (array) ($validated['campuses'] ?? []) : $defaults;
        $requestedCampuses = array_values(array_unique(array_map('strval', $campusInput)));
        $selectedCampuses = array_values(array_intersect($requestedCampuses, $availableNames));
        $target = (int) ($validated['target'] ?? 1000);

        $months = [];
        for ($cursor = $from->copy()->startOfMonth(); $cursor->lte($to); $cursor->addMonth()) {
            $months[$cursor->format('Y-m')] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->locale('id')->translatedFormat('M Y'),
                'campuses' => array_fill_keys($selectedCampuses, 0.0),
                'achievement' => 0.0,
                'share' => 0.0,
                'target' => 0,
            ];
        }

        if ($selectedCampuses !== []) {
            $metrics = RsmCollabDailyMetric::query()
                ->where('report_name', self::REPORT_NAME)
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('campus_name', $selectedCampuses)
                ->get(['metric_date', 'campus_name', 'value']);

            foreach ($metrics as $metric) {
                $month = Carbon::parse($metric->metric_date)->format('Y-m');
                $campus = (string) $metric->campus_name;
                if (isset($months[$month]['campuses'][$campus])) {
                    $months[$month]['campuses'][$campus] += (float) $metric->value;
                    $months[$month]['achievement'] += (float) $metric->value;
                }
            }
        }

        $grandTotal = array_sum(array_column($months, 'achievement'));
        foreach ($months as &$month) {
            $month['share'] = $grandTotal > 0 ? ($month['achievement'] / $grandTotal) * 100 : 0;
        }
        unset($month);

        $months = $this->allocateTarget($months, $target, $grandTotal);
        $campusTotals = array_fill_keys($selectedCampuses, 0.0);
        foreach ($months as $month) {
            foreach ($month['campuses'] as $campus => $value) {
                $campusTotals[$campus] += $value;
            }
        }

        return view('closing-target-simulation.index', [
            'availableCampuses' => $availableCampuses,
            'selectedCampuses' => $selectedCampuses,
            'campusTotals' => $campusTotals,
            'months' => array_values($months),
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'target' => $target,
            'grandTotal' => $grandTotal,
            'peak' => collect($months)->sortByDesc('achievement')->first(),
        ]);
    }

    /** Largest-remainder allocation keeps rounded monthly targets equal to the requested total. */
    private function allocateTarget(array $months, int $target, float $grandTotal): array
    {
        if ($target === 0 || $grandTotal <= 0) {
            return $months;
        }

        $remainders = [];
        $allocated = 0;
        foreach ($months as $key => &$month) {
            $exact = $target * $month['achievement'] / $grandTotal;
            $month['target'] = (int) floor($exact);
            $allocated += $month['target'];
            $remainders[$key] = $exact - $month['target'];
        }
        unset($month);

        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $target - $allocated) as $key) {
            $months[$key]['target']++;
        }

        return $months;
    }
}
