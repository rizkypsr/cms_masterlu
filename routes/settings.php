<?php

use App\Http\Controllers\DepositController;
use App\Http\Controllers\Settings\DatabaseBackupController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/database-backup', [DatabaseBackupController::class, 'show'])
        ->name('database-backup.show');

    Route::get('settings/database-backup/download', [DatabaseBackupController::class, 'download'])
        ->middleware('throttle:6,1')
        ->name('database-backup.download');

    // Saldo Deposit — topup queue, balances, AI rates, monthly report
    Route::get('deposit', [DepositController::class, 'topups'])->name('deposit.topups');
    Route::post('deposit/topup/{depositTopup}/approve', [DepositController::class, 'approveTopup'])
        ->name('deposit.topup.approve');
    Route::post('deposit/topup/{depositTopup}/reject', [DepositController::class, 'rejectTopup'])
        ->name('deposit.topup.reject');

    Route::get('deposit/saldo', [DepositController::class, 'balances'])->name('deposit.saldo');
    Route::post('deposit/adjust', [DepositController::class, 'adjust'])->name('deposit.adjust');
    Route::post('deposit/adjust/bulk', [DepositController::class, 'bulkAdjust'])->name('deposit.adjust.bulk');

    Route::get('deposit/tarif', [DepositController::class, 'rates'])->name('deposit.tarif');
    Route::post('deposit/tarif', [DepositController::class, 'storeRate'])->name('deposit.tarif.store');

    Route::get('deposit/laporan', [DepositController::class, 'report'])->name('deposit.laporan');
    Route::post('deposit/free-quota', [DepositController::class, 'updateFreeQuota'])->name('deposit.free-quota');

    Route::get('deposit/pengguna/{pengguna}', [DepositController::class, 'user'])->name('deposit.user');
});
