{{--
    Admin style guide (GET /admin/guide, auth): every component and class of the admin design system
    in its states — the visual reference for the admin screens. ?demo=errors shows the error states.
--}}
@extends('admin.layouts.app')

@section('title', __('admin.guide.title'))

@php
    $demoErrors = request()->query('demo') === 'errors';
    if ($demoErrors) {
        $errors = (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
            'title' => [__('admin.guide.samples.error_title')],
            'email' => [__('admin.guide.samples.error_email')],
            't.en.hero.title' => [__('admin.guide.samples.error_pair', ['name' => 'atelier'])],
            'in_gallery' => [__('admin.guide.samples.error_toggle')],
        ]));
        view()->share('errors', $errors);
    }

    // A stand-in for App\Cms\MediaItem (same methods), drawn from the generated artworks.
    $demoMedia = new class
    {
        public int $id = 0;

        public int $width = 800;

        public int $height = 800;

        public string $originalName = 'atelier-fresque.webp';

        public function url(?int $width = null): string
        {
            return asset('generated/gallery/pehouet-2.svg');
        }

        public function srcset(): string
        {
            return asset('generated/gallery/pehouet-2.svg').' 800w';
        }

        public function alt(?string $locale = null): string
        {
            return 'Ateliers Pehouet';
        }

        public function objectPosition(): string
        {
            return '50% 40%';
        }
    };

    $accents = ['blue', 'yellow', 'red', 'orange', 'amber', 'white'];
    $sections = ['colours', 'type', 'buttons', 'badges', 'flash', 'cards', 'forms', 'tabs', 'table', 'media', 'sortable', 'activity', 'dialog', 'empty'];
    $artworks = ['mondrian-1', 'vitrail-2', 'soleil-3', 'prisme-1', 'tissage-2', 'mosaique-4'];
@endphp

