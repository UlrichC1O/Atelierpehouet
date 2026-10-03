{{--
    A card grouping one topic of a screen.
      <x-admin.card :title="__('…')" accent="blue">
          <x-slot:actions><a class="btn btn--sm btn--ghost" href="…">…</a></x-slot:actions>
          …body…
          <div class="adm-card__foot">…optional footer…</div>
      </x-admin.card>
    title ⇒ <h2> in .adm-card__head; accent (blue|yellow|red|orange|amber|white) ⇒ coloured edge + corner triangle.
--}}
@props([
    'title' => null,
    'accent' => null,
])
@php
    $accent = in_array($accent, (array) config('atelier.accents', []), true) ? $accent : null;
    $hasActions = isset($actions) && $actions->isNotEmpty();
@endphp
<section {{ $attributes->class(['adm-card', 'adm-card--accent accent-'.$accent => $accent]) }}>
    @if ($title || $hasActions)
        <header class="adm-card__head">
            @if ($title)
                <h2 class="adm-card__title">{{ $title }}</h2>
            @endif
            @if ($hasActions)
                <div class="adm-card__actions">{{ $actions }}</div>
            @endif
        </header>
    @endif
    <div class="adm-card__body">
        {{ $slot }}
    </div>
</section>
