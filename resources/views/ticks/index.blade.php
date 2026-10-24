@extends('layouts.app', ['title' => 'Ticks'])

@section('content')
    <div class="page-head">
        <h2>Ticks</h2>
        <form method="post" action="{{ route('ticks.store') }}">@csrf <button type="submit">Tick nu klaarzetten</button></form>
    </div>
    @include('ticks._table', ['ticks' => $ticks, 'full' => true])
    <x-pagination :paginator="$ticks" />
@endsection
