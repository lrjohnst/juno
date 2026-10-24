<div class="journal-tick">
    <h3><a href="{{ route('ticks.show', $tick) }}">Tick #{{ $tick->id }}</a> <x-status :event="$tick->latestEvent" />
        <span>· parent {{ $tick->parent_id ? '#'.$tick->parent_id : '—' }} · <x-time :at="$tick->created_at" format="d-m H:i" /></span></h3>
    <ul>
        @foreach ($tickEntries->sortBy('id') as $entry)
            <li>{{ $entry->content }}</li>
        @endforeach
    </ul>
</div>
