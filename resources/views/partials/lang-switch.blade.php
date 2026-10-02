{{-- FR / EN switch. French is the main language; the current one is marked aria-current. --}}
@php($current = app()->getLocale())
<div class="lang-switch" role="group" aria-label="{{ __('ui.a11y.language') }}">
    @foreach (config('atelier.locales', []) as $code => $name)
        <a class="lang-switch__link" href="{{ route('locale.switch', $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}" @if ($code === $current) aria-current="true" @endif>
            <span aria-hidden="true">{{ strtoupper($code) }}</span><span class="visually-hidden">{{ $name }}</span>
        </a>
    @endforeach
</div>
