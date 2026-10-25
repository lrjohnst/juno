<?php

use App\Http\Controllers\Ui\DashboardController;
use App\Http\Controllers\Ui\DispatchController;
use App\Http\Controllers\Ui\DocumentController;
use App\Http\Controllers\Ui\JournalController;
use App\Http\Controllers\Ui\LibraryController;
use App\Http\Controllers\Ui\SettingController;
use App\Http\Controllers\Ui\TickController;
use App\Http\Controllers\Ui\TokenController;
use Illuminate\Support\Facades\Route;
use LucasJohnston\Sso\Http\Middleware\SsoAuthenticate;

Route::middleware(SsoAuthenticate::class)->group(function () {
    Route::get('/', DashboardController::class)->name('home');
    Route::post('/juno/pause', [DashboardController::class, 'pause'])->name('juno.pause');
    Route::post('/juno/resume', [DashboardController::class, 'resume'])->name('juno.resume');

    Route::get('/ticks', [TickController::class, 'index'])->name('ticks.index');
    Route::post('/ticks', [TickController::class, 'store'])->name('ticks.store');
    Route::get('/ticks/{tick}', [TickController::class, 'show'])->name('ticks.show');
    Route::post('/ticks/{tick}/cancel', [TickController::class, 'cancel'])->name('ticks.cancel');
    Route::post('/ticks/{tick}/abandon', [TickController::class, 'abandon'])->name('ticks.abandon');
    Route::post('/ticks/{tick}/rollback', [TickController::class, 'rollback'])->name('ticks.rollback');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])->name('documents.versions.store');

    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');

    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{source}', [LibraryController::class, 'show'])->name('library.show');
    Route::delete('/library/{source}', [LibraryController::class, 'destroy'])->name('library.destroy');

    Route::get('/dispatches', [DispatchController::class, 'index'])->name('dispatches.index');
    Route::get('/dispatches/{dispatch}', [DispatchController::class, 'show'])->name('dispatches.show');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/settings/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/settings/tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::patch('/settings/tokens/{token}', [TokenController::class, 'update'])->name('tokens.update');
    Route::post('/settings/tokens/{token}/revoke', [TokenController::class, 'revoke'])->name('tokens.revoke');
});
