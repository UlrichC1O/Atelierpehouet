{{--
    <x-service-card :service="$service" :index="$loop->index" variant="default|compact|feature" />
    Filterable (data-filter-item / data-category), revealed on scroll, tilts and lights up under the pointer.
--}}
@props([
    'service',
    'index' => 0,
    'variant' => 'default',
])
<article {{ $attributes->class(['service-card', 'service-card--'.$variant, 'accent-'.$service['accent']]) }}
         data-reveal="fade-up" data-filter-item data-category="{{ $service['category'] }}" style="--i: {{ $index }}">
    <a class="service-card__link" href="{{ $service['url'] }}" data-tilt data-spotlight>
        <span class="service-card__border" aria-hidden="true"></span>
        <span class="service-card__num" aria-hidden="true">{{ $service['number'] }}</span>
        <span class="service-card__icon" aria-hidden="true"><x-icon :name="$service['icon']" /></span>
        <span class="service-card__cat">{{ $service['category_label'] }}</span>
        <h3 class="service-card__title">{{ $service['title'] }}</h3>
        @if ($variant !== 'compact' && ! empty($service['short']))
            <p class="service-card__text">{{ $service['short'] }}</p>
        @endif
        <span class="service-card__more">{{ __('components.service_card.more') }}<x-icon name="arrow-right" /></span>
        <span class="service-card__corner" aria-hidden="true"></span>
    </a>
</article>
