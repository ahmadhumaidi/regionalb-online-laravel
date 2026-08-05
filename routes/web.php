<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AdBudgetActionController;
use App\Http\Controllers\AdBudgetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AktivitasController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportFormController;
use App\Http\Controllers\ReportRecapController;
use App\Http\Controllers\ReportStatusController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\PersonnelScheduleController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\CoordinatorScheduleController;
use App\Http\Controllers\CollabSourceController;
use App\Http\Controllers\BdcUsersController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ClosingCampusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'effective_role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/profile/password', [ProfileController::class, 'edit'])->name('password.edit');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/impersonation', [ImpersonationController::class, 'store'])->name('impersonation.store');
    Route::delete('/impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/anggaran', [AdBudgetController::class, 'index'])->name('anggaran');
    Route::get('/anggaran/create', fn () => app(ReportFormController::class)->create('ads'))->name('anggaran.create');
    Route::post('/anggaran', fn (\Illuminate\Http\Request $request) => app(ReportFormController::class)->store($request, 'ads'))->name('anggaran.store');
    Route::post('/anggaran/{report}/setujui', [AdBudgetActionController::class, 'approve'])->name('anggaran.setujui');
    Route::post('/anggaran/{report}/tolak', [AdBudgetActionController::class, 'reject'])->name('anggaran.tolak');
    Route::post('/anggaran/{report}/revisi', [AdBudgetActionController::class, 'revise'])->name('anggaran.revisi');
    Route::post('/anggaran/{report}/selesai', [AdBudgetActionController::class, 'complete'])->name('anggaran.selesai');

    Route::get('/konten', [ContentController::class, 'index'])->name('konten');
    Route::post('/konten/accounts', [ContentController::class, 'storeAccount'])->name('konten.accounts.store');
    Route::post('/konten/posts', [ContentController::class, 'storePost'])->name('konten.posts.store');
    Route::get('/pencapaian', [AchievementController::class, 'index'])->name('pencapaian');
    Route::get('/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan');
    Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas');
    Route::get('/rekap', [ReportRecapController::class, 'index'])->name('rekap');
    Route::get('/rekap/export', [ReportRecapController::class, 'export'])->name('rekap.export');
    Route::get('/targets', [TargetController::class, 'index'])->name('targets');
    Route::post('/targets', [TargetController::class, 'store'])->name('targets.store');
    Route::get('/jadwal-personalia', [PersonnelScheduleController::class, 'index'])->name('jadwal-personalia');
    Route::post('/jadwal-personalia/sync', [PersonnelScheduleController::class, 'sync'])->name('jadwal-personalia.sync');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('/users/{managedUser}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{managedUser}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{managedUser}/toggle', [UserManagementController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{managedUser}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::get('/jadwal-koordinator', [CoordinatorScheduleController::class, 'index'])->name('jadwal-koordinator');
    Route::post('/jadwal-koordinator', [CoordinatorScheduleController::class, 'store'])->name('jadwal-koordinator.store');
    Route::post('/jadwal-koordinator/generate', [CoordinatorScheduleController::class, 'generate'])->name('jadwal-koordinator.generate');
    Route::post('/jadwal-koordinator/whatsapp', [CoordinatorScheduleController::class, 'whatsapp'])->name('jadwal-koordinator.whatsapp');
    Route::patch('/jadwal-koordinator/{schedule}', [CoordinatorScheduleController::class, 'update'])->name('jadwal-koordinator.update');
    Route::get('/jadwal-koordinator/{schedule}/attachment', [CoordinatorScheduleController::class, 'attachment'])->name('jadwal-koordinator.attachment');
    Route::delete('/jadwal-koordinator/{schedule}', [CoordinatorScheduleController::class, 'destroy'])->name('jadwal-koordinator.destroy');
    Route::get('/sumber-collab', [CollabSourceController::class, 'index'])->name('sumber-collab');
    Route::post('/sumber-collab/sync', [CollabSourceController::class, 'sync'])->name('sumber-collab.sync');
    Route::get('/bdc-users', [BdcUsersController::class, 'index'])->name('bdc-users');
    Route::post('/bdc-users/refresh', [BdcUsersController::class, 'refresh'])->name('bdc-users.refresh');
    Route::get('/role', [RoleController::class, 'index'])->name('role');
    Route::get('/closing-kampus', [ClosingCampusController::class, 'index'])->name('closing-kampus');
    Route::get('/kegiatan/create', fn () => app(ReportFormController::class)->create('marketing'))->name('kegiatan.create');
    Route::post('/kegiatan', fn (\Illuminate\Http\Request $request) => app(ReportFormController::class)->store($request, 'marketing'))->name('kegiatan.store');
    Route::get('/aktivitas/create', fn () => app(ReportFormController::class)->create('other'))->name('aktivitas.create');
    Route::post('/aktivitas', fn (\Illuminate\Http\Request $request) => app(ReportFormController::class)->store($request, 'other'))->name('aktivitas.store');

    Route::get('/laporan/{report}', [ReportFormController::class, 'show'])->name('reports.show');
    Route::get('/laporan/{report}/edit', [ReportFormController::class, 'edit'])->name('reports.edit');
    Route::patch('/laporan/{report}', [ReportFormController::class, 'update'])->name('reports.update');
    Route::delete('/laporan/{report}', [ReportFormController::class, 'destroy'])->name('reports.destroy');
    Route::get('/laporan/{report}/lampiran', [ReportFormController::class, 'attachment'])->name('reports.attachment');

    Route::post('/laporan/{report}/verifikasi', [ReportStatusController::class, 'verify'])->name('reports.verifikasi');
    Route::post('/laporan/{report}/setujui', [ReportStatusController::class, 'approve'])->name('reports.setujui');
    Route::post('/laporan/{report}/tolak', [ReportStatusController::class, 'reject'])->name('reports.tolak');
    Route::post('/laporan/{report}/revisi', [ReportStatusController::class, 'revise'])->name('reports.revisi');

    Route::get('/halaman/{key}', [PlaceholderController::class, 'show'])
        ->whereIn('key', [
            'jadwal-koordinator', 'bdc-users', 'role', 'users',
            'sumber-collab',
        ])
        ->name('placeholder');
});
