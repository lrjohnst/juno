@extends('layouts.app', ['title' => 'Dispatches'])

@section('content')
    <div class="page-head"><h2>Dispatches</h2><span class="muted">Claude Code-opdrachten van de kernel, als juno in de workspace</span></div>
    @include('dispatches._table', ['dispatches' => $dispatches])
    <x-pagination :paginator="$dispatches" />
@endsection
