@props(['role' => null])
@if ($role)
    <span class="pill pill-role">{{ $role->value }}</span>
@else
    <span class="muted">—</span>
@endif
