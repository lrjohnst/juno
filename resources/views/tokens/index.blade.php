@extends('layouts.app', ['title' => 'Tokens'])

@section('content')
    <div class="page-head"><h2>API-tokens</h2><a href="{{ route('settings.index') }}">‹ Instellingen</a></div>

    @if ($new = session('newToken'))
        <div class="flash flash-info">
            Token <strong>{{ $new['name'] }}</strong> uitgegeven. Kopieer het nu: het wordt maar één keer getoond.
            <div class="token-display" style="margin-top:8px">{{ $new['plain'] }}</div>
        </div>
    @endif

    @foreach ($tokens as $token)
        @php($status = $token->events->last())
        <div class="card-panel">
            <div class="page-head">
                <h2 class="section" style="margin:0">{{ $token->name }} <x-status :event="$status" /></h2>
                <span class="muted">sinds <x-time :at="$token->created_at" format="d-m-Y" /></span>
            </div>
            <form method="post" action="{{ route('tokens.update', $token) }}" class="actions">@csrf @method('patch')
                @foreach ($abilities as $ability => $label)
                    <label title="{{ $label }}"><input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked($token->abilities->contains('ability', $ability)) @disabled($status?->label->value === 'Revoked')> <span class="mono">{{ $ability }}</span></label>
                @endforeach
                @if ($status?->label->value !== 'Revoked') <button class="btn-sm" type="submit">Rechten opslaan</button> @endif
            </form>
            <ul class="timeline" style="margin-top:10px">
                @foreach ($token->events as $event)
                    <li><span class="when"><x-time :at="$event->happened_at" /></span> <x-status :event="$event" /> <span>{{ $event->description }}</span></li>
                @endforeach
            </ul>
            @if ($status?->label->value === 'Issued')
                <form method="post" action="{{ route('tokens.revoke', $token) }}" class="actions" style="margin-top:10px">@csrf
                    <input type="text" name="description" placeholder="Reden van intrekken" required>
                    <button class="btn-danger" type="submit">Intrekken</button>
                </form>
            @endif
        </div>
    @endforeach

    <div class="card-panel">
        <h2 class="section">Nieuw token</h2>
        <form method="post" action="{{ route('tokens.store') }}">@csrf
            <div class="form-row">
                <div><label class="field-label">Naam</label><input type="text" name="name" value="{{ old('name') }}" required></div>
                <div><label class="field-label">Omschrijving</label><input type="text" name="description" value="{{ old('description') }}"></div>
            </div>
            <div class="actions">
                @foreach ($abilities as $ability => $label)
                    <label title="{{ $label }}"><input type="checkbox" name="abilities[]" value="{{ $ability }}"> <span class="mono">{{ $ability }}</span> <span class="muted">{{ $label }}</span></label>
                @endforeach
            </div>
            <div style="margin-top:10px"><button class="btn-primary" type="submit">Uitgeven</button></div>
        </form>
    </div>
@endsection
