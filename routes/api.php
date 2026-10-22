<?php

use App\Http\Controllers\TickController;
use App\Http\Middleware\AuthenticateJunoToken;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateJunoToken::class)->group(function () {
    Route::post('/ticks/claim', [TickController::class, 'claim']);
    Route::post('/ticks/{tick}/events', [TickController::class, 'storeEvent']);
});
