<?php

use App\Http\Controllers\AdBudgetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProfileController;
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

    Route::get('/halaman/{key}', [PlaceholderController::class, 'show'])
        ->whereIn('key', [
            'pencapaian', 'jadwal-koordinator', 'bdc-users', 'konten', 'kegiatan',
            'aktivitas', 'rekap', 'role', 'targets', 'users',
            'sumber-collab', 'jadwal-personalia',
        ])
        ->name('placeholder');
});
