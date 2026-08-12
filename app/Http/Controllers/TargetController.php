<?php

namespace App\Http\Controllers;

use App\Models\RsmAdBudgetLimit;
use App\Models\RsmMonthlyTarget;
use App\Models\RsmUser;
use App\Services\AdBudget\AdBudgetPeriods;
use App\Support\AreaRegionals;
use App\Support\RsmRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TargetController extends Controller
{
    private const TARGETABLE_ROLES = ['staff', 'koordinator'];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageTargets($user), 403);
        $area = $user->area ?: 'Regional B';
        $references = $this->references($area);
        $targets = RsmMonthlyTarget::query()->where('area', $area)->where('scope_type', 'staff')->orderByDesc('target_month')->orderBy('wilayah')->orderBy('unit_name')->orderBy('staff_name')->limit(80)->get();
        $indicators = $this->indicators();

        return view('targets.index', compact('area', 'references', 'targets', 'indicators'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(RsmRole::canManageTargets($user), 403);
        $rules = [
            'target_month' => ['required', 'date_format:Y-m'],
            'scope_type' => ['required', Rule::in(['regional', 'wilayah', 'unit', 'staff'])],
            'wilayah' => ['nullable', 'string', 'max:120'], 'unit_name' => ['nullable', 'string', 'max:160'], 'staff_name' => ['nullable', 'string', 'max:160'],
            'target_leads' => ['nullable', 'integer', 'min:0'], 'target_follow_up' => ['nullable', 'integer', 'min:0'], 'target_registrasi' => ['nullable', 'integer', 'min:0'], 'target_herregistrasi' => ['nullable', 'integer', 'min:0'], 'target_anggaran' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (array_keys($this->indicators()) as $key) {
            $rules["indicator_targets.$key.target"] = ['nullable', 'numeric', 'min:0'];
            $rules["indicator_targets.$key.weight"] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }
        $data = $request->validate($rules);
        $baseIndicatorTargets = $this->indicatorTargets($data);
        $this->assertTotalWeightIsOneHundred($baseIndicatorTargets);
        $all = $request->boolean('apply_all_scope'); $area = $user->area ?: 'Regional B'; $scope = $data['scope_type'];
        $wilayah = $scope === 'regional' ? '' : trim((string) ($data['wilayah'] ?? '')); $unit = in_array($scope, ['unit', 'staff'], true) ? trim((string) ($data['unit_name'] ?? '')) : ''; $staff = $scope === 'staff' ? trim((string) ($data['staff_name'] ?? '')) : '';
        if (! $all && $scope === 'wilayah' && $wilayah === '') return back()->withErrors(['wilayah' => 'Wilayah wajib dipilih.'])->withInput();
        if (! $all && $scope === 'unit' && $unit === '') return back()->withErrors(['unit_name' => 'Unit/Kampus wajib dipilih.'])->withInput();
        if (! $all && $scope === 'staff' && $staff === '') return back()->withErrors(['staff_name' => 'Staff wajib dipilih.'])->withInput();
        if ($scope === 'staff' && $staff !== '') {
            $selected = RsmUser::query()->where('area', $area)->whereIn('role', self::TARGETABLE_ROLES)->where('is_active', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($staff)])->first();
            if ($selected) { $wilayah = $selected->regional ?: $wilayah; $unit = $selected->campus_name ?: $unit; $staff = $selected->name; }
        }
        $items = $all && $scope !== 'regional' ? $this->bulkItems($area, $scope) : [['wilayah' => $wilayah, 'unit_name' => $unit, 'staff_name' => $staff]];
        if ($items === []) return back()->withErrors(['scope_type' => 'Tidak ada data pada scope yang dipilih.'])->withInput();
        foreach ($items as $item) {
            $indicatorTargets = $this->withAdBudgetTargets($baseIndicatorTargets, $area, $data['target_month'], $item, $scope);
            $key = $scope === 'staff' ? 'staff:' . mb_strtolower(trim($item['staff_name'])) : ($scope === 'unit' ? 'unit:' . mb_strtolower(trim($item['unit_name'])) : ($scope === 'wilayah' ? 'wilayah:' . mb_strtolower(trim($item['wilayah'])) : 'regional'));
            RsmMonthlyTarget::updateOrCreate(['area' => $area, 'target_month' => $data['target_month'], 'scope_key' => $key], ['scope_type' => $scope, 'wilayah' => $item['wilayah'] ?: null, 'unit_name' => $item['unit_name'] ?: null, 'staff_name' => $item['staff_name'] ?: null, 'target_leads' => 0, 'target_follow_up' => (int) ($indicatorTargets['fu']['target'] ?? 0), 'target_registrasi' => (int) ($indicatorTargets['reg']['target'] ?? 0), 'target_herregistrasi' => (int) ($indicatorTargets['herreg']['target'] ?? 0), 'target_anggaran' => 0, 'indicator_targets' => $indicatorTargets, 'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'created_by_user_id' => $user->id, 'created_by_name' => $user->name]);
        }
        return redirect()->route('scoring.targets')->with('status', 'Target bulanan berhasil disimpan.');
    }

    private function indicatorTargets(array $data): array
    {
        $submitted = is_array($data['indicator_targets'] ?? null) ? $data['indicator_targets'] : [];
        $legacyTargets = [
            'reg' => $data['target_registrasi'] ?? null,
            'herreg' => $data['target_herregistrasi'] ?? null,
            'fu' => $data['target_follow_up'] ?? null,
        ];

        $targets = [];
        foreach ($this->indicators() as $key => $meta) {
            $row = is_array($submitted[$key] ?? null) ? $submitted[$key] : [];
            $target = $row['target'] ?? $legacyTargets[$key] ?? $meta['default_target'] ?? 0;
            $weight = $row['weight'] ?? $meta['default_weight'];
            $targets[$key] = [
                'label' => $meta['label'],
                'group' => $meta['group'],
                'target' => (float) $target,
                'weight' => (float) $weight,
            ];
        }

        return $targets;
    }

    private function assertTotalWeightIsOneHundred(array $indicatorTargets): void
    {
        $totalWeight = collect($indicatorTargets)->sum(fn (array $row): float => (float) ($row['weight'] ?? 0));
        if (abs($totalWeight - 100.0) <= 0.01) {
            return;
        }

        throw ValidationException::withMessages([
            'indicator_targets' => 'Total bobot indikator harus tepat 100%. Saat ini: '.number_format($totalWeight, 2, ',', '.').'%.',
        ]);
    }

    private function withAdBudgetTargets(array $indicatorTargets, string $area, string $targetMonth, array $item, string $scope): array
    {
        $adBudgetTarget = $this->adBudgetTarget($area, $targetMonth, $item, $scope);

        if ($adBudgetTarget <= 0) {
            foreach (['cpm_cpl', 'closing_iklan'] as $key) {
                if (array_key_exists($key, $indicatorTargets)) {
                    $indicatorTargets[$key]['target'] = 0.0;
                }
            }
        }

        return $indicatorTargets;
    }

    private function adBudgetTarget(string $area, string $targetMonth, array $item, string $scope): float
    {
        if (! Schema::hasTable('rsm_ad_budget_limits')) {
            return 0.0;
        }

        $period = AdBudgetPeriods::default(Carbon::createFromFormat('Y-m-d', $targetMonth.'-01')->toDateString());
        $query = RsmAdBudgetLimit::query()->where('area', $area)->where('ad_period', $period);
        $wilayah = trim((string) ($item['wilayah'] ?? ''));
        $unitName = trim((string) ($item['unit_name'] ?? ''));

        if ($scope !== 'regional' && $wilayah !== '') {
            $query->where('wilayah', $wilayah);
        }
        if (Schema::hasColumn('rsm_ad_budget_limits', 'unit_name') && $scope !== 'regional' && $unitName !== '') {
            $query->where('unit_name', $unitName);
        }

        return (float) $query->sum('budget_limit');
    }

    private function indicators(): array
    {
        return (array) config('scoring_indicators.indicators', []);
    }

    private function references(string $area): array
    {
        $staff = RsmUser::query()->where('area', $area)->whereIn('role', self::TARGETABLE_ROLES)->where('is_active', true)->orderBy('regional')->orderBy('campus_name')->orderBy('name')->get(['name', 'regional', 'campus_name']);
        return ['regionals' => AreaRegionals::forArea($area), 'campuses' => $staff->pluck('campus_name')->filter()->unique()->values(), 'staff' => $staff];
    }

    private function bulkItems(string $area, string $scope): array
    {
        if ($scope === 'wilayah') return array_map(fn ($value) => ['wilayah' => $value, 'unit_name' => '', 'staff_name' => ''], AreaRegionals::forArea($area));
        $staff = RsmUser::query()->where('area', $area)->whereIn('role', self::TARGETABLE_ROLES)->where('is_active', true)->get(['name', 'regional', 'campus_name']);
        if ($scope === 'staff') return $staff->map(fn ($row) => ['wilayah' => $row->regional ?: '', 'unit_name' => $row->campus_name ?: '', 'staff_name' => $row->name])->all();
        return $staff->filter(fn ($row) => trim((string) $row->campus_name) !== '')->unique(fn ($row) => mb_strtolower($row->campus_name))->map(fn ($row) => ['wilayah' => $row->regional ?: '', 'unit_name' => $row->campus_name, 'staff_name' => ''])->values()->all();
    }
}
