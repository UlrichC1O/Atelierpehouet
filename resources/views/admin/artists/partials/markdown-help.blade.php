{{--
    Short Markdown help under the biography (docs/ARTISTS.md §6.2): a closed <details> with a few
    examples. The site renders the text through App\Cms\Markdown (HTML stripped).
--}}
@php
    $markdownRows = __('admin_artists.markdown.rows');
    $markdownRows = is_array($markdownRows) ? $markdownRows : [];
@endphp
<details class="adm-artist-help">
    <summary class="adm-artist-help__summary">
        <x-admin.icon name="info" />
        <span>{{ __('admin_artists.markdown.summary') }}</span>
    </summary>
    <div class="adm-artist-help__body">
        <p>{{ __('admin_artists.markdown.intro') }}</p>
        <table class="adm-artist-help__table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin_artists.markdown.write') }}</th>
                    <th scope="col">{{ __('admin_artists.markdown.result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($markdownRows as $markdownRow)
                    <tr>
                        <td><code>{{ $markdownRow['code'] ?? '' }}</code></td>
                        <td>{{ $markdownRow['result'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="adm-muted adm-small">{{ __('admin_artists.markdown.note') }}</p>
    </div>
</details>
