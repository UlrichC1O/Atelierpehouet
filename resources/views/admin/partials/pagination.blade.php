{{--
    Simple previous / next pagination for admin lists (docs/CMS.md §6 .adm-pagination).
      {{ $messages->withQueryString()->links('admin.partials.pagination') }}
    Works with paginate() (shows "Page 2 sur 5") and simplePaginate() ("Page 2").
--}}
@if ($paginator->hasPages())
    <nav class="adm-pagination" aria-label="{{ __('admin.a11y.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="adm-pagination__link is-disabled" aria-disabled="true">
                <x-icon name="arrow-left" /><span>{{ __('admin.common.previous') }}</span>
            </span>
        @else
            <a class="adm-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                <x-icon name="arrow-left" /><span>{{ __('admin.common.previous') }}</span>
            </a>
        @endif

        <span class="adm-pagination__info" aria-current="page">
            @if (method_exists($paginator, 'lastPage'))
                {{ __('admin.common.page_of', ['page' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}
            @else
                {{ __('admin.common.page', ['page' => $paginator->currentPage()]) }}
            @endif
        </span>

        @if ($paginator->hasMorePages())
            <a class="adm-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                <span>{{ __('admin.common.next') }}</span><x-icon name="arrow-right" />
            </a>
        @else
            <span class="adm-pagination__link is-disabled" aria-disabled="true">
                <span>{{ __('admin.common.next') }}</span><x-icon name="arrow-right" />
            </span>
        @endif
    </nav>
@endif
