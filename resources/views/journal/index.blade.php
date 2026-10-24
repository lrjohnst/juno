@extends('layouts.app', ['title' => 'Journal'])

@section('content')
    <div class="page-head"><h2>Journal</h2></div>

    <div class="card-panel">
        <h2 class="section">Wat de kernel meekrijgt: de laatste {{ $journalWindow }} ticks, over alle branches heen</h2>
        @forelse ($window as $tickId => $tickEntries)
            @include('journal._tick', ['tick' => $tickEntries->first()->tick, 'tickEntries' => $tickEntries])
        @empty
            <p class="empty">Leeg: de laatste {{ $journalWindow }} ticks schreven niets.</p>
        @endforelse
    </div>

    <div class="card-panel">
        <h2 class="section">Alle entries</h2>
        @forelse ($grouped as $tickId => $tickEntries)
            @include('journal._tick', ['tick' => $tickEntries->first()->tick, 'tickEntries' => $tickEntries])
        @empty
            <p class="empty">Nog geen entries.</p>
        @endforelse
        <x-pagination :paginator="$entries" />
    </div>
@endsection
