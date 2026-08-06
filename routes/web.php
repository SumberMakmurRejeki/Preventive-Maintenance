<?php

use App\Http\Controllers\Auth\ForbiddenController;
use App\Http\Controllers\Auth\GuestLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MachineAccessController;
use App\Http\Controllers\Auth\QrRedirectController;
use App\Http\Controllers\Breakdown\BreakdownCloseController;
use App\Http\Controllers\Breakdown\BreakdownInputController;
use App\Http\Controllers\Breakdown\BreakdownReviewController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Master\MasterLokasiController;
use App\Http\Controllers\Master\MasterMesinController;
use App\Http\Controllers\Master\MasterPmChecksheetController;
use App\Http\Controllers\Notification\AdminNotificationController;
use App\Http\Controllers\Notification\AdminPushSubscriptionController;
use App\Http\Controllers\PM\MachineLandingController;
use App\Http\Controllers\PM\PmExecutorController;
use App\Http\Controllers\PM\PmReviewController;
use App\Http\Controllers\Report\ReportBreakdownController;
use App\Http\Controllers\Report\ReportPmController;
use App\Http\Controllers\Settings\UserSettingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('prime.guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/guest-login', [GuestLoginController::class, 'store'])->name('guest-login.store');
});

Route::get('/qr/{qrToken}', QrRedirectController::class)->name('qr.redirect');
Route::view('/machine-not-found', 'pages.machine.not-found')->name('machine-not-found');
Route::get('/403', ForbiddenController::class)->name('forbidden');

