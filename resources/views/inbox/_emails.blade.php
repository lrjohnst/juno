@forelse ($emails as $email)
    <details class="note" @if ($loop->first && ($openFirst ?? false)) open @endif>
        <summary>
            <strong>{{ $email->from->name }}</strong> → <strong>{{ $email->to->name }}</strong>
            · <x-time :at="$email->created_at" format="d-m H:i" />
            @if ($email->tick_id) · tick <a href="{{ route('ticks.show', $email->tick_id) }}">#{{ $email->tick_id }}</a> @endif
            <span class="muted">— {{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($email->body), 90) }}</span>
        </summary>
        <pre class="content" style="margin-top:8px">{{ $email->body }}</pre>
    </details>
@empty
    <p class="empty">Nog geen berichten.</p>
@endforelse
