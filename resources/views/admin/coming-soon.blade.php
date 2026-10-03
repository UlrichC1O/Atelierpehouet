{{--
    An admin section still being built (docs/CMS.md phase 2), rendered with HTTP 200 by
    App\Http\Controllers\Admin\ComingSoon: the section's name, a short French note and a way back.
    Var: $section (key of admin.nav.{section}: texts, services, media, gallery, pages, messages,
    settings, account).
--}}
@extends('admin.layouts.app')

@php
    $sectionKey = 'admin.nav.'.($section ?? '');
    $sectionTitle = app('translator')->has($sectionKey) ? __($sectionKey) : __('admin.brand.title');
    $sectionIcon = [
        'texts' => 'text', 'services' => 'services', 'media' => 'image', 'gallery' => 'gallery',
        'pages' => 'page', 'messages' => 'inbox', 'settings' => 'settings', 'account' => 'user',
    ][$section ?? ''] ?? 'triangle';
@endphp

@section('title', $sectionTitle)

@section('content')
    <x-admin.page-head :title="$sectionTitle" />

    <x-admin.card>
        <x-admin.empty :title="__('admin.coming_soon.heading')" :text="__('admin.coming_soon.text')" :icon="$sectionIcon">
            <a class="btn btn--sm" href="{{ route('admin.dashboard') }}">
                <x-admin.icon name="arrow-left" />
                <span class="btn__label">{{ __('admin.coming_soon.back') }}</span>
            </a>
            <a class="btn btn--sm btn--ghost" href="{{ route('home') }}" target="_blank" rel="noopener">
                <span class="btn__label">{{ __('admin.topbar.view_site') }}</span>
                <x-icon name="external" />
                <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
            </a>
        </x-admin.empty>
    </x-admin.card>
@endsection
