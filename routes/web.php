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
use App\Http\Controllers\ReportStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'effective_role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/profile/password', [ProfileController::class, 'edit'])->name('password.edit');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/impersonation', [ImpersonationController::class, 'store'])->name('impersonation.store');
    Route::delete('/impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/anggaran', [AdBudgetController::class, 'index'])->name('anggaran');
    Route::post('/anggaran/{report}/setujui', [AdBudgetActionController::class, 'approve'])->name('anggaran.setujui');
    Route::post('/anggaran/{report}/tolak', [AdBudgetActionController::class, 'reject'])->name('anggaran.tolak');
    Route::post('/anggaran/{report}/revisi', [AdBudgetActionController::class, 'revise'])->name('anggaran.revisi');
    Route::post('/anggaran/{report}/selesai', [AdBudgetActionController::class, 'complete'])->name('anggaran.selesai');

    Route::get('/konten', [ContentController::class, 'index'])->name('konten');
    Route::get('/pencapaian', [AchievementController::class, 'index'])->name('pencapaian');
    Route::get('/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan');
    Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas');

    Route::post('/laporan/{report}/verifikasi', [ReportStatusController::class, 'verify'])->name('reports.verifikasi');
    Route::post('/laporan/{report}/setujui', [ReportStatusController::class, 'approve'])->name('reports.setujui');
    Route::post('/laporan/{report}/tolak', [ReportStatusController::class, 'reject'])->name('reports.tolak');
    Route::post('/laporan/{report}/revisi', [ReportStatusController::class, 'revise'])->name('reports.revisi');

    Route::get('/halaman/{key}', [PlaceholderController::class, 'show'])
        ->whereIn('key', [
            'jadwal-koordinator', 'bdc-users', 'rekap', 'role', 'targets', 'users',
            'sumber-collab', 'jadwal-personalia',
        ])
        ->name('placeholder');
});
