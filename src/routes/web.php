<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\DiseaseGroupController;

Route::get('/', \App\Http\Controllers\DashboardController::class)->name('dashboard');

Route::get('/statement', [StatementController::class, 'index'])->name('statement.index');
Route::post('/statement', [StatementController::class, 'store'])->name('statement.store');
Route::get('/statement/{fileId}/amount/edit', [StatementController::class, 'editAmount'])
    ->whereNumber('fileId')
    ->name('statement.amount.edit');
Route::patch('/statement/{fileId}/amount', [StatementController::class, 'updateAmount'])
    ->whereNumber('fileId')
    ->name('statement.amount.update');
Route::get('/statement/files', [StatementController::class, 'files'])
    ->name('statement.files');
Route::get('/statement/{fileId}/summary', [StatementController::class, 'summary'])
    ->whereNumber('fileId')->name('statement.summary');

Route::get('/diseases-group', [DiseaseGroupController::class, 'index'])->name('diseases-group.index');