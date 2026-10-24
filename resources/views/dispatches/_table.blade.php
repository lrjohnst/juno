<table class="grid">
    <thead><tr><th>Dispatch</th><th>Tick</th><th>Status</th><th>Gestart</th><th>Prompt</th><th class="num">Duur</th><th class="num">Kosten</th></tr></thead>
    <tbody>
        @forelse ($dispatches as $dispatch)
            <tr>
                <td><a href="{{ route('dispatches.show', $dispatch) }}">#{{ $dispatch->id }}</a></td>
                <td>@if ($dispatch->tick_id) <a href="{{ route('ticks.show', $dispatch->tick_id) }}">#{{ $dispatch->tick_id }}</a> @else <span class="muted">—</span> @endif</td>
                <td><x-status :event="$dispatch->latestEvent" /></td>
                <td><x-time :at="$dispatch->created_at" format="d-m H:i" /></td>
                <td>{{ \Illuminate\Support\Str::limit($dispatch->prompt, 70) }}</td>
                <td class="num">{{ $dispatch->result?->duration_ms !== null ? number_format($dispatch->result->duration_ms / 1000, 1).' s' : '' }}</td>
                <td class="num">{{ $dispatch->result?->cost_usd !== null ? '$'.number_format($dispatch->result->cost_usd, 4) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Geen dispatches.</td></tr>
        @endforelse
    </tbody>
</table>
