@extends('layouts.app', ['title' => 'Documenten'])

@section('content')
    <div class="page-head"><h2>Documenten</h2>@if ($head)<span class="muted">Stand bij HEAD: tick #{{ $head->id }}</span>@endif</div>

    <table class="grid">
        <thead><tr><th>Naam</th><th>Volgt rollback</th><th>Huidige versie</th><th class="num">Grootte</th><th class="num">Versies</th></tr></thead>
        <tbody>
            @forelse ($documents as $document)
                @php($version = $current[$document->id] ?? null)
                <tr>
                    <td><a href="{{ route('documents.show', $document) }}">{{ $document->name }}</a></td>
                    <td>@if ($document->follows_rollback) <span class="pill pill-active">ja</span> @else <span class="pill pill-inactive">nee</span> @endif</td>
                    <td>@if ($version) {{ $version->id }} <span class="muted">· tick #{{ $version->tick_id }} · <x-time :at="$version->created_at" format="d-m H:i" /></span> @else <span class="muted">nog geen versie</span> @endif</td>
                    <td class="num">{{ $version ? number_format(strlen($version->content)) : '' }}</td>
                    <td class="num">{{ $document->versions_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Nog geen documenten.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="card-panel" style="margin-top:18px">
        <h2 class="section">Nieuw document</h2>
        <form method="post" action="{{ route('documents.store') }}">@csrf
            <div class="form-row">
                <div><label class="field-label">Naam</label><input type="text" name="name" value="{{ old('name') }}" placeholder="playbook.md" required></div>
                <div style="flex:0 0 auto; align-self:flex-end"><label><input type="checkbox" name="follows_rollback" value="1" checked> Volgt rollback</label></div>
                <div style="flex:0 0 auto; align-self:flex-end"><button class="btn-primary" type="submit">Aanmaken</button></div>
            </div>
            <p class="muted" style="margin:0">De naam wordt een bestand in <span class="mono">workspace/context/</span>. Daarna voeg je op de documentpagina de eerste versie toe.</p>
        </form>
    </div>
@endsection
