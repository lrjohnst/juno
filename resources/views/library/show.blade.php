@extends('layouts.app', ['title' => $source->title])

@section('content')
    <div class="page-head">
        <h2>{{ $source->title }}</h2>
        <a href="{{ route('library.index') }}">‹ Library</a>
    </div>

    <div class="card-panel">
        <table class="grid">
            <tbody>
                <tr><th style="width:140px">Auteurs</th><td>{{ implode('; ', $source->authors ?? []) }}</td></tr>
                <tr><th>Jaar</th><td>{{ $source->year ?? '—' }}</td></tr>
                @if ($source->doi)<tr><th>DOI</th><td><a href="https://doi.org/{{ $source->doi }}" rel="noopener" target="_blank">{{ $source->doi }}</a></td></tr>@endif
                @if ($source->isbn)<tr><th>ISBN</th><td class="mono">{{ $source->isbn }}</td></tr>@endif
                @if ($source->url)<tr><th>URL</th><td><a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank">{{ $source->url }}</a></td></tr>@endif
                <tr><th>Beschrijving</th><td style="white-space:pre-wrap">{{ $source->description }}</td></tr>
                <tr><th>Toegevoegd</th><td><x-time :at="$source->created_at" /> @if ($source->tick) · tick <a href="{{ route('ticks.show', $source->tick) }}">#{{ $source->tick->id }}</a> <x-role :role="$source->tick->role" /> @endif</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card-panel">
        <h2 class="section">Aantekeningen</h2>
        @forelse ($notes as $note)
            <details class="note" @if ($openNote === $note->id || $notes->count() === 1) open @endif>
                <summary>{{ $note->kind->value }} · {{ $note->versions->count() }} {{ $note->versions->count() === 1 ? 'versie' : 'versies' }}
                    <span class="muted">· laatst <x-time :at="$note->latestVersion?->created_at" format="d-m H:i" />@if ($note->latestVersion?->tick_id) (tick #{{ $note->latestVersion->tick_id }})@endif</span></summary>
                <pre class="content" style="margin-top:8px">{{ $note->latestVersion?->content }}</pre>
                @if ($note->versions->count() > 1)
                    <details style="margin-top:8px"><summary class="muted">Eerdere versies</summary>
                        @foreach ($note->versions->reverse()->skip(1) as $version)
                            <p class="muted" style="margin:8px 0 4px">Versie {{ $version->id }} · <x-time :at="$version->created_at" />@if ($version->tick_id) · tick #{{ $version->tick_id }}@endif</p>
                            <pre class="content">{{ $version->content }}</pre>
                        @endforeach
                    </details>
                @endif
            </details>
        @empty
            <p class="empty">Nog geen aantekeningen.</p>
        @endforelse
    </div>

    <form method="post" action="{{ route('library.destroy', $source) }}" onsubmit="return confirm('Bron en alle aantekeningen verwijderen?')">@csrf @method('delete')
        <button class="btn-danger" type="submit">Bron verwijderen</button>
    </form>
@endsection
