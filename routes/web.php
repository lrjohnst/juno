<?php

use Illuminate\Support\Facades\Route;
use LucasJohnston\Sso\Http\Middleware\SsoAuthenticate;

Route::middleware(SsoAuthenticate::class)->group(function () {
    Route::get('/', function () {
        return view('home');
    })->name('home');
});
