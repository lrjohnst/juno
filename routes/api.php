<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\TickController;
use App\Http\Middleware\AuthenticateJunoToken;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateJunoToken::class)->group(function () {
    Route::post('/ticks/claim', [TickController::class, 'claim']);
    Route::post('/ticks/{tick}/events', [TickController::class, 'storeEvent']);
    Route::get('/ticks/{tick}/documents', [DocumentController::class, 'index']);
    Route::post('/ticks/{tick}/documents/{name}', [DocumentController::class, 'store']);
    Route::get('/journal', [JournalController::class, 'index']);
    Route::post('/ticks/{tick}/journal', [JournalController::class, 'store']);
});
