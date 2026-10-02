{{--
    The full logo: triangle mark + "TELIERS" (+ optional tagline and signature).
      <x-logo-wordmark size="header" />                                             compact, for the header
      <x-logo-wordmark size="hero" :animated="true" :tagline="true" :signature="true" />
      <x-logo-wordmark size="footer" :tagline="true" :signature="true" />
    Props: size (header|hero|footer), animated (assembly sequence: pieces → outline → letters rise →
    gradient flows → tagline types → signature draws), tagline, signature, idle (mark breathing loop,
    on by default for the hero).
    Geometry follows the logo: every size is expressed in "u" = the triangle height (font-size of the
    root), the letters are 45 % of it and sit on the triangle base, the "T" tucks under its slope.
    Each letter carries its own slice of one continuous TELIERS gradient (--x = offset of the letter
    inside the word, from Audiowide's advance widths), so letters can animate independently.
    The root is a <span> so the wordmark can live inside <a> or <h1>; the visible letters are
    aria-hidden and the brand name is given as visually hidden text.
--}}
@props([
    'size' => 'header',
    'animated' => false,
    'tagline' => false,
    'signature' => false,
    'idle' => null,
])
@php
    // Audiowide advance widths (em) and the tracking used by .wordmark__word (em).
    $track = 0.1;
    $advances = [['T', 0.73], ['E', 0.76], ['L', 0.74], ['I', 0.28], ['E', 0.76], ['R', 0.83], ['S', 0.78]];
    $glyphs = [];
    $x = 0.0;
    foreach ($advances as $i => [$char, $advance]) {
        $glyphs[] = ['char' => $char, 'i' => $i, 'x' => round($x, 3)];
        $x += $advance + $track;
    }
    $wordWidth = round($x - $track, 3);
    $idle = $idle ?? ($size === 'hero');
    $taglineText = __('ui.tagline');
@endphp
<span {{ $attributes->class([
        'wordmark',
        'wordmark--'.$size,
        'wordmark--animated' => $animated,
        'wordmark--tagged' => $tagline,
        'wordmark--signed' => $signature,
    ]) }}>
    <x-logo-mark class="wordmark__mark" :animated="$animated" :idle="$idle" decorative />
    <span class="wordmark__word" aria-hidden="true" style="--wm-track: {{ $track }}em; --wm-w: {{ $wordWidth }}em">@foreach ($glyphs as $glyph)<span class="wordmark__letter" data-letter="{{ $glyph['char'] }}" style="--i: {{ $glyph['i'] }}; --x: {{ $glyph['x'] }}em">{{ $glyph['char'] }}</span>@endforeach</span>
    <span class="visually-hidden">{{ config('atelier.name', 'Ateliers Pehouet') }}</span>
    @if ($tagline)
        <span class="wordmark__tagline" style="--chars: {{ mb_strlen($taglineText) }}"><span class="visually-hidden"> — </span>{{ $taglineText }}</span>
    @endif
    @if ($signature)
        <x-signature class="wordmark__signature" :animated="$animated" />
    @endif
</span>
