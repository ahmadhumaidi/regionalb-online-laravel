<?php

namespace App\Services;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CoordinatorScheduleGenerator
{
    public function generate(string $month, array $profiles, ?RsmUser $actor = null): int
    {
        $campusesByRegional = DB::table('partner_campuses')
            ->whereNotNull('wilayah')
            ->orderByRaw('COALESCE(display_name, name)')
            ->get(['wilayah', DB::raw('COALESCE(display_name, name) as label')])
            ->groupBy('wilayah')
            ->map(fn ($rows) => $rows->pluck('label')->unique()->values()->all());

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $end = $start->copy()->endOfMonth();
        $count = 0;
        $campusIndex = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $weekday = $day->dayOfWeekIso;
            foreach ($profiles as $regional => $profile) {
                $isWeeklyReview = $weekday === 5 || $weekday >= 6;
                if ($isWeeklyReview) {
                    $unit = "Rekap dan laporan mingguan {$regional}";
                    $visitType = 'Zoom';
                    $agenda = $weekday >= 6
                        ? 'Follow up weekend, evaluasi leads, dan persiapan kunjungan pekan berikutnya'
                        : 'Rekap mingguan dan follow up kendala kampus';
                } else {
                    $campuses = $campusesByRegional[$regional] ?? [];
                    $index = $campusIndex[$regional] ?? 0;
                    $unit = $campuses !== [] ? $campuses[$index % count($campuses)] : "Kampus prioritas {$regional}";
                    $campusIndex[$regional] = $index + 1;
                    $visitType = $this->scheduleVisitType($regional, $unit);
                    $agenda = 'Monitoring PMB, leads, iklan, dan action plan kampus';
                }

                // Keep manual edits and completed reports when a month is generated again.
                if (RsmCoordinatorSchedule::where('area', $profile['area'])
                    ->whereDate('schedule_date', $day->toDateString())
                    ->where(function ($query) use ($profile) {
                        $query->where('koordinator_user_id', $profile['id'])
                            ->orWhere('koordinator_name', $profile['name']);
                    })->exists()) {
                    continue;
                }

                $created = RsmCoordinatorSchedule::firstOrCreate(
                    [
                        'area' => $profile['area'],
                        'schedule_date' => $day->toDateString(),
                        'wilayah' => $regional,
                        'unit_name' => $unit,
                        'koordinator_name' => $profile['name'],
                    ],
                    [
                        'koordinator_user_id' => $profile['id'],
                        'koordinator_home' => $profile['home'],
                        'visit_type' => $visitType,
                        'status' => 'Rencana',
                        'agenda' => $agenda,
                        'created_by_user_id' => $actor?->id,
                        'created_by_name' => $actor?->name ?? 'Scheduler otomatis',
                    ]
                );
                $count += $created->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $count;
    }

    /** Port of rsm_schedule_visit_type() (rsm_db.php:3030-3045). */
    private function scheduleVisitType(string $regional, string $unitName): string
    {
        $name = mb_strtolower($unitName);
        $patterns = [
            'Regional 5' => '/semarang|yogyakarta|ivet|lia|unaki|undaris|uby|ima/',
            'Regional 6' => '/surabaya|gresik|stiesia|um |uwk|uwika|unigres|ikip/',
            'Regional 7' => '/banyuwangi|jember|bali|stikom|unibabwi|ubhinus|polnas/',
            'Regional 4' => '/makassar|sulawesi|uts|patria/',
        ];

        if (isset($patterns[$regional]) && preg_match($patterns[$regional], $name)) {
            return 'Visit';
        }

        return 'Zoom';
    }

}
