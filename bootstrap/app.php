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
        // Refresh live Collab data every 30 minutes.
        // Only touch the last N days (config: services.collab.sync_window_days) so
        // closed days aren't rewritten every 30 minutes - they're effectively
        // permanent once outside the window. The daily full sync below is the
        // once-a-day safety net that catches any late corrections beyond that window.
        $collabWindowDays = (int) config('services.collab.sync_window_days', 2);
        $schedule->command("rsm:sync-sources --only=collab --window={$collabWindowDays}")->everyThirtyMinutes()->withoutOverlapping(29);
        // GGKlik attendance is intentionally fetched only at operational
        // checkpoints so its slower authenticated endpoint stays lightweight.
        foreach (['09:05', '12:00', '17:05', '23:59'] as $attendanceTime) {
            $schedule->command('rsm:sync-sources --only=attendance')
                ->dailyAt($attendanceTime)
                ->timezone('Asia/Jakarta')
                ->withoutOverlapping(60);
        }
        // BdcReportUsersService's own cache TTL is 15 minutes; refresh a bit
        // faster than that so a Dashboard load practically never needs to
        // fall back to a live (and possibly slow/timed-out) api.p2k.co.id call.
        $schedule->command('rsm:sync-sources --only=bdc')->everyTenMinutes()->withoutOverlapping(9);
        // Refresh Personalia shortly after midnight Jakarta time, while
        // retaining a separate daily full-history sync for Collab. BDC is
        // already refreshed every ten minutes and needs no duplicate daily run.
        $schedule->command('rsm:sync-sources --only=personalia')
            ->dailyAt('00:15')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(60);
        $schedule->command('rsm:sync-sources --only=collab')
            ->dailyAt('02:15')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(180);
        // Manual sync requests from the Collab page are queued so the web
        // request returns immediately instead of timing out while cb.web.id
        // is serving its reports.
        $schedule->command('queue:work --stop-when-empty --queue=default --timeout=1000 --tries=1')
            ->everyMinute()->withoutOverlapping(20);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'effective_role' => \App\Http\Middleware\SetEffectiveRole::class,
        ]);

        // Meta's WhatsApp Cloud API webhook posts plain JSON with no Laravel session/CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
