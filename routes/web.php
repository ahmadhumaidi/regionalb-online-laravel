<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'effective_role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/impersonation', [ImpersonationController::class, 'store'])->name('impersonation.store');
    Route::delete('/impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');

    // Placeholder landing route until Fase 3 Batch A ports the real
    // dashboard page (reads, KPIs, gamification summary, etc.).
    Route::get('/', function () {
        return view('dashboard.placeholder');
    })->name('dashboard');
});
