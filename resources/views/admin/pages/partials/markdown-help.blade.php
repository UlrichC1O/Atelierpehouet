{{-- Short Markdown help of the free-page editor (docs/CMS.md §7.4): a closed <details> with examples. --}}
@php
    $markdownRows = __('admin_content.pages.markdown.rows');
    $markdownRows = is_array($markdownRows) ? $markdownRows : [];
@endphp
<details class="adm-md-help">
    <summary class="adm-md-help__summary">
        <x-admin.icon name="info" />
        <span>{{ __('admin_content.pages.markdown.summary') }}</span>
    </summary>
    <div class="adm-md-help__body">
        <p>{{ __('admin_content.pages.markdown.intro') }}</p>
        <table class="adm-md-help__table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin_content.pages.markdown.write') }}</th>
                    <th scope="col">{{ __('admin_content.pages.markdown.result') }}</th>
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
        <p class="adm-muted adm-small">{{ __('admin_content.pages.markdown.note') }}</p>
    </div>
</details>
