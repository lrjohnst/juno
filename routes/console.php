<?php

use App\Models\Tick;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Eerst opruimen, dan klaarzetten: anders blokkeert een vastgelopen tick de volgende.
Schedule::call(fn () => Tick::abandonStale())
    ->name('ticks:abandon-stale')
    ->everyMinute();

// Eén tick per tick_interval minuten, geteld vanaf de Unix-epoch. tick.php (cron van juno) haalt hem op.
Schedule::call(fn () => Tick::schedule())
    ->name('ticks:schedule')
    ->everyMinute()
    ->when(fn () => intdiv(time(), 60) % max(1, (int) config('juno.tick_interval')) === 0);
