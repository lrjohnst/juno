@extends('layouts.app', ['title' => 'Team'])

@section('content')
    <div class="page-head"><h2>Team</h2></div>

    <div class="card-panel">
        <table class="grid">
            <thead><tr><th>Teamlid</th><th>Rol</th><th>Rolprompt</th><th class="num">Ticks</th><th>Laatste tick</th><th>Chat</th><th class="num">Sinds compact</th><th class="num">Sinds recap</th></tr></thead>
            <tbody>
                @foreach ($agents as $agent)
                    @php($s = $stats[$agent->id])
                    <tr>
                        <td><a href="{{ route('team.show', $agent) }}"><strong>{{ $agent->name }}</strong></a><div class="muted">{{ \Illuminate\Support\Str::limit($agent->summary, 90) }}</div></td>
                        <td>@if ($agent->kind->value === 'Human') <span class="pill pill-none">Mens</span> @else <x-role :role="$agent->role" /> @endif</td>
                        <td>@if ($agent->roleDocument) <a href="{{ route('documents.show', $agent->roleDocument) }}">{{ $agent->roleDocument->name }}</a> @else <span class="muted">—</span> @endif</td>
                        <td class="num">{{ $agent->kind->value === 'Agent' ? $s['ticks'] : '' }}</td>
                        <td>@if ($s['lastTick']) <a href="{{ route('ticks.show', $s['lastTick']) }}">#{{ $s['lastTick']->id }}</a> <x-status :event="$s['lastTick']->latestEvent" /> @else <span class="muted">—</span> @endif</td>
                        <td>@if ($s['sessionId']) <span class="mono" title="{{ $s['sessionId'] }}">{{ substr($s['sessionId'], 0, 8) }}</span> @elseif ($agent->kind->value === 'Agent') <span class="muted">nieuwe chat</span> @endif</td>
                        <td class="num">{{ $agent->kind->value === 'Agent' ? $s['sinceCompacted'] : '' }}</td>
                        <td class="num">{{ $agent->kind->value === 'Agent' ? $s['sinceRecapped'] : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="margin-top:10px">Chat: de Claude Code-sessie die de volgende tick voortzet; "nieuwe chat" = de volgende dispatch begint opnieuw. Sinds compact/recap: geclaimde ticks van deze agent na het laatste <span class="mono">Compacted</span>/<span class="mono">Recapped</span>.</p>
    </div>
@endsection
