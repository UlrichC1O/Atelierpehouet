{{--
    Page heading of an admin screen: the only <h1> of the page.
      <x-admin.page-head :title="__('admin.nav.media')" :lead="…" :back="route('admin.media.index')">
          <a class="btn btn--sm" href="…">Action</a>      ← default slot = actions (right side)
      </x-admin.page-head>
    The Mondrian mark takes the accent of the current section (set on .adm-main by the layout).
--}}
@props([
    'title',
    'lead' => null,
    'back' => null,
])
<header {{ $attributes->class(['adm-page-head']) }}>
    <div class="adm-page-head__text">
        @if ($back)
            <a class="adm-page-head__back" href="{{ $back }}">
                <x-admin.icon name="arrow-left" />
                <span>{{ __('admin.actions.back') }}</span>
            </a>
        @endif
        <span class="adm-page-head__mark" aria-hidden="true"><span></span><span></span><span></span></span>
        <h1 class="adm-page-head__title">{{ $title }}</h1>
        @if ($lead)
            <p class="adm-page-head__lead">{{ $lead }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="adm-page-head__actions">{{ $slot }}</div>
    @endif
</header>
