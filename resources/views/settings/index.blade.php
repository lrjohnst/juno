@extends('layouts.app', ['title' => 'Instellingen'])

@section('content')
    <div class="page-head"><h2>Instellingen</h2><a href="{{ route('tokens.index') }}">API-tokens ›</a></div>

    <form method="post" action="{{ route('settings.update') }}" class="card-panel">@csrf
        <table class="grid">
            <thead><tr><th>Instelling</th><th style="width:140px">Waarde</th><th>Uitleg</th><th class="num">.env</th></tr></thead>
            <tbody>
                @foreach ($settings as $key => [$label, $unit, $help])
                    @php($field = str_replace('.', '_', $key))
                    <tr>
                        <td><strong>{{ $label }}</strong><div class="muted mono">{{ $key }}</div></td>
                        <td><input type="number" name="{{ $field }}" min="1" value="{{ old($field, $values[$key] ?? $defaults[$key]) }}" required> <span class="muted">{{ $unit }}</span></td>
                        <td class="muted">{{ $help }}</td>
                        <td class="num muted">{{ $defaults[$key] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="actions" style="margin-top:12px">
            <button class="btn-primary" type="submit">Opslaan</button>
            <span class="muted">Opgeslagen in de tabel settings; die gaat voor <span class="mono">.env</span>.</span>
        </div>
    </form>
@endsection
