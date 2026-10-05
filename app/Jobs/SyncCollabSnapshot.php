<?php

namespace App\Jobs;

use App\Services\CollabSourceService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCollabSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $reportName = null)
    {
    }

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return $this->reportName ?: 'all';
    }

    public function handle(): void
    {
        if ($this->reportName === 'Absen Staff') {
            CollabSourceService::syncAttendance();

            return;
        }

        CollabSourceService::sync(null, $this->reportName);
    }
}
