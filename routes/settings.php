<?php

use App\Http\Controllers\Settings\DatabaseBackupController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SubscriptionController;
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

    // Subscription / Donor Plans
    Route::get('settings/subscription', [SubscriptionController::class, 'show'])
        ->name('subscription.show');
    Route::post('settings/subscription/plan', [SubscriptionController::class, 'storePlan'])
        ->name('subscription.plan.store');
    Route::put('settings/subscription/plan/{plan}', [SubscriptionController::class, 'updatePlan'])
        ->name('subscription.plan.update');
    Route::post('settings/subscription/plan/{plan}/toggle', [SubscriptionController::class, 'togglePlan'])
        ->name('subscription.plan.toggle');
    Route::post('settings/subscription/user/bulk-assign', [SubscriptionController::class, 'bulkAssignUsers'])
        ->name('subscription.user.bulk-assign');
    Route::post('settings/subscription/user/{pengguna}/assign', [SubscriptionController::class, 'assignUser'])
        ->name('subscription.user.assign');
    Route::post('settings/subscription/user/{pengguna}/extend', [SubscriptionController::class, 'extendUser'])
        ->name('subscription.user.extend');
    Route::delete('settings/subscription/user/{pengguna}', [SubscriptionController::class, 'revokeUser'])
        ->name('subscription.user.revoke');
});
