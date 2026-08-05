<?php

namespace App\Providers;

use App\Models\RsmUser;
use App\Support\RsmRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-users-page', fn (RsmUser $user) => RsmRole::canViewUsersPage($user));
        Gate::define('manage-targets', fn (RsmUser $user) => RsmRole::canManageTargets($user));
        Gate::define('sync-collab', fn (RsmUser $user) => RsmRole::canSyncCollab($user));
        Gate::define('manage-ad-budget', fn (RsmUser $user) => RsmRole::canManageAdBudget($user));
        Gate::define('view-jadwal-koordinator', fn (RsmUser $user) => RsmRole::canViewJadwalKoordinator($user));
        Gate::define('manage-coordinator-schedule', fn (RsmUser $user) => RsmRole::canManageCoordinatorSchedule($user));
        Gate::define('impersonate', fn (RsmUser $user) => RsmRole::canImpersonate($user));

        foreach (['detail', 'setujui', 'tolak', 'revisi', 'export', 'verifikasi', 'export-wilayah', 'edit-draft', 'hapus-draft'] as $action) {
            Gate::define("report.{$action}", fn (RsmUser $user, ?string $effectiveRole = null) => RsmRole::canAction($effectiveRole ?? $user->role, $action));
        }
    }
}
