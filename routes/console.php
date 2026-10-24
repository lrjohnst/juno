<?php

use App\Enums\TickEventLabel;
use App\Juno;
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

// Bediening door Lucas. Procedure voor een rollback: juno:pause → open ticks laten aflopen,
// juno:cancel of juno:abandon → juno:rollback → juno:resume.
Artisan::command('juno:pause {description}', function (string $description) {
    Juno::pause($description);
    $this->info('Juno is gepauzeerd: er worden geen ticks klaargezet of geclaimd.');
})->purpose('Pauzeer Juno');

Artisan::command('juno:resume {description}', function (string $description) {
    Juno::resume($description);
    $this->info('Juno is hervat.');
})->purpose('Hervat Juno');

Artisan::command('juno:cancel {tick} {description}', function (string $tick, string $description) {
    $tick = Tick::findOrFail($tick);
    $tick->setStatus(['label' => TickEventLabel::Canceled, 'description' => $description]);
    $this->info("Tick {$tick->id} is Canceled.");
})->purpose('Annuleer een tick die nog Scheduled is');

Artisan::command('juno:abandon {tick} {description}', function (string $tick, string $description) {
    $tick = Tick::findOrFail($tick);
    $tick->setStatus(['label' => TickEventLabel::Abandoned, 'description' => $description]);
    $this->info("Tick {$tick->id} is Abandoned.");
})->purpose('Geef een geclaimde tick handmatig op');

Artisan::command('juno:rollback {tick} {description}', function (string $tick, string $description) {
    $tick = Tick::findOrFail($tick);
    $new = Tick::rollbackTo($tick, $description);
    $this->info("Tick {$new->id} staat klaar met tick {$tick->id} als parent; hij start na juno:resume.");
})->purpose('Draai terug naar een tick die Finished is');
