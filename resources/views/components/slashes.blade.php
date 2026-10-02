{{-- Signature-like white strokes (≈ −20° and ≈ 65°) that draw in and shimmer. <x-slashes :count="5" /> --}}
@props([
    'count' => 5,
])
<div {{ $attributes->class(['slashes', 'ap-anim-scope']) }} aria-hidden="true">
    @for ($i = 0; $i < max(1, (int) $count); $i++)
        <span class="slashes__line @if ($i % 3 === 2) slashes__line--steep @endif" style="--i: {{ $i }}"></span>
    @endfor
</div>
