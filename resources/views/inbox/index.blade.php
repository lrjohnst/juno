@extends('layouts.app', ['title' => 'Inbox'])

@section('content')
    <div class="page-head">
        <h2>Inbox</h2>
        <div class="actions">
            <a href="{{ route('inbox.index') }}" @class(['btn', 'btn-sm', 'btn-primary' => ! $all])>Van en aan mij</a>
            <a href="{{ route('inbox.index', ['view' => 'all']) }}" @class(['btn', 'btn-sm', 'btn-primary' => $all])>Alle berichten van het team</a>
        </div>
    </div>

    <div class="card-panel">
        <h2 class="section">Nieuw bericht</h2>
        <form method="post" action="{{ route('inbox.store') }}">@csrf
            <div class="filters">
                <label>Aan
                    <select name="to_agent_id" required>
                        @foreach ($recipients as $recipient)
                            <option value="{{ $recipient->id }}" @selected(old('to_agent_id') == $recipient->id)>{{ $recipient->name }} ({{ $recipient->role->value }})</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <textarea class="content" name="body" style="min-height:140px" required placeholder="Verzoeken: …">{{ old('body') }}</textarea>
            <div class="actions" style="margin-top:10px">
                <button class="btn-primary" type="submit">Versturen</button>
                <span class="muted">De agent krijgt het bij zijn volgende tick, bovenaan zijn prompt.</span>
            </div>
        </form>
    </div>

    <div class="card-panel">
        <h2 class="section">{{ $all ? 'Alle berichten' : 'Van en aan '.$lucas->name }} ({{ $emails->total() }})</h2>
        @include('inbox._emails', ['emails' => $emails, 'openFirst' => true])
        <x-pagination :paginator="$emails" />
    </div>
@endsection
