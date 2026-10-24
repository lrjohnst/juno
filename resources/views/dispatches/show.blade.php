@extends('layouts.app', ['title' => "Dispatch {$dispatch->id}"])

@section('content')
    <div class="page-head">
        <h2>Dispatch #{{ $dispatch->id }} <x-status :event="$status" /></h2>
        <a href="{{ route('dispatches.index') }}">‹ Alle dispatches</a>
    </div>

    @php($result = $dispatch->result)
    <div class="stats">
        <div class="stat"><div class="label">Tick</div><div class="value">@if ($dispatch->tick) <a href="{{ route('ticks.show', $dispatch->tick) }}">#{{ $dispatch->tick->id }}</a> <x-status :event="$dispatch->tick->latestEvent" /> @else <span class="muted">—</span> @endif</div></div>
        <div class="stat"><div class="label">Exitcode</div><div class="value">{{ $result?->exit_code ?? '—' }}</div></div>
        <div class="stat"><div class="label">Duur</div><div class="value">{{ $result?->duration_ms !== null ? number_format($result->duration_ms / 1000, 1).' s' : '—' }}</div><div class="sub">{{ $result?->num_turns !== null ? $result->num_turns.' beurten' : '' }}</div></div>
        <div class="stat"><div class="label">Kosten</div><div class="value">{{ $result?->cost_usd !== null ? '$'.number_format($result->cost_usd, 4) : '—' }}</div></div>
    </div>

    <div class="card-panel">
        <h2 class="section">Prompt <span class="muted" style="font-weight:400">· {{ $dispatch->permission_mode }}</span></h2>
        <pre class="content">{{ $dispatch->prompt }}</pre>
    </div>

    <div class="card-panel">
        <h2 class="section">Uitvoer</h2>
        @if ($result)
            <pre class="content">{{ $result->output }}</pre>
            @if ($result->session_id) <p class="muted" style="margin:10px 0 0">Sessie <span class="mono">{{ $result->session_id }}</span> — hervatten als juno: <span class="mono">~/tools/claude --resume {{ $result->session_id }}</span></p> @endif
        @else
            <p class="empty">Nog geen resultaat.</p>
        @endif
    </div>

    <div class="card-panel">
        <h2 class="section">Levenscyclus</h2>
        <ul class="timeline">
            @foreach ($dispatch->events as $event)
                <li><span class="when"><x-time :at="$event->happened_at" /></span> <x-status :event="$event" /> <span>{{ $event->description }}</span></li>
            @endforeach
        </ul>
    </div>
@endsection
