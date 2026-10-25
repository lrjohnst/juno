<?php

use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\Library\NoteController;
use App\Http\Controllers\Library\SourceController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TickController;
use Illuminate\Support\Facades\Route;

// Elk token heeft eigen rechten (api_tokens / api_token_abilities); zie ApiToken::ABILITIES.

Route::middleware('juno.ability:kernel')->group(function () {
    Route::post('/ticks', [TickController::class, 'store']);
    Route::post('/ticks/claim', [TickController::class, 'claim']);
    Route::post('/ticks/{tick}/events', [TickController::class, 'storeEvent']);
    Route::get('/ticks/{tick}/documents', [DocumentController::class, 'index']);
    Route::post('/ticks/{tick}/documents/{name}', [DocumentController::class, 'store']);
    Route::post('/ticks/{tick}/dispatches', [DispatchController::class, 'store']);
    Route::post('/dispatches/{dispatch}/events', [DispatchController::class, 'storeEvent']);
    Route::post('/dispatches/{dispatch}/result', [DispatchController::class, 'storeResult']);
    Route::get('/journal', [JournalController::class, 'index']);
    Route::post('/ticks/{tick}/journal', [JournalController::class, 'store']);
    Route::post('/mail', [MailController::class, 'store']);
});

Route::prefix('library')->group(function () {
    Route::middleware('juno.ability:library.read')->group(function () {
        Route::get('/sources', [SourceController::class, 'index']);
        Route::get('/sources/lookup', [SourceController::class, 'lookup']);
        Route::get('/sources/{source}', [SourceController::class, 'show']);
        Route::get('/notes', [NoteController::class, 'index']);
        Route::get('/notes/{note}', [NoteController::class, 'show']);
    });

    Route::middleware('juno.ability:library.write')->group(function () {
        Route::post('/sources', [SourceController::class, 'store']);
        Route::patch('/sources/{source}', [SourceController::class, 'update']);
        Route::delete('/sources/{source}', [SourceController::class, 'destroy']);
        Route::post('/sources/{source}/notes', [NoteController::class, 'store']);
        Route::patch('/notes/{note}', [NoteController::class, 'update']);
        Route::post('/notes/{note}/versions', [NoteController::class, 'storeVersion']);
        Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
    });
});
