@extends('layouts.app', ['title' => 'Ticks'])

@section('content')
    <div class="page-head">
        <h2>Ticks</h2>
        @include('ticks._schedule')
    </div>
    <form method="get" class="filters">
        <select name="role" onchange="this.form.submit()">
            <option value="">Alle rollen</option>
            @foreach (App\Enums\TickRole::cases() as $option)
                <option value="{{ $option->value }}" @selected($role === $option)>{{ $option->value }}</option>
            @endforeach
        </select>
    </form>
    @include('ticks._table', ['ticks' => $ticks, 'full' => true])
    <x-pagination :paginator="$ticks" />
@endsection
