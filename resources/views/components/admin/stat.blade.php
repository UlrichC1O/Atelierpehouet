{{--
    A key figure of the dashboard.
      <x-admin.stat :value="42" :label="__('admin.dashboard.stats.photos')" :href="route('admin.media.index')" accent="blue" icon="image" />
    href ⇒ the whole tile is a link; accent: blue|yellow|red|orange|amber|white; icon: any <x-admin.icon> name.
--}}
@props([
    'value',
    'label',
    'href' => null,
    'accent' => 'yellow',
    'icon' => null,
])
@php
    $accent = in_array($accent, (array) config('atelier.accents', []), true) ? $accent : 'yellow';
@endphp
@if ($href)
<a {{ $attributes->class(['adm-stat', 'adm-stat--link', 'accent-'.$accent])->merge(['href' => $href]) }}>
@else
<div {{ $attributes->class(['adm-stat', 'accent-'.$accent]) }}>
@endif
    @if ($icon)
        <span class="adm-stat__icon" aria-hidden="true"><x-admin.icon :name="$icon" /></span>
    @endif
    <span class="adm-stat__value">{{ $value }}</span>
    <span class="adm-stat__label">{{ $label }}</span>
    @if ($href)
        <span class="adm-stat__go" aria-hidden="true"><x-admin.icon name="arrow-right" /></span>
    @endif
@if ($href)
</a>
@else
</div>
@endif
