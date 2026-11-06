<?php

namespace App\Console\Commands;

use App\Models\RsmUser;
use App\Services\CoordinatorScheduleGenerator;
use App\Support\AreaRegionals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GenerateCoordinatorSchedules extends Command
{
    protected $signature = 'rsm:generate-coordinator-schedules {--month= : Bulan YYYY-MM, default bulan berjalan WIB} {--dry-run : Periksa cakupan tanpa membuat jadwal}';

    protected $description = 'Generate jadwal bulanan koordinator aktif untuk seluruh area';

    public function handle(CoordinatorScheduleGenerator $generator): int
    {
        $month = $this->option('month') ?? now('Asia/Jakarta')->format('Y-m');
        if (Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->fails()) {
            $this->error('Bulan harus dalam format YYYY-MM yang valid.');

            return self::FAILURE;
        }

        $profilesByArea = [];
        $homes = ['Regional 4' => 'Makassar', 'Regional 5' => 'Semarang', 'Regional 6' => 'Surabaya', 'Regional 7' => 'Banyuwangi'];
        foreach (RsmUser::where('role', RsmUser::ROLE_KOORDINATOR)->where('is_active', true)
            ->whereIn('area', ['Regional A', 'Regional B'])->orderBy('id')->get() as $coordinator) {
            if (! in_array($coordinator->regional, AreaRegionals::forArea($coordinator->area), true)) {
                continue;
            }
            $profilesByArea[$coordinator->area][$coordinator->regional] = [
                'id' => $coordinator->id, 'name' => $coordinator->name,
                'area' => $coordinator->area, 'home' => $homes[$coordinator->regional] ?? '',
            ];
        }

        if ($this->option('dry-run')) {
            $this->info('Bulan '.$month.': '.array_sum(array_map('count', $profilesByArea)).' koordinator aktif. Tidak ada data diubah.');

            return self::SUCCESS;
        }

        $count = DB::transaction(function () use ($generator, $month, $profilesByArea) {
            $count = 0;
            foreach ($profilesByArea as $profiles) {
                $count += $generator->generate($month, $profiles);
            }

            return $count;
        });
        $this->info("Generate {$month} selesai: {$count} agenda baru.");

        return self::SUCCESS;
    }
}
