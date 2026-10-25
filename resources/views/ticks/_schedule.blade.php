<form method="post" action="{{ route('ticks.store') }}" class="actions">@csrf
    <select name="role" style="width:auto">
        <option value="">Volgende rol ({{ App\Models\Tick::nextRole(App\Models\Tick::head())->value }})</option>
        @foreach (App\Enums\TickRole::cases() as $role)
            <option value="{{ $role->value }}">{{ $role->value }}</option>
        @endforeach
    </select>
    <button type="submit" @disabled($disabled ?? false) title="Zet nu een tick klaar, los van het interval">Tick nu klaarzetten</button>
</form>
