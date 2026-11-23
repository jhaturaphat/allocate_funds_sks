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
Route::delete(
    '/statement/files/{fileId}',
    [StatementController::class, 'destroyFile']
)->name('statement.files.destroy');

// หนังสือแจ้งจัดสรรรายโรงพยาบาล (ของเดิม)
Route::get('/statement/{fileId}/print/{hproc}', [StatementController::class, 'printHospital'])
    ->name('statement.print');

// พิมพ์หนังสือรายโรงพยาบาลทุกแห่งแล้วรวม PDF เป็นไฟล์เดียว (ของเดิม)
Route::get('/statement/{fileId}/print-all', [StatementController::class, 'printAllHospitals'])
    ->name('statement.print-all');

// หนังสือ 1 ฉบับพร้อมบัญชีรายละเอียดแนบท้ายทุกโรงพยาบาล (เพิ่มใหม่)
Route::get('/statement/{fileId}/print-period', [StatementController::class, 'printPeriodWithAppendix'])
    ->name('statement.print-period');
    // ดาวน์โหลด Word หนังสือรายโรงพยาบาลหนึ่งแห่ง
Route::get('/statement/{fileId}/word/{hproc}', [StatementController::class, 'downloadHospitalWord'])
    ->name('statement.word');

// ดาวน์โหลด Word ของทุกโรงพยาบาลเป็น ZIP (หนึ่งไฟล์ DOCX ต่อหนึ่งโรงพยาบาล)
Route::get('/statement/{fileId}/word-all', [StatementController::class, 'downloadAllHospitalWords'])
    ->name('statement.word-all');

// ดาวน์โหลด Word หนังสือหนึ่งฉบับพร้อมบัญชีรายละเอียดแนบท้าย
Route::get('/statement/{fileId}/word-period', [StatementController::class, 'downloadPeriodWord'])
    ->name('statement.word-period');


Route::get('/diseases-group', [DiseaseGroupController::class, 'index'])->name('diseases-group.index');