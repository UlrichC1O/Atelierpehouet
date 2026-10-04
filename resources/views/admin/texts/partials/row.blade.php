{{--
    One text of the editor: its label, then the French and English fields side by side (.adm-pair),
    each with the original text (shown when the text differs from it), the placeholders to keep and,
    when a change is saved, "Rétablir l'original".
    Vars: $row (see TextController::editData()), $metaLength. The ids follow <x-admin.field>
    (field-t-{locale}-{key}), so the error summary of the layout links to the fields.
--}}
<div @class(['adm-text-row', 'is-modified' => $row['modified']]) id="{{ $row['anchor'] }}"
     data-adm-filter-item data-adm-filter-text="{{ $row['search'] }}" data-text-row>
    <div class="adm-pair">
        <div class="adm-pair__head adm-text-row__head">
            <span class="adm-text-row__label">{{ $row['label'] }}</span>
            @if ($row['hint'])
                <code class="adm-text-row__key" title="{{ __('admin_content.texts.key', ['key' => $row['hint']]) }}">{{ $row['hint'] }}</code>
            @endif
            <span class="adm-badge adm-badge--modified" data-text-badge @if (! $row['modified']) hidden @endif>{{ __('admin.common.modified') }}</span>
        </div>

        @foreach ($row['cells'] as $locale => $cell)
            @php
                $cellError = $errors->first($cell['error_key']);
                $cellError = $cellError !== '' ? $cellError : null;
                $keep = $cell['placeholders'];
                $showKeep = $keep !== [] || $cell['plural'];
                $differs = trim(str_replace(["\r\n", "\r"], "\n", $cell['value'])) !== trim(str_replace(["\r\n", "\r"], "\n", $cell['default']))
                    && trim($cell['value']) !== '';
                $describedBy = trim(($showKeep || $row['meta'] ? $cell['id'].'-hint ' : '').($cellError ? $cell['id'].'-error' : ''));
            @endphp
            <div @class(['field', 'adm-field', 'adm-text-cell', 'field--invalid' => $cellError, 'is-reset' => $cell['reset']]) data-text-cell>
                <label class="field__label adm-text-cell__label" for="{{ $cell['id'] }}">
                    <span class="adm-lang adm-lang--{{ $locale }}" aria-hidden="true">{{ strtoupper($locale) }}</span>
                    <span class="visually-hidden">{{ $row['label'] }} —</span>
                    <span class="adm-text-cell__lang">{{ __('admin_content.texts.language.'.$locale) }}</span>
                </label>

                @if ($cell['multiline'])
                    <textarea class="field__input" id="{{ $cell['id'] }}" name="{{ $cell['name'] }}" lang="{{ $locale }}" rows="2" data-autosize data-text-input
                              @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @if ($cellError) aria-invalid="true" @endif @if ($row['meta']) data-maxlength="{{ $metaLength }}" @endif @if ($keep !== []) data-placeholders="{{ json_encode($keep) }}" @endif @if ($cell['plural']) data-pipes="{{ substr_count($cell['default'], '|') }}" @endif @if ($cell['reset']) readonly @endif>{{ $cell['value'] }}</textarea>
                @else
                    <input class="field__input" type="text" id="{{ $cell['id'] }}" name="{{ $cell['name'] }}" lang="{{ $locale }}" value="{{ $cell['value'] }}" data-text-input
                           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @if ($cellError) aria-invalid="true" @endif @if ($row['meta']) data-maxlength="{{ $metaLength }}" @endif @if ($keep !== []) data-placeholders="{{ json_encode($keep) }}" @endif @if ($cell['plural']) data-pipes="{{ substr_count($cell['default'], '|') }}" @endif @if ($cell['reset']) readonly @endif>
                @endif

                @if ($showKeep || $row['meta'])
                    <p class="field__hint adm-text-cell__hint" id="{{ $cell['id'] }}-hint">
                        @if ($row['meta'])
                            <span>{{ __('admin_content.texts.meta_hint') }}</span>
                        @endif
                        @if ($showKeep)
                            <span class="adm-text-cell__keep" data-text-keep>{{ __('admin_content.texts.keep', ['tokens' => implode(' ', array_merge(
                                array_map(fn ($token) => '« '.$token.' »', $keep),
                                $cell['plural'] ? [__('admin_content.texts.keep_plural')] : [],
                            ))]) }}</span>
                        @endif
                    </p>
                @endif

                @if ($cellError)
                    <p class="field__error" id="{{ $cell['id'] }}-error">{{ $cellError }}</p>
                @endif

                <div class="adm-default adm-text-cell__default" data-text-default @if (! $differs && $cell['override'] === null) hidden @endif><span class="adm-default__label">{{ __('admin_content.texts.original') }}</span><span lang="{{ $locale }}" data-text-default-value>{{ $cell['default'] }}</span></div>

                @if ($cell['override'] !== null)
                    <label class="adm-reset" title="{{ __('admin_content.texts.reset_hint') }}">
                        <input type="checkbox" name="{{ $cell['reset_name'] }}" value="1" data-text-reset @checked($cell['reset'])>
                        {{ __('admin_content.texts.reset') }}
                    </label>
                @endif
            </div>
        @endforeach
    </div>
</div>
