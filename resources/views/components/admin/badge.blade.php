{{--
    Small status label.  <x-admin.badge variant="modified">{{ __('admin.common.modified') }}</x-admin.badge>
    variant: default|new|modified|hidden|custom|success|danger|info
--}}
@props([
    'variant' => 'default',
])
<span {{ $attributes->class(['adm-badge', 'adm-badge--'.$variant => $variant !== 'default']) }}>{{ $slot }}</span>
