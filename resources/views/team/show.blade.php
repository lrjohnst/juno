@extends('layouts.app', ['title' => $agent->name])

@section('content')
    <div class="page-head">
        <h2>{{ $agent->name }} @if ($agent->role) <x-role :role="$agent->role" /> @else <span class="pill pill-none">Mens</span> @endif</h2>
        <a href="{{ route('team.index') }}">‹ Team</a>
    </div>

    @if ($agent->kind->value === 'Agent')
        <div class="stats">
            <div class="stat">
                <div class="label">Ticks</div>
                <div class="value">{{ $stats['ticks'] }}</div>
                <div class="sub">@if ($stats['startedAt']) sinds <x-time :at="$stats['startedAt']" format="d-m-Y" /> @else nog geen @endif</div>
            </div>
            <div class="stat">
                <div class="label">Laatste tick</div>
                <div class="value">@if ($stats['lastTick']) <a href="{{ route('ticks.show', $stats['lastTick']) }}">#{{ $stats['lastTick']->id }}</a> <x-status :event="$stats['lastTick']->latestEvent" /> @else <span class="muted">—</span> @endif</div>
            </div>
            <div class="stat">
                <div class="label">Chat</div>
                <div class="value mono" style="font-size:14px">{{ $stats['sessionId'] ?? 'nieuwe chat' }}</div>
                <div class="sub">de volgende tick zet deze sessie voort</div>
            </div>
            <div class="stat">
                <div class="label">Ritme</div>
                <div class="value">{{ $stats['sinceCompacted'] }} · {{ $stats['sinceRecapped'] }}</div>
                <div class="sub">ticks sinds compact · sinds recap</div>
            </div>
        </div>
    @endif

    <div class="card-panel">
        <h2 class="section">Instellingen</h2>
        <form method="post" action="{{ route('team.update', $agent) }}">@csrf @method('patch')
            <label>Samenvatting <span class="muted">(één regel in het teamoverzicht dat de anderen in hun recap krijgen)</span>
                <textarea name="summary" rows="3">{{ old('summary', $agent->summary) }}</textarea>
            </label>
            @if ($agent->kind->value === 'Agent')
                <p style="margin:12px 0 6px"><strong>Documenten die de kernel klaarzet</strong> <span class="muted">in workspace/context</span></p>
                <div class="actions">
                    @foreach ($documents as $document)
                        @continue($document->id === $agent->role_document_id)
                        <label><input type="checkbox" name="documents[]" value="{{ $document->id }}" @checked($agent->documents->contains($document))> <span class="mono">{{ $document->name }}</span></label>
                    @endforeach
                </div>
                <p class="muted" style="margin-top:6px">Rolprompt (gaat in de prompt, niet in de context):
                    @if ($agent->roleDocument) <a href="{{ route('documents.show', $agent->roleDocument) }}">{{ $agent->roleDocument->name }}</a> @else — @endif
                </p>
            @endif
            <div class="actions" style="margin-top:10px"><button class="btn-primary" type="submit">Opslaan</button></div>
        </form>
    </div>

    @if ($agent->kind->value === 'Agent')
        <div class="card-panel">
            <h2 class="section">Nieuwe chat</h2>
            <form method="post" action="{{ route('team.reset-session', $agent) }}" class="actions">@csrf
                <input type="text" name="description" placeholder="Reden, bijv. chat te lang of op een dwaalspoor" required>
                <button class="btn-danger" type="submit">Nieuwe chat bij de volgende tick</button>
            </form>
            <p class="muted" style="margin-top:6px">Legt <span class="mono">SessionReset</span> vast. De oude chat blijft bestaan in Claude Code, maar wordt niet meer voortgezet.</p>
        </div>

        <div class="card-panel">
            <h2 class="section">Events</h2>
            <ul class="timeline">
                @forelse ($events as $event)
                    <li><span class="when"><x-time :at="$event->happened_at" /></span> <x-status :event="$event" />
                        @if ($event->tick_id) <a href="{{ route('ticks.show', $event->tick_id) }}">#{{ $event->tick_id }}</a> @endif
                        <span>{{ $event->description }}</span></li>
                @empty
                    <li class="empty">Nog geen events.</li>
                @endforelse
            </ul>
        </div>

        <div class="card-panel">
            <h2 class="section">Laatste dispatches</h2>
            @include('dispatches._table', ['dispatches' => $dispatches])
        </div>
    @endif

    <div class="card-panel">
        <div class="page-head"><h2 class="section" style="margin:0">Laatste berichten</h2><a href="{{ route('inbox.index', ['view' => 'all']) }}">Alle berichten ›</a></div>
        @include('inbox._emails', ['emails' => $emails])
    </div>
@endsection
