@props(['event' => null])
@if ($event)
    <span class="pill pill-{{ $event->label->value }}" title="{{ $event->description }}">{{ $event->label->value }}</span>
@else
    <span class="pill pill-none">—</span>
@endif
