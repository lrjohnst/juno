<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Settings uit de database gaan boven config/ en .env.
        try {
            config(Setting::pluck('value', 'key')->all());
        } catch (QueryException) {
            // Tabel bestaat nog niet (vóór de eerste migrate): blijf bij config/ en .env.
        }
    }
}
