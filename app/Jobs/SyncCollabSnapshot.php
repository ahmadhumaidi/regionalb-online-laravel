<?php

namespace App\Jobs;

use App\Services\CollabSourceService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCollabSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function handle(): void
    {
        CollabSourceService::sync();
    }
}
