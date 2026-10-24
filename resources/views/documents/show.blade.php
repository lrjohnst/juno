@extends('layouts.app', ['title' => $document->name])

@section('content')
    <div class="page-head">
        <h2>{{ $document->name }}</h2>
        <a href="{{ route('documents.index') }}">‹ Alle documenten</a>
    </div>

    <div class="card-panel">
        <form method="post" action="{{ route('documents.update', $document) }}" class="actions">@csrf @method('patch')
            <label><input type="hidden" name="follows_rollback" value="0"><input type="checkbox" name="follows_rollback" value="1" @checked($document->follows_rollback)> Volgt rollback</label>
            <button class="btn-sm" type="submit">Opslaan</button>
            <span class="muted">Aan: de stand volgt de route van de tick (een rollback draait hem terug). Uit: elke tick ziet de nieuwste versie.</span>
        </form>
    </div>

    <div class="card-panel">
        <div class="page-head">
            <h2 class="section" style="margin:0">
                @if ($shown && $current && $shown->id !== $current->id) Versie {{ $shown->id }} (oud) @elseif ($shown) Huidige versie ({{ $shown->id }}) @else Nog geen versie @endif
            </h2>
            @if ($shown) <span class="muted">tick <a href="{{ route('ticks.show', $shown->tick_id) }}">#{{ $shown->tick_id }}</a> · <x-time :at="$shown->created_at" /></span> @endif
        </div>
        @if ($shown) <pre class="content">{{ $shown->content }}</pre> @endif
        @if ($shown && $current && $shown->id !== $current->id) <p><a href="{{ route('documents.show', $document) }}">Naar de huidige versie ›</a></p> @endif
    </div>

    <div class="card-panel">
        <h2 class="section">Nieuwe versie</h2>
        @if ($head)
            <form method="post" action="{{ route('documents.versions.store', $document) }}">@csrf
                <textarea class="content" name="content">{{ old('content', $current?->content) }}</textarea>
                <div class="actions" style="margin-top:10px">
                    <button class="btn-primary" type="submit">Opslaan als nieuwe versie</button>
                    <span class="muted">Hangt aan de HEAD-tick #{{ $head->id }}.@if (str_ends_with($document->name, '.json')) Moet geldige JSON zijn.@endif</span>
                </div>
            </form>
        @else
            <p class="empty">Er is nog geen tick om een versie aan te hangen.</p>
        @endif
    </div>

    <div class="card-panel">
        <h2 class="section">Geschiedenis</h2>
        <table class="grid">
            <thead><tr><th>Versie</th><th>Tick</th><th>Status tick</th><th>Aangemaakt</th><th class="num">Grootte</th></tr></thead>
            <tbody>
                @forelse ($versions as $version)
                    <tr>
                        <td><a href="{{ route('documents.show', [$document, 'version' => $version->id]) }}">{{ $version->id }}</a> @if ($current && $version->id === $current->id) <span class="pill pill-active">huidig</span> @endif</td>
                        <td><a href="{{ route('ticks.show', $version->tick_id) }}">#{{ $version->tick_id }}</a></td>
                        <td><x-status :event="$version->tick?->latestEvent" /></td>
                        <td><x-time :at="$version->created_at" /></td>
                        <td class="num">{{ number_format(strlen($version->content)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Nog geen versies.</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$versions" />
    </div>
@endsection
