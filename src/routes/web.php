<?php

use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiseaseGroupController;
use App\Http\Controllers\StatementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated and approved users
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'approved', 'session.age'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Statement import and management
    Route::get('/statement', [StatementController::class, 'index'])
        ->name('statement.index');
    Route::post('/statement', [StatementController::class, 'store'])
        ->name('statement.store');
    Route::get('/statement/files', [StatementController::class, 'files'])
        ->name('statement.files');
    Route::delete('/statement/files/{fileId}', [StatementController::class, 'destroyFile'])
        ->whereNumber('fileId')
        ->name('statement.files.destroy');

    Route::get('/statement/{fileId}/amount/edit', [StatementController::class, 'editAmount'])
        ->whereNumber('fileId')
        ->name('statement.amount.edit');
    Route::patch('/statement/{fileId}/amount', [StatementController::class, 'updateAmount'])
        ->whereNumber('fileId')
        ->name('statement.amount.update');
    Route::get('/statement/{fileId}/summary', [StatementController::class, 'summary'])
        ->whereNumber('fileId')
        ->name('statement.summary');

    // PDF: individual hospital, all hospitals combined, and period with appendix
    Route::get('/statement/{fileId}/print/{hproc}', [StatementController::class, 'printHospital'])
        ->whereNumber('fileId')
        ->where('hproc', '[A-Za-z0-9]+')
        ->name('statement.print');
    Route::get('/statement/{fileId}/print-all', [StatementController::class, 'printAllHospitals'])
        ->whereNumber('fileId')
        ->name('statement.print-all');
    Route::get('/statement/{fileId}/print-period', [StatementController::class, 'printPeriodWithAppendix'])
        ->whereNumber('fileId')
        ->name('statement.print-period');

    // Word: individual hospital, ZIP of all hospitals, and period with appendix
    Route::get('/statement/{fileId}/word/{hproc}', [StatementController::class, 'downloadHospitalWord'])
        ->whereNumber('fileId')
        ->where('hproc', '[A-Za-z0-9]+')
        ->name('statement.word');
    Route::get('/statement/{fileId}/word-all', [StatementController::class, 'downloadAllHospitalWords'])
        ->whereNumber('fileId')
        ->name('statement.word-all');
    Route::get('/statement/{fileId}/word-period', [StatementController::class, 'downloadPeriodWord'])
        ->whereNumber('fileId')
        ->name('statement.word-period');

    Route::get('/diseases-group', [DiseaseGroupController::class, 'index'])
        ->name('diseases-group.index');

    // Admin: approve new accounts
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/users/pending', [UserApprovalController::class, 'pending'])
            ->name('users.pending');
        Route::post('/users/{user}/approve', [UserApprovalController::class, 'approve'])
            ->whereNumber('user')
            ->name('users.approve');
    });
});
