{{--
    Framed artwork. <x-art-frame :src="$url" alt="…" caption="…" :href="$url" group="gallery" style-key="vitrail" />
    href + group ⇒ opens in the lightbox; style-key ⇒ filterable by style.
--}}
@props([
    'src',
    'alt',
    'caption' => null,
    'href' => null,
    'group' => null,
    'styleKey' => null,
    'lazy' => true,
    'width' => 800,
    'height' => 800,
])
<figure {{ $attributes->class(['art-frame']) }} @if ($styleKey) data-filter-item data-category="{{ $styleKey }}" @endif>
    @if ($href)
        <a class="art-frame__link" href="{{ $href }}" @if ($group) data-lightbox="{{ $group }}" data-caption="{{ $caption ?? $alt }}" @endif>
    @endif
    <span class="art-frame__canvas">
        <img class="art-frame__img" src="{{ $src }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}" @if ($lazy) loading="lazy" decoding="async" @endif>
    </span>
    @if ($href)
            <span class="art-frame__zoom" aria-hidden="true"><x-icon name="eye" /></span>
            <span class="visually-hidden">{{ __('components.art_frame.open') }}</span>
        </a>
    @endif
    @if ($caption)
        <figcaption class="art-frame__caption">{{ $caption }}</figcaption>
    @endif
</figure>
