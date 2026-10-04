{{--
    One photo of the library (docs/CMS.md §7.6, §13 C12): large preview with the focal-point picker
    (focal-point.js; the numeric fields work without it), alternative text and caption FR/EN, service,
    gallery, position and name; where the photo appears; "Remplacer la photo" (uploader posting to
    admin.media.replace, same browser processing); details; delete (confirm listing what it removes).
    Vars (Admin\MediaController::editData): $media, $item, $name, $services, $usage, $uploaderName.
--}}
@extends('admin.layouts.app')

@section('title', __('admin_media.edit.title'))

@pushOnce('styles', 'admin-media-css')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/media.css') }}">
@endPushOnce

@pushOnce('scripts', 'admin-focal-point-js')
    <script src="{{ ap_asset('js/admin/focal-point.js') }}" defer></script>
@endPushOnce

@php
    $focalX = (int) old('focal_x', $media->focal_x);
    $focalY = (int) old('focal_y', $media->focal_y);
    $focalX = max(0, min(100, $focalX));
    $focalY = max(0, min(100, $focalY));
    $kilobytes = (int) ceil(max(0, $item->size) / 1024);
    $weight = $kilobytes >= 1024
        ? __('admin_media.card.weight_mb', ['size' => \App\Http\Controllers\Admin\MediaController::megabytes($item->size)])
        : __('admin_media.card.weight', ['size' => $kilobytes]);
    $formatName = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WebP', 'image/gif' => 'GIF'][$item->mime] ?? strtoupper($item->extension);
    $variantWidths = array_map(fn (array $variant): string => $variant['width'].' px', $item->variants);
    $confirmLines = [__('admin_media.edit.delete_confirm', ['name' => $name])];
    if ($usage) {
        $confirmLines[] = __('admin_media.edit.delete_confirm_list');
        foreach ($usage as $place) {
            $confirmLines[] = '— '.$place['label'];
        }
    }
    $confirmLines[] = __('admin_media.edit.delete_confirm_note');
    $previews = ['wide' => '16 / 9', 'portrait' => '4 / 5', 'square' => '1 / 1'];
    $focalValue = __('admin_media.edit.focal_value', ['x' => ':x', 'y' => ':y']);
@endphp

