{{-- Filter pill: <x-chip filter="peinture" :active="false">Peinture <span class="chip__count">4</span></x-chip> --}}
@props([
    'active' => false,
    'filter' => null,
])
<button {{ $attributes->class(['chip', 'is-active' => $active])->merge(['type' => 'button']) }} @if ($filter !== null) data-filter="{{ $filter }}" @endif aria-pressed="{{ $active ? 'true' : 'false' }}">{{ $slot }}</button>
