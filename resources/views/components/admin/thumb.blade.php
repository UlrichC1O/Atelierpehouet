{{--
    Square thumbnail of a library photo (App\Cms\MediaItem), or an empty triangle placeholder.
      <x-admin.thumb :media="$item" />   ·   <x-admin.thumb :media="null" :size="64" />
    Uses the 480 px variant (sharp up to 2× at 240 px), the photo's focal point and alt text
    (alt="" ⇒ decorative, e.g. next to the photo's name).
--}}
@props([
    'media' => null,
    'size' => 96,
    'alt' => null,
])
@php
    $size = max(16, (int) $size);
@endphp
@if (is_object($media) && method_exists($media, 'url'))
    <img {{ $attributes->class(['adm-thumb'])->merge([
        'src' => $media->url(480),
        'width' => $size,
        'height' => $size,
        'alt' => $alt ?? (method_exists($media, 'alt') ? $media->alt() : ''),
        'loading' => 'lazy',
        'decoding' => 'async',
        'style' => '--adm-thumb: '.$size.'px; object-position: '.(method_exists($media, 'objectPosition') ? $media->objectPosition() : '50% 50%'),
    ]) }}>
@else
    <span {{ $attributes->class(['adm-thumb', 'adm-thumb--empty'])->merge(['style' => '--adm-thumb: '.$size.'px']) }} aria-hidden="true"><x-admin.icon name="image" /></span>
@endif
