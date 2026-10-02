{{-- Intro loader: shown only on the first page of a session (head.js adds html.is-loading). --}}
<div class="loader" data-loader aria-hidden="true">
    <div class="loader__stage">
        <x-logo-mark class="loader__mark" decorative />
        <div class="loader__bar"><span></span></div>
        <p class="loader__label">{{ __('ui.loader.label') }}</p>
    </div>
    <div class="loader__panels"><span></span><span></span><span></span></div>
</div>