@section('content')
    <x-admin.page-head :title="__('admin.guide.title')" :lead="__('admin.guide.lead')">
        @if ($demoErrors)
            <a class="btn btn--sm btn--ghost" href="{{ route('admin.guide') }}">{{ __('admin.guide.errors_off') }}</a>
        @else
            <a class="btn btn--sm btn--ghost" href="{{ route('admin.guide', ['demo' => 'errors']) }}">{{ __('admin.guide.errors_on') }}</a>
        @endif
    </x-admin.page-head>

    <nav aria-label="{{ __('admin.guide.toc') }}">
        <ul class="adm-guide__toc" role="list">
            @foreach ($sections as $section)
                <li><a href="#guide-{{ $section }}">{{ __('admin.guide.sections.'.$section) }}</a></li>
            @endforeach
        </ul>
    </nav>

    {{-- Colours & accents --}}
    <x-admin.card :title="__('admin.guide.sections.colours')" id="guide-colours">
        <div class="adm-guide__swatches">
            @foreach ($accents as $accent)
                <div class="adm-guide__swatch accent-{{ $accent }}">
                    <span class="adm-guide__chip"></span>
                    <code>.accent-{{ $accent }}</code>
                </div>
            @endforeach
            <div class="adm-guide__swatch"><span class="adm-guide__chip adm-guide__chip--surface"></span><code>--ap-coal</code></div>
            <div class="adm-guide__swatch"><span class="adm-guide__chip adm-guide__chip--graphite"></span><code>--ap-graphite</code></div>
            <div class="adm-guide__swatch"><span class="adm-guide__chip adm-guide__chip--steel"></span><code>--ap-steel</code></div>
            <div class="adm-guide__swatch"><span class="adm-guide__chip adm-guide__chip--mist"></span><code>--ap-mist</code></div>
        </div>
        <div class="adm-mondrian" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
    </x-admin.card>

    {{-- Typography --}}
    <x-admin.card :title="__('admin.guide.sections.type')" id="guide-type">
        <div class="adm-guide__type">
            <p class="adm-guide__label"><code>.adm-page-head__title</code> · <code>.adm-card__title</code></p>
            <h3 class="adm-guide__h2">{{ __('admin.guide.samples.heading') }}</h3>
            <p>{{ __('admin.guide.samples.paragraph') }} <a href="#guide-type">{{ __('admin.actions.view') }}</a></p>
            <p class="adm-muted">{{ __('admin.guide.samples.muted') }} <span class="adm-mono">admin.flash.saved</span></p>
            <p class="adm-cluster">
                <span class="adm-lang adm-lang--fr"><span aria-hidden="true">FR</span><span class="visually-hidden">({{ __('admin.common.french') }})</span></span>
                <span class="adm-lang adm-lang--en"><span aria-hidden="true">EN</span><span class="visually-hidden">({{ __('admin.common.english') }})</span></span>
                <span class="adm-lang">.adm-lang</span>
            </p>
        </div>
    </x-admin.card>

    {{-- Buttons --}}
    <x-admin.card :title="__('admin.guide.sections.buttons')" id="guide-buttons">
        <div class="adm-cluster">
            <button class="btn" type="button"><x-admin.icon name="save" /><span class="btn__label">{{ __('admin.actions.save') }}</span></button>
            <button class="btn btn--secondary" type="button"><span class="btn__label">{{ __('admin.actions.add') }}</span></button>
            <button class="btn btn--ghost" type="button"><span class="btn__label">{{ __('admin.actions.cancel') }}</span></button>
            <button class="btn btn--outline accent-red" type="button"><span class="btn__label">{{ __('admin.actions.preview') }}</span></button>
            <button class="btn adm-btn--danger" type="button"><x-admin.icon name="trash" /><span class="btn__label">{{ __('admin.actions.delete') }}</span></button>
            <a class="btn btn--link" href="#guide-buttons">{{ __('admin.actions.view_page') }}</a>
        </div>
        <div class="adm-cluster">
            <button class="btn btn--sm" type="button"><span class="btn__label">{{ __('admin.actions.edit') }}</span></button>
            <button class="btn btn--sm btn--ghost" type="button"><span class="btn__label">{{ __('admin.actions.view') }}</span></button>
            <button class="btn btn--sm is-busy" type="button"><span class="btn__label">{{ __('admin.guide.samples.busy') }}</span></button>
            <button class="btn btn--sm" type="button" disabled><span class="btn__label">{{ __('admin.guide.samples.disabled') }}</span></button>
            <button class="adm-icon-btn adm-icon-btn--outline" type="button"><x-admin.icon name="edit" /><span class="visually-hidden">{{ __('admin.actions.edit') }}</span></button>
            <button class="adm-icon-btn adm-icon-btn--danger" type="button"><x-admin.icon name="trash" /><span class="visually-hidden">{{ __('admin.actions.delete') }}</span></button>
            <button class="adm-icon-btn adm-icon-btn--sm" type="button"><x-admin.icon name="move-up" /><span class="visually-hidden">{{ __('admin.actions.move_up') }}</span></button>
        </div>
        <p class="adm-guide__label"><code>.btn</code> <code>.btn--secondary</code> <code>.btn--ghost</code> <code>.btn--outline</code> <code>.adm-btn--danger</code> <code>.btn--link</code> <code>.btn--sm</code> <code>.is-busy</code> <code>.adm-icon-btn</code></p>
    </x-admin.card>

    {{-- Badges --}}
    <x-admin.card :title="__('admin.guide.sections.badges')" id="guide-badges">
        <div class="adm-cluster">
            <x-admin.badge>{{ __('admin.common.original') }}</x-admin.badge>
            <x-admin.badge variant="new">{{ __('admin.common.new') }}</x-admin.badge>
            <x-admin.badge variant="modified">{{ __('admin.common.modified') }}</x-admin.badge>
            <x-admin.badge variant="hidden">{{ __('admin.common.hidden') }}</x-admin.badge>
            <x-admin.badge variant="custom">{{ __('admin.common.custom') }}</x-admin.badge>
            <x-admin.badge variant="success">{{ __('admin.common.published') }}</x-admin.badge>
            <x-admin.badge variant="danger">{{ __('admin.messages.statuses.new') }}</x-admin.badge>
            <x-admin.badge variant="info">{{ __('admin.common.draft') }}</x-admin.badge>
        </div>
    </x-admin.card>

    {{-- Messages & notifications --}}
    <x-admin.card :title="__('admin.guide.sections.flash')" id="guide-flash">
        <div class="adm-flashes">
            @foreach (['success' => 'flash_success', 'error' => 'flash_error', 'info' => 'flash_info'] as $type => $sample)
                <div class="adm-flash adm-flash--{{ $type }}">
                    <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon :name="$type" /></span>
                    <p class="adm-flash__text">{{ __('admin.guide.samples.'.$sample) }}</p>
                    <button class="adm-flash__close" type="button" data-adm-flash-close>
                        <x-icon name="close" /><span class="visually-hidden">{{ __('admin.a11y.dismiss') }}</span>
                    </button>
                </div>
            @endforeach

            {{-- Written by admin.js (docs/CMS.md §13 D20): a draft put back into its form, a session that is gone. --}}
            <div class="adm-flash adm-flash--info adm-draft">
                <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon name="info" /></span>
                <div class="adm-flash__body">
                    <p>{{ __('admin.js.draft_restored') }}</p>
                    <button class="btn btn--sm btn--ghost adm-draft__discard" type="button">{{ __('admin.js.draft_discard') }}</button>
                </div>
            </div>
            <div class="adm-flash adm-flash--error adm-session">
                <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon name="warning" /></span>
                <div class="adm-flash__body">
                    <p>{{ __('admin.js.session_lost') }}</p>
                    <button class="btn btn--sm adm-session__login" type="button">{{ __('admin.js.session_login') }}</button>
                </div>
            </div>
        </div>
        <div class="adm-cluster">
            <button class="btn btn--sm btn--ghost" type="button" data-adm-toast="{{ __('admin.guide.samples.toast') }}" data-adm-toast-type="success">{{ __('admin.guide.samples.toast_success') }}</button>
            <button class="btn btn--sm btn--ghost" type="button" data-adm-toast="{{ __('admin.guide.samples.flash_error') }}" data-adm-toast-type="error">{{ __('admin.guide.samples.toast_error') }}</button>
        </div>
    </x-admin.card>

    {{-- Cards & figures --}}
    <section class="adm-stack" id="guide-cards" aria-labelledby="guide-cards-title">
        <h2 class="adm-guide__h2" id="guide-cards-title">{{ __('admin.guide.sections.cards') }}</h2>
        <div class="adm-grid adm-grid--stats">
            <x-admin.stat :value="128" :label="__('admin.guide.samples.stat_photos')" :href="route('admin.guide').'#guide-media'" accent="blue" icon="image" />
            <x-admin.stat :value="14" :label="__('admin.guide.samples.stat_texts')" accent="yellow" icon="text" />
            <x-admin.stat :value="__('admin.dashboard.stats.services_value', ['visible' => 22, 'total' => 22])" :label="__('admin.guide.samples.stat_services')" accent="red" icon="services" />
            <x-admin.stat :value="3" :label="__('admin.guide.samples.stat_unread')" :href="route('admin.guide').'#guide-table'" accent="orange" icon="inbox" />
        </div>
        <div class="adm-grid adm-grid--2">
            <x-admin.card :title="__('admin.guide.samples.card_title')">
                <x-slot:actions>
                    <a class="btn btn--sm btn--ghost" href="#guide-cards">{{ __('admin.actions.edit') }}</a>
                </x-slot:actions>
                <p>{{ __('admin.guide.samples.card_text') }}</p>
                <div class="adm-card__foot">
                    <span class="adm-muted adm-small">{{ __('admin.guide.samples.card_foot') }}</span>
                    <button class="btn btn--sm" type="button">{{ __('admin.actions.save') }}</button>
                </div>
            </x-admin.card>
            <x-admin.card :title="__('admin.guide.samples.card_accent')" accent="red">
                <p>{{ __('admin.guide.samples.card_text') }}</p>
                <dl class="adm-dl">
                    <dt>{{ __('admin.maintenance.environment.php') }}</dt><dd>{{ PHP_VERSION }}</dd>
                    <dt>{{ __('admin.maintenance.environment.database') }}</dt><dd>{{ config('database.default') }}</dd>
                    <dt>{{ __('admin.maintenance.environment.media') }}</dt><dd>{{ config('cms.media.driver') }}</dd>
                </dl>
            </x-admin.card>
        </div>
    </section>

    {{-- Forms --}}
    <form class="adm-form" id="guide-forms" method="GET" action="{{ route('admin.guide') }}" data-adm-dirty>
        <h2 class="adm-guide__h2">{{ __('admin.guide.sections.forms') }}</h2>

        <fieldset class="adm-fieldset">
            <legend>{{ __('admin.guide.samples.fieldset') }}</legend>
            <x-admin.field name="title" :label="__('admin.guide.samples.field_title')" :value="__('admin.guide.samples.pair_fr')"
                           :hint="__('admin.guide.samples.field_title_hint')" :maxlength="60" required />
            <div class="adm-grid adm-grid--2">
                <x-admin.field name="email" type="email" :label="__('admin.guide.samples.field_email')" placeholder="atelier@example.org" autocomplete="off" />
                <x-admin.field name="instagram" type="url" :label="__('admin.guide.samples.field_url')" placeholder="https://instagram.com/…" />
                <x-admin.field name="phone" type="tel" :label="__('admin.guide.samples.field_tel')" />
                <x-admin.field name="position" type="number" :label="__('admin.guide.samples.field_number')" :value="3" min="0" />
                <x-admin.field name="demo_password" type="password" :label="__('admin.guide.samples.field_password')" autocomplete="new-password" />
                <x-admin.field name="category" type="select" :label="__('admin.guide.samples.field_select')" :placeholder="__('admin.guide.samples.field_select_placeholder')"
                               value="art-communautaire" :options="[
                                   __('admin.guide.samples.field_group_a') => ['cours-ateliers' => __('admin.guide.samples.option_a'), 'art-communautaire' => __('admin.guide.samples.option_b')],
                                   __('admin.guide.samples.field_group_b') => ['peinture-murale' => __('admin.guide.samples.option_c'), 'sculpture' => __('admin.guide.samples.option_d')],
                               ]" />
            </div>
            <x-admin.field name="intro" type="textarea" :label="__('admin.guide.samples.field_textarea')" :value="__('admin.guide.samples.paragraph')"
                           :hint="__('admin.guide.samples.field_textarea_hint')" :maxlength="400" :rows="2" />
        </fieldset>

        <fieldset class="adm-fieldset">
            <legend>{{ __('admin.guide.samples.pair_label') }}</legend>
            <div class="adm-pair">
                <div class="adm-pair__head"><code>hero.title</code> <x-admin.badge variant="modified">{{ __('admin.common.modified') }}</x-admin.badge></div>
                <div class="adm-stack">
                    <x-admin.field name="t[fr][hero.title]" :label="__('admin.guide.samples.pair_label')" lang="fr" type="textarea" :rows="1"
                                   :value="__('admin.guide.samples.pair_fr')" />
                    <div class="adm-default"><span class="adm-default__label">{{ __('admin.common.default_text') }}</span>{{ __('admin.guide.samples.default_value') }}</div>
                    <label class="adm-reset"><input type="checkbox" name="reset[fr][hero.title]" value="1"> {{ __('admin.actions.reset') }}</label>
                </div>
                <div class="adm-stack">
                    <x-admin.field name="t[en][hero.title]" :label="__('admin.guide.samples.pair_label')" lang="en" type="textarea" :rows="1"
                                   :value="__('admin.guide.samples.pair_en')" />
                    <label class="adm-reset"><input type="checkbox" name="reset[en][hero.title]" value="1"> {{ __('admin.actions.reset') }}</label>
                </div>
            </div>
        </fieldset>

        <fieldset class="adm-fieldset">
            <legend>{{ __('admin.guide.samples.toggle') }}</legend>
            <x-admin.toggle name="in_gallery" :label="__('admin.guide.samples.toggle')" :hint="__('admin.guide.samples.toggle_hint')" checked />
            <x-admin.toggle name="is_published" :label="__('admin.guide.samples.toggle_b')" />
            <label class="checkbox"><input type="checkbox" name="remember_demo" value="1"> <span>{{ __('admin.guide.samples.checkbox') }}</span></label>
            <div class="choices" role="radiogroup" aria-label="{{ trim(__('admin.slot.ratio', ['ratio' => ''])) }}">
                <div class="choices__list">
                    <label class="choice"><input type="radio" name="ratio" value="landscape" checked><span>{{ __('admin.guide.samples.choice_a') }}</span></label>
                    <label class="choice"><input type="radio" name="ratio" value="portrait"><span>{{ __('admin.guide.samples.choice_b') }}</span></label>
                    <label class="choice"><input type="radio" name="ratio" value="square"><span>{{ __('admin.guide.samples.choice_c') }}</span></label>
                </div>
            </div>
        </fieldset>

        <x-admin.savebar :back="route('admin.guide')">{{ __('admin.guide.samples.savebar') }}</x-admin.savebar>
    </form>

    {{-- Tabs --}}
    <x-admin.card :title="__('admin.guide.sections.tabs')" id="guide-tabs">
        <div class="adm-tabs" data-adm-tabs="guide">
            <div class="adm-tabs__list">
                <button class="adm-tabs__tab" type="button" aria-controls="guide-tab-fr"><span class="adm-lang adm-lang--fr" aria-hidden="true">FR</span>{{ __('admin.guide.samples.tab_fr') }}</button>
                <button class="adm-tabs__tab" type="button" aria-controls="guide-tab-en"><span class="adm-lang adm-lang--en" aria-hidden="true">EN</span>{{ __('admin.guide.samples.tab_en') }}</button>
            </div>
            <section class="adm-tabs__panel" id="guide-tab-fr">
                <h3 class="adm-tabs__heading">{{ __('admin.guide.samples.tab_fr') }}</h3>
                <p>{{ __('admin.guide.samples.tab_fr_text') }}</p>
            </section>
            <section class="adm-tabs__panel" id="guide-tab-en">
                <h3 class="adm-tabs__heading">{{ __('admin.guide.samples.tab_en') }}</h3>
                <p>{{ __('admin.guide.samples.tab_en_text') }}</p>
            </section>
        </div>
    </x-admin.card>

    {{-- Table --}}
    <section class="adm-stack" id="guide-table" aria-labelledby="guide-table-title">
        <div class="adm-toolbar">
            <h2 class="adm-guide__h2" id="guide-table-title">{{ __('admin.guide.sections.table') }}</h2>
            <label class="adm-search">
                <span class="visually-hidden">{{ __('admin.guide.samples.filter_label') }}</span>
                <x-admin.icon name="search" />
                <input class="field__input" type="search" placeholder="{{ __('admin.common.search_placeholder') }}" data-adm-filter="#guide-table-rows" data-adm-filter-count="#guide-table-count" data-adm-filter-empty="#guide-table-empty">
            </label>
        </div>
        <p class="adm-muted adm-small" id="guide-table-count" aria-live="polite">{{ trans_choice('admin.common.results', 3, ['count' => 3]) }}</p>
        <table class="adm-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.guide.samples.table_title') }}</th>
                    <th scope="col">{{ __('admin.guide.samples.table_status') }}</th>
                    <th scope="col">{{ __('admin.guide.samples.table_updated') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ __('admin.common.actions') }}</span></th>
                </tr>
            </thead>
            <tbody id="guide-table-rows">
                @foreach ([['row_1', 'success', 'published'], ['row_2', 'hidden', 'draft'], ['row_3', 'modified', 'modified']] as [$row, $variant, $status])
                    <tr data-adm-filter-item>
                        <td class="adm-table__main"><a href="#guide-table">{{ __('admin.guide.samples.'.$row) }}</a></td>
                        <td data-label="{{ __('admin.guide.samples.table_status') }}"><x-admin.badge :variant="$variant">{{ __('admin.common.'.$status) }}</x-admin.badge></td>
                        <td data-label="{{ __('admin.guide.samples.table_updated') }}">{{ now()->subDays($loop->index * 3)->translatedFormat('j M Y') }}</td>
                        <td class="adm-table__actions">
                            <div class="adm-cluster">
                                <a class="btn btn--sm btn--ghost" href="#guide-table">{{ __('admin.actions.edit') }}</a>
                                <x-admin.confirm :action="route('admin.guide')" method="GET" :label="__('admin.actions.delete')" :message="__('admin.guide.samples.confirm_message')" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <x-admin.empty :title="__('admin.empty.search')" icon="search" id="guide-table-empty" hidden />
    </section>

    {{-- Photos --}}
    <section class="adm-stack" id="guide-media" aria-labelledby="guide-media-title">
        <h2 class="adm-guide__h2" id="guide-media-title">{{ __('admin.guide.sections.media') }}</h2>

        <div class="adm-cluster">
            <x-admin.thumb :media="$demoMedia" :size="96" alt="" />
            <x-admin.thumb :media="$demoMedia" :size="64" alt="" />
            <x-admin.thumb :media="null" :size="96" />
            <x-admin.thumb :media="null" :size="48" />
        </div>

        <ul class="adm-media-grid" role="list">
            @foreach ($artworks as $artwork)
                <li @class(['adm-media-card', 'is-selected' => $loop->index === 1])>
                    <a class="adm-media-card__media" href="#guide-media">
                        <img class="adm-media-card__img" src="{{ asset('generated/gallery/'.$artwork.'.svg') }}" alt="" width="800" height="800" loading="lazy" decoding="async">
                    </a>
                    <div class="adm-media-card__body">
                        <p class="adm-media-card__name">{{ $artwork }}.webp</p>
                        <p class="adm-media-card__meta">{{ __('admin.common.size', ['width' => 1600, 'height' => 1600]) }}</p>
                        <div class="adm-media-card__badges">
                            @if ($loop->index % 2 === 0)<x-admin.badge variant="success">{{ __('admin.nav.gallery') }}</x-admin.badge>@endif
                            @if ($loop->index === 2)<x-admin.badge variant="custom">{{ __('admin.nav.services') }}</x-admin.badge>@endif
                        </div>
                    </div>
                    <div class="adm-media-card__actions">
                        <a class="btn btn--sm btn--ghost" href="#guide-media">{{ __('admin.actions.edit') }}</a>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="adm-grid adm-grid--2">
            @include('admin.media.partials.slot', ['slot' => 'home.feature', 'label' => __('admin.slots.home.feature'), 'media' => null, 'ratio' => '4/3', 'redirect' => route('admin.guide')])
            @include('admin.media.partials.slot', ['slot' => 'about.portrait', 'label' => __('admin.slots.about.portrait'), 'media' => $demoMedia, 'ratio' => '4/5', 'redirect' => route('admin.guide')])
        </div>

        @include('admin.media.partials.uploader', ['defaults' => ['in_gallery' => 1], 'multiple' => true, 'redirect' => route('admin.guide')])

        <p class="adm-guide__label">{{ __('admin.guide.samples.dropzone_states') }} · <code>.is-dragover</code> <code>.is-busy</code></p>
        <div class="adm-grid adm-grid--2">
            @foreach (['is-dragover', 'is-busy'] as $state)
                <div class="adm-dropzone {{ $state }}" aria-hidden="true">
                    <div class="adm-dropzone__zone">
                        <span class="adm-dropzone__art"><span class="adm-dropzone__tri"></span><x-admin.icon name="upload" /></span>
                        <p class="adm-dropzone__title">{{ __('admin.uploader.drop') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="adm-grid adm-grid--2">
            <div class="adm-stack">
                <p class="adm-guide__label">{{ __('admin.guide.samples.uploads_title') }} · <code>.adm-uploads</code> <code>.adm-progress</code></p>
                <ol class="adm-uploads" role="list">
                    <li class="adm-uploads__item is-uploading">
                        <img class="adm-uploads__thumb" src="{{ asset('generated/gallery/eclats-1.svg') }}" alt="">
                        <span class="adm-uploads__name">fresque-ecole.jpg</span>
                        <span class="adm-uploads__status">{{ __('admin.uploader.statuses.uploading', ['percent' => 64]) }}</span>
                        <progress class="adm-progress" max="100" value="64">64 %</progress>
                    </li>
                    <li class="adm-uploads__item">
                        <img class="adm-uploads__thumb" src="{{ asset('generated/gallery/soleil-1.svg') }}" alt="">
                        <span class="adm-uploads__name">atelier-enfants.heic</span>
                        <span class="adm-uploads__status">{{ __('admin.uploader.statuses.processing') }}</span>
                        <progress class="adm-progress"></progress>
                    </li>
                    <li class="adm-uploads__item is-done">
                        <img class="adm-uploads__thumb" src="{{ asset('generated/gallery/vitrail-1.svg') }}" alt="">
                        <span class="adm-uploads__name">vitrail-chapelle.png</span>
                        <span class="adm-uploads__status">{{ __('admin.uploader.statuses.done') }}</span>
                        <progress class="adm-progress" max="100" value="100">100 %</progress>
                    </li>
                    <li class="adm-uploads__item is-error">
                        <img class="adm-uploads__thumb" src="{{ asset('generated/gallery/tissage-1.svg') }}" alt="">
                        <span class="adm-uploads__name">document.pdf</span>
                        <span class="adm-uploads__status">{{ __('admin.uploader.statuses.failed') }}</span>
                        <span class="adm-uploads__error">{{ __('admin.guide.samples.flash_error') }}</span>
                    </li>
                </ol>
            </div>
            <div class="adm-stack">
                <p class="adm-guide__label">{{ __('admin.guide.samples.focal') }} · <code>.adm-focal</code></p>
                <div class="adm-focal adm-guide__focal" style="--x: 34; --y: 42">
                    <img src="{{ asset('generated/gallery/mondrian-2.svg') }}" alt="" width="800" height="800">
                    <span class="adm-focal__dot" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- Sortable list --}}
    <x-admin.card :title="__('admin.guide.sections.sortable')" id="guide-sortable">
        <p class="adm-muted">{{ __('admin.guide.samples.sortable_hint') }}</p>
        <form method="GET" action="{{ route('admin.guide') }}">
            <ol class="adm-sortable" data-sortable>
                @foreach (__('admin.guide.samples.sortable_items') as $index => $colour)
                    <li class="adm-sortable__item" data-sortable-item data-sortable-value="{{ $index + 1 }}">
                        <button class="adm-handle" type="button" data-sortable-handle>
                            <x-admin.icon name="drag" /><span class="visually-hidden">{{ __('admin.a11y.drag') }} — {{ $colour }}</span>
                        </button>
                        <span class="adm-sortable__number" data-sortable-number>{{ sprintf('%02d', $index + 1) }}</span>
                        <span class="adm-sortable__label">{{ $colour }}</span>
                        <span class="adm-sortable__moves">
                            <button class="adm-icon-btn adm-icon-btn--sm" type="button" data-move="up"><x-admin.icon name="move-up" /><span class="visually-hidden">{{ __('admin.actions.move_up') }} — {{ $colour }}</span></button>
                            <button class="adm-icon-btn adm-icon-btn--sm" type="button" data-move="down"><x-admin.icon name="move-down" /><span class="visually-hidden">{{ __('admin.actions.move_down') }} — {{ $colour }}</span></button>
                        </span>
                    </li>
                @endforeach
            </ol>
        </form>
    </x-admin.card>

    {{-- Activity & pagination --}}
    <x-admin.card :title="__('admin.guide.sections.activity')" id="guide-activity">
        <ol class="adm-timeline" role="list">
            @foreach ([['timeline_1', 'blue', 12], ['timeline_2', 'yellow', 95], ['timeline_3', 'red', 1440]] as [$entry, $accent, $minutes])
                <li class="adm-timeline__item accent-{{ $accent }}">
                    <span class="adm-timeline__dot" aria-hidden="true"></span>
                    <p class="adm-timeline__text">{{ __('admin.guide.samples.'.$entry) }}</p>
                    <time class="adm-timeline__time" datetime="{{ now()->subMinutes($minutes)->toIso8601String() }}">{{ now()->subMinutes($minutes)->diffForHumans() }}</time>
                    <p class="adm-timeline__meta">{{ __('admin.activity.by', ['name' => auth()->user()?->name ?? __('admin.activity.system')]) }}</p>
                </li>
            @endforeach
        </ol>
        <nav class="adm-pagination" aria-label="{{ __('admin.a11y.pagination') }}">
            <span class="adm-pagination__link is-disabled" aria-disabled="true"><x-icon name="arrow-left" /><span>{{ __('admin.common.previous') }}</span></span>
            <span class="adm-pagination__info">{{ __('admin.common.page_of', ['page' => 1, 'total' => 4]) }}</span>
            <a class="adm-pagination__link" href="#guide-activity" rel="next"><span>{{ __('admin.common.next') }}</span><x-icon name="arrow-right" /></a>
        </nav>
    </x-admin.card>

    {{-- Dialog --}}
    <x-admin.card :title="__('admin.guide.sections.dialog')" id="guide-dialog">
        <div class="adm-cluster">
            <button class="btn btn--sm btn--secondary" type="button" data-adm-dialog-open="#guide-dialog-box">{{ __('admin.guide.samples.dialog_open') }}</button>
            <x-admin.confirm :action="route('admin.guide')" method="GET" :label="__('admin.guide.samples.confirm_label')" :message="__('admin.guide.samples.confirm_message')" />
        </div>
        <dialog class="adm-dialog" id="guide-dialog-box" aria-labelledby="guide-dialog-title">
            <div class="adm-dialog__head">
                <h2 class="adm-dialog__title" id="guide-dialog-title">{{ __('admin.guide.samples.dialog_title') }}</h2>
                <button class="adm-icon-btn" type="button" data-adm-dialog-close><x-icon name="close" /><span class="visually-hidden">{{ __('admin.actions.close') }}</span></button>
            </div>
            <div class="adm-dialog__body">
                <p>{{ __('admin.guide.samples.dialog_text') }}</p>
                <x-admin.field name="dialog_note" :label="__('admin.guide.samples.field_title')" />
            </div>
            <div class="adm-dialog__foot">
                <button class="btn btn--sm btn--ghost" type="button" data-adm-dialog-close>{{ __('admin.actions.cancel') }}</button>
                <button class="btn btn--sm" type="button" data-adm-dialog-close value="ok">{{ __('admin.actions.confirm') }}</button>
            </div>
        </dialog>
    </x-admin.card>

    {{-- Empty state --}}
    <section class="adm-stack" id="guide-empty" aria-labelledby="guide-empty-title">
        <h2 class="adm-guide__h2" id="guide-empty-title">{{ __('admin.guide.sections.empty') }}</h2>
        <x-admin.empty :title="__('admin.guide.samples.empty_title')" :text="__('admin.guide.samples.empty_text')" icon="image">
            <a class="btn btn--sm" href="#guide-media"><x-admin.icon name="upload" /><span class="btn__label">{{ __('admin.uploader.title') }}</span></a>
        </x-admin.empty>
    </section>
@endsection

@once
    @push('scripts')
        <script src="{{ ap_asset('js/admin/sortable.js') }}" defer></script>
    @endpush
@endonce
