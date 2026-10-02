{{--
    <x-button href="/contact" icon="arrow-right" magnetic>Demander un devis</x-button>
    Variants: primary (TELIERS gradient) · secondary (blue) · ghost · outline (accent) · light · link
    Sizes: sm · md · lg. Renders <a> when href is set, otherwise <button type="…">.
--}}
@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconBefore' => null,
    'magnetic' => false,
    'type' => 'button',
    'external' => false,
])
@php
    $classes = ['btn', 'btn--'.$variant, 'btn--'.$size => $size !== 'md', 'btn--icon-only' => $slot->isEmpty()];
@endphp
@if ($href)
<a {{ $attributes->class($classes)->merge(['href' => $href]) }} data-ripple @if ($magnetic) data-magnetic @endif @if ($external) target="_blank" rel="noopener" @endif>
    @if ($iconBefore)<span class="btn__icon btn__icon--before"><x-icon :name="$iconBefore" /></span>@endif
    <span class="btn__label">{{ $slot }}</span>
    @if ($icon)<span class="btn__icon"><x-icon :name="$icon" /></span>@endif
    @if ($external)<span class="visually-hidden"> ({{ __('ui.a11y.new_tab') }})</span>@endif
</a>
@else
<button {{ $attributes->class($classes)->merge(['type' => $type]) }} data-ripple @if ($magnetic) data-magnetic @endif>
    @if ($iconBefore)<span class="btn__icon btn__icon--before"><x-icon :name="$iconBefore" /></span>@endif
    <span class="btn__label">{{ $slot }}</span>
    @if ($icon)<span class="btn__icon"><x-icon :name="$icon" /></span>@endif
</button>
@endif
