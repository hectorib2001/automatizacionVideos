<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ControlExportController;
use App\Http\Controllers\ControlPanelController;
use App\Http\Controllers\SourcesController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('control.index'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/panel', [ControlPanelController::class, 'index'])->name('control.index');
    Route::get('/panel/export/tabla-control.csv', [ControlExportController::class, 'pieces'])->name('control.export.pieces');
    Route::get('/panel/export/revisiones.csv', [ControlExportController::class, 'reviews'])->name('control.export.reviews');
    Route::get('/panel/export/rechazos.csv', [ControlExportController::class, 'rejections'])->name('control.export.rejections');

    Route::get('/fuentes', [SourcesController::class, 'index'])->name('sources.index');
    Route::post('/fuentes/documentos', [SourcesController::class, 'storeDocument'])->name('sources.documents.store');
    Route::post('/fuentes/clases', [SourcesController::class, 'storeClass'])->name('sources.classes.store');
    Route::post('/fuentes/{source}/generar', [SourcesController::class, 'run'])->name('sources.run');
});
