@extends('layouts.app', ['title' => "Tick {$tick->id}"])

@section('content')
    <div class="page-head">
        <h2>Tick #{{ $tick->id }} <x-status :event="$status" /></h2>
        <a href="{{ route('ticks.index') }}">‹ Alle ticks</a>
    </div>

    <div class="stats">
        <div class="stat"><div class="label">Parent</div><div class="value">@if ($tick->parent) <a href="{{ route('ticks.show', $tick->parent) }}">#{{ $tick->parent->id }}</a> <x-status :event="$tick->parent->latestEvent" /> @else <span class="muted">—</span> @endif</div></div>
        <div class="stat"><div class="label">Kinderen</div><div class="value">
            @forelse ($tick->children as $child) <a href="{{ route('ticks.show', $child) }}">#{{ $child->id }}</a> @empty <span class="muted">—</span> @endforelse
        </div>@if ($tick->children->count() > 1)<div class="sub">hier begint een branch</div>@endif</div>
        <div class="stat"><div class="label">Aangemaakt</div><div class="value" style="font-size:14px"><x-time :at="$tick->created_at" /></div></div>
    </div>

    @if ($ancestors->isNotEmpty())
        <p class="muted">Route terug: #{{ $tick->id }} @foreach ($ancestors as $a) ← <a href="{{ route('ticks.show', $a) }}">#{{ $a->id }}</a> @endforeach</p>
    @endif

    <div class="card-panel">
        <h2 class="section">Bediening</h2>
        <div class="actions">
            @if ($status?->label === App\Enums\TickEventLabel::Scheduled)
                <form method="post" action="{{ route('ticks.cancel', $tick) }}">@csrf
                    <input type="text" name="description" placeholder="Reden" required>
                    <button class="btn-danger" type="submit">Annuleren (Canceled)</button>
                </form>
            @elseif ($status?->label === App\Enums\TickEventLabel::Claimed)
                <form method="post" action="{{ route('ticks.abandon', $tick) }}">@csrf
                    <input type="text" name="description" placeholder="Reden" required>
                    <button class="btn-danger" type="submit">Opgeven (Abandoned)</button>
                </form>
                <span class="muted">Dit stopt de kernel niet; die krijgt bij het afmelden een 409.</span>
            @endif
            @if ($rollbackBlocker)
                <span class="muted">Rollback naar deze tick: {{ $rollbackBlocker }}</span>
            @else
                <form method="post" action="{{ route('ticks.rollback', $tick) }}">@csrf
                    <input type="text" name="description" placeholder="Waarom terugdraaien?" required>
                    <button class="btn-primary" type="submit">Terugdraaien naar #{{ $tick->id }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card-panel">
        <h2 class="section">Levenscyclus</h2>
        <ul class="timeline">
            @foreach ($tick->events as $event)
                <li><span class="when"><x-time :at="$event->happened_at" /></span> <x-status :event="$event" /> <span>{{ $event->description }}</span></li>
            @endforeach
        </ul>
    </div>

    <div class="card-panel">
        <h2 class="section">Dispatches</h2>
        @include('dispatches._table', ['dispatches' => $dispatches])
    </div>

    <div class="grid-2">
        <div class="card-panel">
            <h2 class="section">Journal</h2>
            @forelse ($journalEntries as $entry)
                <div style="white-space:pre-wrap; padding:5px 0; border-bottom:1px solid var(--border)">{{ $entry->content }}</div>
            @empty
                <p class="empty">Geen entries.</p>
            @endforelse
        </div>
        <div class="card-panel">
            <h2 class="section">Documentversies</h2>
            @forelse ($documentVersions as $version)
                <div style="padding:5px 0; border-bottom:1px solid var(--border)">
                    <a href="{{ route('documents.show', [$version->document, 'version' => $version->id]) }}">{{ $version->document->name }}</a>
                    <span class="muted">versie {{ $version->id }} · {{ number_format(strlen($version->content)) }} bytes</span>
                </div>
            @empty
                <p class="empty">Geen versies geschreven.</p>
            @endforelse
        </div>
    </div>
@endsection
