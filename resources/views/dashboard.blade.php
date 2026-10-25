@extends('layouts.app', ['title' => 'Overzicht'])

@section('content')
    <div class="stats">
        <div class="stat">
            <div class="label">Juno</div>
            <div class="value">@if ($paused) <span class="pill pill-Paused">Gepauzeerd</span> @else <span class="pill pill-Finished">Actief</span> @endif</div>
            <div class="sub">@if ($junoEvents->first()) sinds <x-time :at="$junoEvents->first()->happened_at" format="d-m H:i" /> @else nooit gepauzeerd @endif</div>
        </div>
        <div class="stat">
            <div class="label">Open tick</div>
            <div class="value">@if ($openTick) <a href="{{ route('ticks.show', $openTick) }}">#{{ $openTick->id }}</a> <x-status :event="$openTick->latestEvent" /> @else <span class="muted">geen</span> @endif</div>
            <div class="sub">HEAD: @if ($head) <a href="{{ route('ticks.show', $head) }}">#{{ $head->id }}</a> @else — @endif</div>
        </div>
        <div class="stat">
            <div class="label">Volgende tick</div>
            <div class="value">@if ($nextTick) <x-time :at="$nextTick" format="H:i" /> @else <span class="muted">—</span> @endif</div>
            <div class="sub">{{ $nextRole->value }} · @if ($paused) gepauzeerd @elseif ($openTick) eerst moet #{{ $openTick->id }} aflopen @else elke {{ config('juno.tick_interval') }} min @endif</div>
        </div>
        <div class="stat">
            <div class="label">Totaal</div>
            <div class="value">{{ $tickCount }} <span class="muted" style="font-size:13px">ticks</span></div>
            <div class="sub">{{ $dispatchCount }} dispatches · vandaag ${{ number_format($costToday, 2) }}</div>
        </div>
    </div>

    <div class="card-panel">
        <h2 class="section">Bediening</h2>
        <div class="actions">
            @if ($paused)
                <form method="post" action="{{ route('juno.resume') }}">@csrf
                    <input type="text" name="description" placeholder="Reden van hervatten" required>
                    <button class="btn-primary" type="submit">Hervatten</button>
                </form>
            @else
                <form method="post" action="{{ route('juno.pause') }}">@csrf
                    <input type="text" name="description" placeholder="Reden van pauzeren" required>
                    <button type="submit">Pauzeren</button>
                </form>
                @include('ticks._schedule', ['disabled' => (bool) $openTick])
            @endif
        </div>
        <p class="muted" style="margin:10px 0 0">Rollback: pauzeren → open tick laten aflopen, annuleren of opgeven → op de pagina van een Finished tick terugdraaien → hervatten.</p>
    </div>

    <div class="card-panel">
        <div class="page-head"><h2 class="section" style="margin:0">Laatste ticks</h2><a href="{{ route('ticks.index') }}">Alle ticks ›</a></div>
        @include('ticks._table', ['ticks' => $ticks])
    </div>

    <div class="grid-2">
        <div class="card-panel">
            <div class="page-head"><h2 class="section" style="margin:0">Laatste dispatches</h2><a href="{{ route('dispatches.index') }}">Alle ›</a></div>
            @forelse ($dispatches as $dispatch)
                <div style="padding:6px 0; border-bottom:1px solid var(--border)">
                    <a href="{{ route('dispatches.show', $dispatch) }}">#{{ $dispatch->id }}</a>
                    <x-status :event="$dispatch->latestEvent" />
                    <span class="muted">tick {{ $dispatch->tick_id ?? '—' }} · <x-time :at="$dispatch->created_at" format="d-m H:i" /></span>
                    <div class="muted" style="margin-top:2px">{{ \Illuminate\Support\Str::limit($dispatch->prompt, 90) }}</div>
                </div>
            @empty
                <p class="empty">Nog geen dispatches.</p>
            @endforelse
        </div>
        <div class="card-panel">
            <h2 class="section">Pauzes</h2>
            <ul class="timeline">
                @forelse ($junoEvents as $event)
                    <li><span class="when"><x-time :at="$event->happened_at" format="d-m H:i" /></span> <x-status :event="$event" /> <span>{{ $event->description }}</span></li>
                @empty
                    <li class="empty">Nog nooit gepauzeerd.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
