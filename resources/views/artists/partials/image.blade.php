{{--
    One photo of the CMS library (App\Cms\MediaItem) as a responsive <img> — docs/ARTISTS.md §5.3.
    @include('artists.partials.image', ['media' => $media, 'alt' => '…', 'sizes' => '(min-width: 64em) 30vw, 92vw'])
    Options: width (variant used as src, 960) · lazy (true) · class (null) · cover (false; true ⇒ object-position from
    the focal point, for object-fit: cover frames) · priority (false; true on an eager image ⇒ fetchpriority="high").
    Pass every option explicitly: an @include also sees the variables of the including view.
--}}
@php
    $imageSrcset = (string) $media->srcset();
    $imageLazy = (bool) ($lazy ?? true);
@endphp
<img @if (! empty($class)) class="{{ $class }}" @endif
     src="{{ $media->url($width ?? 960) }}"
     @if ($imageSrcset !== '') srcset="{{ $imageSrcset }}" sizes="{{ $sizes ?? '100vw' }}" @endif
     width="{{ $media->width }}" height="{{ $media->height }}"
     alt="{{ $alt ?? '' }}"
     loading="{{ $imageLazy ? 'lazy' : 'eager' }}" decoding="{{ $imageLazy ? 'async' : 'auto' }}"
     @if (! $imageLazy && ! empty($priority)) fetchpriority="high" @endif
     @if (! empty($cover)) style="object-position: {{ $media->objectPosition() }}" @endif>
