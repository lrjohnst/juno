<table class="grid">
    <thead><tr><th>Tick</th><th>Parent</th><th>Rol</th><th>Status</th><th>Aangemaakt</th><th class="num">Dispatches</th><th class="num">Journal</th>@isset($full)<th class="num">Versies</th><th class="num">Kinderen</th>@endisset</tr></thead>
    <tbody>
        @forelse ($ticks as $tick)
            <tr>
                <td><a href="{{ route('ticks.show', $tick) }}">#{{ $tick->id }}</a></td>
                <td>@if ($tick->parent_id) <a href="{{ route('ticks.show', $tick->parent_id) }}">#{{ $tick->parent_id }}</a> @else <span class="muted">—</span> @endif</td>
                <td><x-role :role="$tick->role" /></td>
                <td><x-status :event="$tick->latestEvent" /></td>
                <td><x-time :at="$tick->created_at" /></td>
                <td class="num">{{ $tick->dispatches_count ?: '' }}</td>
                <td class="num">{{ $tick->journal_entries_count ?: '' }}</td>
                @isset($full)
                    <td class="num">{{ $tick->document_versions_count ?: '' }}</td>
                    <td class="num">@if ($tick->children_count > 1) <strong title="Meer dan één kind: hier begint een branch">{{ $tick->children_count }}</strong> @else {{ $tick->children_count ?: '' }} @endif</td>
                @endisset
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Nog geen ticks.</td></tr>
        @endforelse
    </tbody>
</table>
