{{--
    Empty state: a small Mondrian/triangle composition, a title, an optional text and actions.
      <x-admin.empty :title="__('admin.empty.media')" :text="…" icon="image">
          <a class="btn btn--sm" href="…">…</a>          ← default slot = actions
      </x-admin.empty>
--}}
@props([
    'title',
    'text' => null,
    'icon' => 'triangle',
])
<div {{ $attributes->class(['adm-empty']) }}>
    <div class="adm-empty__art" aria-hidden="true">
        <span class="adm-empty__block adm-empty__block--blue"></span>
        <span class="adm-empty__block adm-empty__block--yellow"></span>
        <span class="adm-empty__block adm-empty__block--red"></span>
        <span class="adm-empty__icon"><x-admin.icon :name="$icon" /></span>
    </div>
    <p class="adm-empty__title">{{ $title }}</p>
    @if ($text)
        <p class="adm-empty__text">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="adm-empty__actions">{{ $slot }}</div>
    @endif
</div>
