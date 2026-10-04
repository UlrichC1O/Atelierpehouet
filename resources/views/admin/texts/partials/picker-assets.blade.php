{{--
    Assets of the photo picker and uploader (admin-media, docs/CMS.md §7.6) for a screen showing photo
    spots or picker fields: pushed once, and only those that exist (each screen works without them —
    the spot falls back to the library page, the field to its <select>).
      @include('admin.texts.partials.picker-assets')
--}}
@once
    @push('styles')
        @if (is_file(public_path('css/admin/media.css')))
            <link rel="stylesheet" href="{{ ap_asset('css/admin/media.css') }}">
        @endif
    @endpush
    @push('scripts')
        @foreach (['js/admin/uploader.js', 'js/admin/media-picker.js'] as $pickerScript)
            @if (is_file(public_path($pickerScript)))
                <script src="{{ ap_asset($pickerScript) }}" defer></script>
            @endif
        @endforeach
    @endpush
@endonce
