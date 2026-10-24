@props(['at' => null, 'format' => 'd-m-Y H:i:s'])
@if ($at)
    <span title="{{ $at->copy()->utc()->format('Y-m-d H:i:s') }} UTC">{{ $at->copy()->timezone('Europe/Amsterdam')->format($format) }}</span>
@else
    <span class="muted">—</span>
@endif
