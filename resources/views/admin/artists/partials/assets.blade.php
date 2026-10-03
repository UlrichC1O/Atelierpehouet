{{--
    Sheets and scripts of the artist screens (docs/ARTISTS.md §6.3, §6.4), pushed once per page:
      @include('admin.artists.partials.assets')                     every artist screen
      @include('admin.artists.partials.assets', ['sortable' => true]) + js/admin/sortable.js (ordered lists)
    The photo library's scripts (uploader.js, media-picker.js) are pushed only once those files exist:
    without them every form still works (plain upload form, <select> of the library photos).
    artists.js comes before media-picker.js so the photo fields are converted before the picker starts.
    Until uploader.js exists, artists.js uploads the photos of the artist screens itself (resized in the
    browser); its messages come from the [data-artist-upload-i18n] element below.
--}}
@php
    $libraryScripts = array_values(array_filter(['js/admin/uploader.js', 'js/admin/media-picker.js'], fn (string $script): bool => is_file(public_path($script))));
@endphp
@once
    @if (! in_array('js/admin/uploader.js', $libraryScripts, true))
        <span hidden data-artist-upload-i18n
              @foreach (['queued', 'preparing', 'uploading', 'done', 'failed', 'too_large', 'unreadable', 'network', 'expired', 'reloading'] as $uploadKey)
                  data-{{ str_replace('_', '-', $uploadKey) }}="{{ __('admin_artists.uploader.'.$uploadKey, ['percent' => ':percent']) }}"
              @endforeach
        ></span>
    @endif
    @push('styles')
        <link rel="stylesheet" href="{{ ap_asset('css/admin/artists.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ ap_asset('js/admin/artists.js') }}" defer></script>
        @foreach ($libraryScripts as $libraryScript)
            <script src="{{ ap_asset($libraryScript) }}" defer></script>
        @endforeach
    @endpush
@endonce
@if ($sortable ?? false)
    @once
        @push('scripts')
            <script src="{{ ap_asset('js/admin/sortable.js') }}" defer></script>
        @endpush
    @endonce
@endif
