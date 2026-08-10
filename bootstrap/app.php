<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Production cron runs sync_collab_cache.php every 30 minutes; match that cadence here.
        // Only touch the last N days (config: services.collab.sync_window_days) so
        // closed days aren't rewritten every 30 minutes - they're effectively
        // permanent once outside the window. The daily full sync below is the
        // once-a-day safety net that catches any late corrections beyond that window.
        $collabWindowDays = (int) config('services.collab.sync_window_days', 2);
        $schedule->command("rsm:sync-sources --only=collab --window={$collabWindowDays}")->everyThirtyMinutes()->withoutOverlapping();
        // BdcReportUsersService's own cache TTL is 15 minutes; refresh a bit
        // faster than that so a Dashboard load practically never needs to
        // fall back to a live (and possibly slow/timed-out) api.p2k.co.id call.
        $schedule->command('rsm:sync-sources --only=bdc')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('rsm:sync-sources')->dailyAt('02:15')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'effective_role' => \App\Http\Middleware\SetEffectiveRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
