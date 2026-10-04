{{--
    « Pages libres » (docs/CMS.md §7.4): the legal pages first (ready to complete, §13 F28), then every
    free page (title, address, status, footer link, last change) with its actions.
    Rendered by Admin\PageController@index. Vars: $pages (Collection<CustomPage>), $legal (slug ⇒
    ?CustomPage), $covers (id ⇒ MediaItem).
--}}
@extends('admin.layouts.app')

@section('title', __('admin_content.pages.title'))

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/content.css') }}">
@endpush

@section('content')
    <x-admin.page-head :title="__('admin_content.pages.title')" :lead="__('admin_content.pages.lead')">
        <a class="btn btn--sm" href="{{ route('admin.pages.create') }}">
            <x-icon name="plus" />
            <span class="btn__label">{{ __('admin_content.pages.create') }}</span>
        </a>
    </x-admin.page-head>

    <x-admin.card :title="__('admin_content.pages.legal.title')" accent="orange" class="adm-legal" id="legal-pages">
        <p class="adm-muted">{{ __('admin_content.pages.legal.lead') }}</p>
        <ul class="adm-legal__list" role="list">
            @foreach ($legal as $slug => $legalPage)
                <li class="adm-legal__item">
                    <span class="adm-legal__mark" aria-hidden="true"></span>
                    <div class="adm-legal__text">
                        <p class="adm-legal__name">{{ $legalPage ? $legalPage->title_fr : __('admin_content.pages.legal.names.'.$slug) }}</p>
                        <p class="adm-legal__url adm-mono">/{{ $slug }}</p>
                    </div>
                    @if ($legalPage)
                        @if ($legalPage->is_published)
                            <x-admin.badge variant="success">{{ __('admin_content.pages.legal.done') }}</x-admin.badge>
                        @else
                            <x-admin.badge variant="new">{{ __('admin_content.pages.legal.todo') }}</x-admin.badge>
                        @endif
                        <a class="btn btn--sm @if ($legalPage->is_published) btn--ghost @endif" href="{{ route('admin.pages.edit', $legalPage) }}">
                            <x-admin.icon name="edit" />
                            <span class="btn__label">{{ $legalPage->is_published ? __('admin.actions.edit') : __('admin_content.pages.legal.fill') }}</span>
                            <span class="visually-hidden">— {{ $legalPage->title_fr }}</span>
                        </a>
                    @else
                        <p class="adm-small adm-muted adm-legal__missing">{{ __('admin_content.pages.legal.missing', ['slug' => $slug]) }}</p>
                        <a class="btn btn--sm btn--secondary" href="{{ route('admin.pages.create', ['slug' => $slug]) }}">
                            <x-icon name="plus" />
                            <span class="btn__label">{{ __('admin_content.pages.legal.create') }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-admin.card>

    @if ($pages->isEmpty())
        <x-admin.empty :title="__('admin_content.pages.empty')" :text="__('admin_content.pages.empty_text')" icon="page">
            <a class="btn btn--sm" href="{{ route('admin.pages.create') }}">
                <x-icon name="plus" />
                <span class="btn__label">{{ __('admin_content.pages.create') }}</span>
            </a>
        </x-admin.empty>
    @else
        <section class="adm-stack" aria-labelledby="pages-list-title">
            <div class="adm-toolbar">
                <h2 class="adm-content-heading" id="pages-list-title">{{ trans_choice('admin_content.pages.all', $pages->count(), ['count' => $pages->count()]) }}</h2>
                @if ($pages->count() > 6)
                    <label class="adm-search">
                        <span class="visually-hidden">{{ __('admin_content.pages.search') }}</span>
                        <x-admin.icon name="search" />
                        <input class="field__input" type="search" placeholder="{{ __('admin.common.search_placeholder') }}"
                               data-adm-filter="#pages-rows" data-adm-filter-empty="#pages-empty">
                    </label>
                @endif
            </div>
            <table class="adm-table adm-pages-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin_content.pages.columns.title') }}</th>
                        <th scope="col">{{ __('admin_content.pages.columns.url') }}</th>
                        <th scope="col">{{ __('admin_content.pages.columns.status') }}</th>
                        <th scope="col">{{ __('admin_content.pages.columns.footer') }}</th>
                        <th scope="col">{{ __('admin_content.pages.columns.updated') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('admin.common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody id="pages-rows">
                    @foreach ($pages as $page)
                        <tr data-adm-filter-item data-adm-filter-text="{{ $page->title_fr.' '.$page->title_en.' '.$page->slug }}">
                            <td class="adm-table__main">
                                <div class="adm-pages-table__title">
                                    <x-admin.thumb :media="$covers[$page->cover_media_id] ?? null" :size="48" alt="" />
                                    <a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title_fr }}</a>
                                </div>
                            </td>
                            <td class="adm-pages-table__url" data-label="{{ __('admin_content.pages.columns.url') }}"><span class="adm-mono">/{{ $page->slug }}</span></td>
                            <td data-label="{{ __('admin_content.pages.columns.status') }}">
                                @if ($page->is_published)
                                    <x-admin.badge variant="success">{{ __('admin_content.pages.published') }}</x-admin.badge>
                                @else
                                    <x-admin.badge variant="hidden">{{ __('admin_content.pages.draft') }}</x-admin.badge>
                                @endif
                            </td>
                            <td data-label="{{ __('admin_content.pages.columns.footer') }}">{{ $page->in_footer ? __('admin.common.yes') : __('admin.common.no') }}</td>
                            <td data-label="{{ __('admin_content.pages.columns.updated') }}">{{ $page->updated_at?->locale(app()->getLocale())->translatedFormat('j M Y') ?? '—' }}</td>
                            <td class="adm-table__actions">
                                <div class="adm-cluster">
                                    <a class="btn btn--sm btn--ghost" href="{{ route('admin.pages.edit', $page) }}">
                                        <x-admin.icon name="edit" />
                                        <span class="btn__label">{{ __('admin.actions.edit') }}</span>
                                        <span class="visually-hidden">— {{ $page->title_fr }}</span>
                                    </a>
                                    <a class="btn btn--sm btn--ghost" href="{{ route('pages.custom', $page->slug) }}" target="_blank" rel="noopener">
                                        <x-icon name="external" />
                                        <span class="btn__label">{{ $page->is_published ? __('admin_content.pages.view') : __('admin_content.pages.preview_page') }}</span>
                                        <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-admin.empty :title="__('admin.empty.search')" icon="search" id="pages-empty" hidden />
        </section>
    @endif
@endsection
