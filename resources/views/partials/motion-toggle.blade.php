{{--
    Pauses / resumes every animation of the site (core.js persists the choice).
    aria-pressed = animations enabled. @include('partials.motion-toggle', ['compact' => true])
--}}
<button class="motion-toggle @if (! empty($compact)) motion-toggle--compact @endif" type="button" data-motion-toggle aria-pressed="true"
        data-label-on="{{ __('ui.motion.on') }}" data-label-off="{{ __('ui.motion.off') }}" title="{{ __('ui.motion.toggle') }}">
    <span class="motion-toggle__icon" aria-hidden="true"><i></i><i></i><i></i></span>
    <span class="motion-toggle__text">{{ __('ui.motion.label') }}&nbsp;: <span data-motion-label>{{ __('ui.motion.on') }}</span></span>
</button>
