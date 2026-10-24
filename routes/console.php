<?php

use App\Enums\TickEventLabel;
use App\Juno;
use App\Models\Dispatch;
use App\Models\DocumentVersion;
use App\Models\JournalEntry;
use App\Models\Tick;
use App\Models\TickEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Eerst opruimen, dan klaarzetten: anders blokkeert een vastgelopen tick de volgende.
Schedule::call(fn () => Tick::abandonStale())
    ->name('ticks:abandon-stale')
    ->everyMinute();

Schedule::call(fn () => Dispatch::abandonStale())
    ->name('dispatches:abandon-stale')
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

// Leegt de hele tick-geschiedenis: ticks, tick_events en alles wat aan een tick hangt (documentversies en
// journal-entries). Documenten zelf, settings en juno_events blijven. De ids beginnen daarna weer bij 1.
Artisan::command('juno:clear-ticks {--force : Niet vragen, en ook doorgaan als er een tick open staat}', function () {
    $open = Tick::whereStatus(...TickEventLabel::open())->pluck('id');

    if ($open->isNotEmpty() && ! $this->option('force')) {
        $this->error('Er staat een tick open ('.$open->implode(', ').'). Laat die eerst aflopen, of gebruik --force.');

        return 1;
    }

    $counts = sprintf('%d ticks, %d tick-events, %d documentversies, %d journal-entries',
        Tick::count(), TickEvent::count(), DocumentVersion::count(), JournalEntry::count());

    if (! $this->option('force') && ! $this->confirm("Dit verwijdert {$counts}. Doorgaan?")) {
        return 1;
    }

    Schema::withoutForeignKeyConstraints(function () {
        JournalEntry::truncate();
        DocumentVersion::truncate();
        TickEvent::truncate();
        Tick::truncate();
    });

    $this->info("Verwijderd: {$counts}.");
})->purpose('Leeg de tick-geschiedenis (ticks, events, documentversies, journal)');