@section('content')
    <x-admin.page-head class="adm-media-head" :title="$name" :lead="__('admin_media.edit.lead')" :back="route('admin.media.index')">
        <a class="btn btn--sm btn--ghost" href="{{ $item->url() }}" target="_blank" rel="noopener">
            <x-icon name="external" />
            <span class="btn__label">{{ __('admin_media.edit.open_original') }}</span>
            <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
        </a>
    </x-admin.page-head>

    <form class="adm-form adm-media-edit" id="media-form" method="POST" action="{{ route('admin.media.update', $media) }}" data-adm-dirty data-adm-busy>
        @csrf
        @method('PUT')

        <div class="adm-media-edit__grid">
            <x-admin.card :title="__('admin_media.edit.sections.focal')" accent="blue" class="adm-media-edit__visual">
                <div class="adm-focal adm-media-edit__focal" data-focal tabindex="0" role="slider"
                     aria-label="{{ __('admin_media.edit.focal_label') }}" aria-describedby="media-focal-help"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $focalX }}"
                     aria-valuetext="{{ __('admin_media.edit.focal_value', ['x' => $focalX, 'y' => $focalY]) }}"
                     data-focal-text="{{ $focalValue }}"
                     style="--x: {{ $focalX }}; --y: {{ $focalY }}; aspect-ratio: {{ $item->ratio() }}">
                    <img src="{{ $item->url(960) }}" srcset="{{ $item->srcset() }}" sizes="(min-width: 64em) 40rem, 92vw"
                         width="{{ $item->width }}" height="{{ $item->height }}" alt="{{ $item->alt('fr') }}" decoding="async">
                    <span class="adm-focal__dot" aria-hidden="true"></span>
                </div>
                <p class="field__hint adm-media-edit__help" id="media-focal-help">{{ __('admin_media.edit.focal_help') }}</p>

                <div class="adm-media-edit__focal-fields">
                    <x-admin.field name="focal_x" type="number" :label="__('admin_media.fields.focal_x')" :value="$focalX" min="0" max="100" step="1" inputmode="numeric" data-focal-input="x" required />
                    <x-admin.field name="focal_y" type="number" :label="__('admin_media.fields.focal_y')" :value="$focalY" min="0" max="100" step="1" inputmode="numeric" data-focal-input="y" required />
                    <button class="btn btn--sm btn--ghost adm-media-edit__center" type="button" data-focal-reset hidden>
                        <x-admin.icon name="focus" />
                        <span class="btn__label">{{ __('admin_media.edit.focal_reset') }}</span>
                    </button>
                </div>

                <div class="adm-crops" aria-label="{{ __('admin_media.edit.previews') }}" role="group">
                    <p class="adm-crops__title">{{ __('admin_media.edit.previews') }}</p>
                    <div class="adm-crops__list">
                        @foreach ($previews as $previewKey => $previewRatio)
                            <figure class="adm-crops__item adm-crops__item--{{ $previewKey }}">
                                <span class="adm-crops__frame" style="aspect-ratio: {{ $previewRatio }}">
                                    <img src="{{ $item->url(480) }}" alt="" loading="lazy" decoding="async" data-focal-preview
                                         style="object-position: {{ $focalX }}% {{ $focalY }}%">
                                </span>
                                <figcaption>{{ __('admin_media.edit.preview_'.$previewKey) }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </x-admin.card>

            <div class="adm-stack adm-media-edit__fields">
                <x-admin.card :title="__('admin_media.edit.sections.texts')" accent="yellow">
                    <div class="adm-pair">
                        <x-admin.field name="alt_fr" type="textarea" :rows="2" lang="fr" :label="__('admin_media.fields.alt')" :value="$media->alt_fr"
                                       :maxlength="300" :hint="__('admin_media.edit.alt_hint')" />
                        <x-admin.field name="alt_en" type="textarea" :rows="2" lang="en" :label="__('admin_media.fields.alt')" :value="$media->alt_en"
                                       :maxlength="300" />
                        <x-admin.field name="caption_fr" type="textarea" :rows="2" lang="fr" :label="__('admin_media.fields.caption')" :value="$media->caption_fr"
                                       :maxlength="500" :hint="__('admin_media.edit.caption_hint')" />
                        <x-admin.field name="caption_en" type="textarea" :rows="2" lang="en" :label="__('admin_media.fields.caption')" :value="$media->caption_en"
                                       :maxlength="500" />
                    </div>
                </x-admin.card>

                <x-admin.card :title="__('admin_media.edit.sections.placement')" accent="amber">
                    <div class="adm-stack">
                        <x-admin.toggle name="in_gallery" :label="__('admin_media.fields.in_gallery')" :checked="$media->in_gallery" :hint="__('admin_media.edit.gallery_hint')" />
                        <x-admin.field name="service_slug" type="select" :label="__('admin_media.fields.service')" :value="$media->service_slug"
                                       :placeholder="__('admin_media.edit.service_none')" :options="$services" :hint="__('admin_media.edit.service_hint')" />
                        <div class="adm-grid adm-grid--2">
                            <x-admin.field name="position" type="number" :label="__('admin_media.fields.position')" :value="$media->position" min="0" step="1" inputmode="numeric"
                                           :hint="__('admin_media.edit.position_hint')" />
                            <x-admin.field name="original_name" :label="__('admin_media.fields.original_name')" :value="$media->original_name" :maxlength="255"
                                           :hint="__('admin_media.edit.name_hint')" />
                        </div>
                    </div>
                </x-admin.card>
            </div>
        </div>

        <x-admin.savebar :back="route('admin.media.index')" />
    </form>

    <div class="adm-media-edit__more">
        <x-admin.card :title="__('admin_media.edit.sections.usage')" accent="red" class="adm-usage" id="media-usage">
            @if ($usage)
                <ul class="adm-usage__list" role="list">
                    @foreach ($usage as $place)
                        <li class="adm-usage__item adm-usage__item--{{ $place['type'] }}">
                            <span class="adm-usage__label">{{ $place['label'] }}</span>
                            <span class="adm-usage__links">
                                @if ($place['admin_url'])
                                    <a href="{{ $place['admin_url'] }}">{{ __('admin_media.edit.usage_admin') }}</a>
                                @endif
                                @if ($place['public_url'])
                                    <a href="{{ $place['public_url'] }}" target="_blank" rel="noopener">{{ __('admin_media.edit.usage_view') }}<span class="visually-hidden"> ({{ __('admin.a11y.new_tab') }})</span></a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="adm-usage__none">
                    <x-admin.badge variant="hidden" class="adm-badge--nowhere">{{ __('admin_media.card.badges.nowhere') }}</x-admin.badge>
                    <span>{{ __('admin_media.edit.usage_none') }}</span>
                </p>
                <p class="field__hint">{{ __('admin_media.edit.usage_hint') }}</p>
            @endif
        </x-admin.card>

        <x-admin.card :title="__('admin_media.edit.sections.replace')" accent="blue" class="adm-media-replace" id="media-replace">
            <p>{{ __('admin_media.edit.replace_text') }}</p>
            @include('admin.media.partials.uploader', [
                'action' => route('admin.media.replace', $media),
                'defaults' => [],
                'multiple' => false,
                'mode' => 'replace',
                'redirect' => route('admin.media.edit', $media),
                'label' => __('admin_media.edit.replace_label'),
            ])
            <p class="field__hint adm-media-replace__note">{{ __('admin_media.edit.replace_note') }}</p>
        </x-admin.card>

        <x-admin.card :title="__('admin_media.edit.sections.details')" class="adm-media-details">
            <dl class="adm-dl">
                <dt>{{ __('admin_media.edit.details.dimensions') }}</dt>
                <dd>{{ __('admin.common.size', ['width' => $item->width, 'height' => $item->height]) }}</dd>
                <dt>{{ __('admin_media.edit.details.weight') }}</dt>
                <dd>{{ $weight }}</dd>
                <dt>{{ __('admin_media.edit.details.format') }}</dt>
                <dd>{{ $formatName }}</dd>
                <dt>{{ __('admin_media.edit.details.variants') }}</dt>
                <dd>{{ $variantWidths ? implode(' · ', $variantWidths) : __('admin_media.edit.details.variants_none') }}</dd>
                @if ($media->created_at)
                    <dt>{{ __('admin_media.edit.details.uploaded') }}</dt>
                    <dd><time datetime="{{ $media->created_at->toIso8601String() }}">{{ $media->created_at->locale(app()->getLocale())->isoFormat('LL, LT') }}</time></dd>
                @endif
                @if ($uploaderName)
                    <dt>{{ __('admin_media.edit.details.uploaded_by') }}</dt>
                    <dd>{{ $uploaderName }}</dd>
                @endif
                @if ($media->updated_at && $media->created_at && ! $media->updated_at->equalTo($media->created_at))
                    <dt>{{ __('admin_media.edit.details.updated') }}</dt>
                    <dd><time datetime="{{ $media->updated_at->toIso8601String() }}">{{ $media->updated_at->locale(app()->getLocale())->isoFormat('LL, LT') }}</time></dd>
                @endif
                <dt>{{ __('admin_media.edit.details.address') }}</dt>
                <dd class="adm-media-details__url"><a href="{{ $item->url() }}" target="_blank" rel="noopener">{{ $item->url() }}</a></dd>
            </dl>
        </x-admin.card>

        <x-admin.card :title="__('admin_media.edit.sections.danger')" accent="red" class="adm-media-danger" id="media-delete">
            <p>{{ __('admin_media.edit.delete_text') }}</p>
            @if ($usage)
                <p>{{ __('admin_media.edit.delete_removes') }}</p>
                <ul class="adm-media-danger__list" role="list">
                    @foreach ($usage as $place)
                        <li>{{ $place['label'] }}</li>
                    @endforeach
                </ul>
            @else
                <p>{{ __('admin_media.edit.delete_nowhere') }}</p>
            @endif
            <p class="field__hint">{{ __('admin_media.edit.delete_note') }}</p>
            <x-admin.confirm :action="route('admin.media.destroy', $media)" :label="__('admin_media.edit.delete_button')"
                             :message="implode(PHP_EOL, $confirmLines)" />
        </x-admin.card>
    </div>
@endsection
