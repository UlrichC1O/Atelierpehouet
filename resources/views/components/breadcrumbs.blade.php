{{-- <x-breadcrumbs :items="[['label' => 'Services', 'url' => route('services.index')], ['label' => 'Sculpture']]" /> --}}
@props([
    'items' => [],
])
<nav {{ $attributes->class('breadcrumbs') }} aria-label="{{ __('components.breadcrumbs.label') }}">
    <ol class="breadcrumbs__list" role="list">
        <li class="breadcrumbs__item"><a href="{{ route('home') }}">{{ __('ui.breadcrumb.home') }}</a></li>
        @foreach ($items as $item)
            <li class="breadcrumbs__item">
                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
