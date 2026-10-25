@extends('layouts.app', ['title' => 'Library'])

@section('content')
    <div class="page-head"><h2>Library</h2><span class="muted">{{ $sources->total() }} bronnen · Juno vult dit via de library-API</span></div>

    <form method="get" class="filters">
        <input type="text" name="q" value="{{ $q }}" placeholder="Zoek in titel, auteurs, beschrijving">
        <button type="submit">Zoeken</button>
        @if ($q) <a href="{{ route('library.index') }}" class="btn">Wissen</a> @endif
    </form>

    <table class="grid">
        <thead><tr><th>Bron</th><th>Auteurs</th><th class="num">Jaar</th><th class="num">Aantekeningen</th><th>Toegevoegd</th></tr></thead>
        <tbody>
            @forelse ($sources as $source)
                <tr>
                    <td><a href="{{ route('library.show', $source) }}">{{ $source->title }}</a><div class="muted">{{ \Illuminate\Support\Str::limit($source->description, 110) }}</div></td>
                    <td>{{ implode('; ', $source->authors ?? []) }}</td>
                    <td class="num">{{ $source->year }}</td>
                    <td class="num">{{ $source->notes_count }}</td>
                    <td><x-time :at="$source->created_at" format="d-m-Y" /> @if ($source->tick_id) <span class="muted">· tick <a href="{{ route('ticks.show', $source->tick_id) }}">#{{ $source->tick_id }}</a></span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Nog geen bronnen.</td></tr>
            @endforelse
        </tbody>
    </table>
    <x-pagination :paginator="$sources" />
@endsection