Route::middleware(['prime.auth', 'prime.operator.session'])->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/dashboard', DashboardController::class)
        ->middleware('prime.role:admin,guest')
        ->name('dashboard');
    Route::get('/machine-access', [MachineAccessController::class, 'create'])
        ->name('machine-access')
        ->middleware('prime.role:operator');
    Route::post('/machine-access', [MachineAccessController::class, 'store'])
        ->name('machine-access.store')
        ->middleware('prime.role:operator');
    Route::get('/machines/{machine}', [MachineLandingController::class, 'show'])
        ->middleware('prime.role:admin,operator')
        ->name('machines.show');
    Route::get('/pm/executor/{machine}', [PmExecutorController::class, 'show'])
        ->middleware('prime.role:operator')
        ->name('pm-executor.show');
    Route::post('/pm/executor/{machine}/start', [PmExecutorController::class, 'start'])
        ->middleware('prime.role:operator')
        ->name('pm-executor.start');
    Route::post('/pm/executor/{machine}/submit', [PmExecutorController::class, 'submit'])
        ->middleware('prime.role:operator')
        ->name('pm-executor.submit');
    Route::post('/pm/executor/{machine}/media/upload', [PmExecutorController::class, 'uploadMedia'])
        ->middleware('prime.role:operator')
        ->name('pm-executor.media.upload');
    Route::delete('/pm/executor/{machine}/media/{media}', [PmExecutorController::class, 'deleteMedia'])
        ->middleware('prime.role:operator')
        ->name('pm-executor.media.destroy');
    Route::get('/breakdown/input/{machine}', [BreakdownInputController::class, 'create'])
        ->middleware('prime.role:admin,operator')
        ->name('machines.breakdown-input');
    Route::get('/breakdown/input', [BreakdownInputController::class, 'index'])
        ->middleware('prime.role:admin,operator')
        ->name('breakdown-input.index');
    Route::post('/breakdown/input/{machine}', [BreakdownInputController::class, 'store'])
        ->middleware('prime.role:admin,operator')
        ->name('breakdown-input.store');
    Route::post('/breakdown/media/upload', [BreakdownInputController::class, 'uploadMedia'])
        ->middleware('prime.role:admin,operator')
        ->name('breakdown-media.upload');
    Route::delete('/breakdown/media/{mediaId}', [BreakdownInputController::class, 'deleteMedia'])
        ->middleware('prime.role:admin,operator')
        ->name('breakdown-media.destroy');
    Route::get('/breakdown/{breakdown}/close', [BreakdownCloseController::class, 'show'])
        ->middleware('prime.role:admin,operator')
        ->name('machines.breakdown-close');
    Route::patch('/breakdown/{breakdown}/close', [BreakdownCloseController::class, 'update'])
        ->middleware('prime.role:admin,operator')
        ->name('machines.breakdown-close.update');
    Route::middleware('prime.role:admin')->prefix('/breakdown/review')->group(function (): void {
        Route::get('/', [BreakdownReviewController::class, 'index'])->name('breakdown-review.index');
        Route::get('/{id}', [BreakdownReviewController::class, 'show'])->name('breakdown-review.show');
        Route::get('/{id}/edit', [BreakdownReviewController::class, 'edit'])->name('breakdown-review.edit');
        Route::put('/{id}', [BreakdownReviewController::class, 'update'])->name('breakdown-review.update');
        Route::delete('/{id}', [BreakdownReviewController::class, 'destroy'])->name('breakdown-review.destroy');
    });

    Route::middleware('prime.role:admin')->prefix('/pm/master-lokasi')->group(function (): void {
        Route::get('/', [MasterLokasiController::class, 'index'])->name('master-lokasi.index');
        Route::post('/', [MasterLokasiController::class, 'store'])->name('master-lokasi.store');
        Route::put('/{location}', [MasterLokasiController::class, 'update'])->name('master-lokasi.update');
        Route::patch('/{location}/nonaktifkan', [MasterLokasiController::class, 'deactivate'])->name('master-lokasi.deactivate');
        Route::patch('/{location}/aktifkan', [MasterLokasiController::class, 'activate'])->name('master-lokasi.activate');
        Route::delete('/{location}', [MasterLokasiController::class, 'destroy'])->name('master-lokasi.destroy');
    });

    Route::middleware('prime.role:admin')->prefix('/pm/master-mesin')->group(function (): void {
        Route::get('/', [MasterMesinController::class, 'index'])->name('master-mesin.index');
        Route::post('/', [MasterMesinController::class, 'store'])->name('master-mesin.store');
        Route::put('/{machineId}', [MasterMesinController::class, 'update'])->name('master-mesin.update');
        Route::patch('/{machineId}/nonaktifkan', [MasterMesinController::class, 'deactivate'])->name('master-mesin.deactivate');
        Route::patch('/{machineId}/aktifkan', [MasterMesinController::class, 'activate'])->name('master-mesin.activate');
        Route::delete('/{machineId}', [MasterMesinController::class, 'destroy'])->name('master-mesin.destroy');
        Route::post('/{machineId}/generate-qr', [MasterMesinController::class, 'generateQr'])->name('master-mesin.generate-qr');
    });

    Route::middleware('prime.role:admin')->prefix('/pm/master-checksheet')->group(function (): void {
        Route::get('/', [MasterPmChecksheetController::class, 'index'])->name('master-checksheet.index');
        Route::get('/create', [MasterPmChecksheetController::class, 'create'])->name('master-checksheet.create');
        Route::post('/', [MasterPmChecksheetController::class, 'store'])->name('master-checksheet.store');
        Route::post('/{id}/reconcile-schedule-dates', [MasterPmChecksheetController::class, 'reconcileScheduleDates'])->name('master-checksheet.reconcile-schedule-dates');
        Route::get('/{id}', [MasterPmChecksheetController::class, 'show'])->name('master-checksheet.show');
        Route::get('/{id}/edit', [MasterPmChecksheetController::class, 'edit'])->name('master-checksheet.edit');
        Route::put('/{id}', [MasterPmChecksheetController::class, 'update'])->name('master-checksheet.update');
        Route::patch('/{id}/nonaktifkan', [MasterPmChecksheetController::class, 'deactivate'])->name('master-checksheet.deactivate');
        Route::patch('/{id}/aktifkan', [MasterPmChecksheetController::class, 'activate'])->name('master-checksheet.activate');
        Route::delete('/{id}', [MasterPmChecksheetController::class, 'destroy'])->name('master-checksheet.destroy');
    });
    Route::middleware('prime.role:admin')->prefix('/pm/review')->group(function (): void {
        Route::get('/', [PmReviewController::class, 'index'])->name('pm-review.index');
        Route::get('/{executionId}', [PmReviewController::class, 'show'])->name('pm-review.show');
        Route::get('/{executionId}/edit', [PmReviewController::class, 'edit'])->name('pm-review.edit');
        Route::put('/{executionId}', [PmReviewController::class, 'update'])->name('pm-review.update');
        Route::patch('/{executionId}/approve', [PmReviewController::class, 'approve'])->name('pm-review.approve');
        Route::delete('/{executionId}', [PmReviewController::class, 'destroy'])->name('pm-review.destroy');
    });

    Route::middleware('prime.role:admin')->prefix('/admin/notifications')->group(function (): void {
        Route::get('/', [AdminNotificationController::class, 'index'])->name('admin-notifications.index');
        Route::post('/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('admin-notifications.read-all');
        Route::post('/{notificationId}/read', [AdminNotificationController::class, 'markAsRead'])->name('admin-notifications.read');
        Route::get('/{notificationId}/go', [AdminNotificationController::class, 'go'])->name('admin-notifications.go');
    });

    Route::middleware('prime.role:admin')->prefix('/admin/push-subscriptions')->group(function (): void {
        Route::post('/', [AdminPushSubscriptionController::class, 'store'])->name('admin-push-subscriptions.store');
        Route::delete('/', [AdminPushSubscriptionController::class, 'destroy'])->name('admin-push-subscriptions.destroy');
    });

    Route::middleware('prime.role:admin')->prefix('/report')->group(function (): void {
        Route::get('/pm', [ReportPmController::class, 'index'])->name('report-pm.index');
        Route::post('/pm/export/pdf', [ReportPmController::class, 'exportPdf'])->name('report-pm.export-pdf');
        Route::post('/pm/export/excel', [ReportPmController::class, 'exportExcel'])->name('report-pm.export-excel');
        Route::get('/exports/{reportExport}/download', [ReportPmController::class, 'download'])->name('report-exports.download');
        Route::get('/breakdown', [ReportBreakdownController::class, 'index'])->name('report-breakdown.index');
        Route::post('/breakdown/export/pdf', [ReportBreakdownController::class, 'exportPdf'])->name('report-breakdown.export-pdf');
        Route::post('/breakdown/export/excel', [ReportBreakdownController::class, 'exportExcel'])->name('report-breakdown.export-excel');
        Route::get('/breakdown/exports/{reportExport}/download', [ReportBreakdownController::class, 'download'])->name('report-breakdown.download');
    });

    Route::middleware('prime.role:admin')->prefix('/settings/users')->group(function (): void {
        Route::get('/', [UserSettingController::class, 'index'])->name('settings-users.index');
        Route::post('/', [UserSettingController::class, 'store'])->name('settings-users.store');
        Route::put('/{userId}', [UserSettingController::class, 'update'])->name('settings-users.update');
        Route::patch('/{userId}/reset-password', [UserSettingController::class, 'resetPassword'])->name('settings-users.reset-password');
        Route::patch('/{userId}/nonaktifkan', [UserSettingController::class, 'deactivate'])->name('settings-users.deactivate');
        Route::patch('/{userId}/aktifkan', [UserSettingController::class, 'activate'])->name('settings-users.activate');
        Route::delete('/{userId}', [UserSettingController::class, 'destroy'])->name('settings-users.destroy');
    });

    Route::middleware('prime.role:admin,operator')->group(function (): void {
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    });
});
