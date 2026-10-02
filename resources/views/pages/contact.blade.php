{{-- Contact & quote request. Posts to ContactController@store (honeypot "website", throttled). --}}
@extends('layouts.app')

@section('title', __('contact.title'))
@section('meta_description', __('contact.meta'))
@section('body_class', 'page-contact')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/contact.css') }}">
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/contact.js') }}" defer></script>
@endpush

@php
    $contact = config('atelier.contact', []);
    $whatsapp = preg_replace('/\D+/', '', (string) ($contact['whatsapp'] ?? ''));
    $grouped = collect(config('atelier.categories', []))->keys()
        ->mapWithKeys(fn ($key) => [__('ui.categories.'.$key) => $services->where('category', $key)])
        ->filter(fn ($items) => $items->isNotEmpty());
    $selectedService = old('service', $selected);
    $sent = session('status') === 'sent';
    $messageMax = 5000;
    $fieldClass = fn (string $name) => $errors->has($name) ? 'field field--invalid' : 'field';
@endphp

@section('content')
    <x-page-hero :eyebrow="__('contact.eyebrow')" :title="__('contact.hero_title')" :lead="__('contact.lead')"
                 :breadcrumbs="[['label' => __('ui.nav.contact')]]" accent="red" compact />

    <section class="section section--flush-top contact">
        <div class="container contact__layout">
            <div class="contact__main" id="contact-form" tabindex="-1">
                @if ($sent)
                    <div class="alert alert--success contact__success" role="status">
                        <svg class="alert__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path pathLength="1" d="M4.5 12.5l5 5 10-11"/></svg>
                        <div>
                            <p class="contact__success-title">{{ __('contact.flash.title') }}</p>
                            <p>{{ __('contact.flash.success') }}</p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert--error" role="alert" data-contact-errors>
                        <div>
                            <p><strong>{{ __('contact.errors_title') }}</strong></p>
                            <ul class="contact__error-list">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form class="contact__form" method="post" action="{{ route('contact.store') }}" novalidate data-contact-form>
                    @csrf
                    <h2 class="h3 contact__form-title">{{ __('contact.form_title') }}</h2>
                    <p class="muted contact__form-intro"><span class="contact__req" aria-hidden="true"></span> {{ __('contact.form_intro') }}</p>

                    <div class="form-grid">
                        <div class="{{ $fieldClass('name') }}">
                            <label class="field__label" for="name"><span class="contact__req" aria-hidden="true"></span> {{ __('contact.fields.name') }} <span class="visually-hidden">({{ __('contact.fields.required') }})</span></label>
                            <input class="field__input" id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}"
                                   placeholder="{{ __('contact.fields.name_placeholder') }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name')<p class="field__error" id="name-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="{{ $fieldClass('email') }}">
                            <label class="field__label" for="email"><span class="contact__req" aria-hidden="true"></span> {{ __('contact.fields.email') }} <span class="visually-hidden">({{ __('contact.fields.required') }})</span></label>
                            <input class="field__input" id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}"
                                   placeholder="{{ __('contact.fields.email_placeholder') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<p class="field__error" id="email-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="{{ $fieldClass('phone') }}">
                            <label class="field__label" for="phone">{{ __('contact.fields.phone') }} <span class="field__optional">{{ __('contact.fields.optional') }}</span></label>
                            <input class="field__input" id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="{{ old('phone') }}"
                                   placeholder="{{ __('contact.fields.phone_placeholder') }}" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')<p class="field__error" id="phone-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="{{ $fieldClass('service') }}">
                            <label class="field__label" for="service">{{ __('contact.fields.service') }} <span class="field__optional">{{ __('contact.fields.optional') }}</span></label>
                            <select class="field__input" id="service" name="service" @error('service') aria-invalid="true" aria-describedby="service-error" @enderror>
                                <option value="">{{ __('contact.fields.service_none') }}</option>
                                @foreach ($grouped as $label => $items)
                                    <optgroup label="{{ $label }}">
                                        @foreach ($items as $item)
                                            <option value="{{ $item['slug'] }}" @selected($selectedService === $item['slug'])>{{ $item['number'] }} · {{ $item['title'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('service')<p class="field__error" id="service-error">{{ $message }}</p>@enderror
                        </div>

                        <fieldset class="choices">
                            <legend class="field__label">{{ __('contact.fields.budget') }} <span class="field__optional">{{ __('contact.fields.optional') }}</span></legend>
                            <div class="choices__list">
                                @foreach ($budgets as $budget)
                                    <label class="choice">
                                        <input type="radio" name="budget" value="{{ $budget }}" @checked(old('budget') === $budget)>
                                        <span>{{ __('contact.budgets.'.$budget) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('budget')<p class="field__error">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="{{ $fieldClass('message') }} field--full">
                            <label class="field__label" for="message"><span class="contact__req" aria-hidden="true"></span> {{ __('contact.fields.message') }} <span class="visually-hidden">({{ __('contact.fields.required') }})</span></label>
                            <textarea class="field__input" id="message" name="message" required minlength="10" maxlength="{{ $messageMax }}" rows="7"
                                      placeholder="{{ __('contact.fields.message_placeholder') }}" data-counter="message-counter"
                                      aria-describedby="message-hint @error('message') message-error @enderror" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                            <p class="field__hint contact__hint-row" id="message-hint">
                                <span>{{ __('contact.fields.message_hint') }}</span>
                                <span class="contact__counter" id="message-counter" data-template="{{ __('contact.counter', ['count' => '__COUNT__', 'max' => $messageMax]) }}" aria-live="off"></span>
                            </p>
                            @error('message')<p class="field__error" id="message-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="contact__honeypot" aria-hidden="true">
                            <label for="website">{{ __('contact.fields.website') }}</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <div class="field--full">
                            <label class="checkbox @error('consent') field--invalid @enderror">
                                <input type="checkbox" name="consent" value="1" required @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                                <span>{{ __('contact.fields.consent') }}</span>
                            </label>
                            @error('consent')<p class="field__error" id="consent-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-actions">
                            <x-button type="submit" size="lg" icon="arrow-right" data-sending-label="{{ __('contact.sending') }}" data-contact-submit>{{ __('contact.submit') }}</x-button>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="contact__aside">
                <div class="contact-card" data-reveal="fade-left">
                    <p class="contact-card__title">{{ __('contact.card.title') }}</p>
                    <ul class="contact-card__list" role="list">
                        @if (! empty($contact['email']))
                            <li><x-icon name="mail" /><span><small>{{ __('contact.card.email') }}</small><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></span></li>
                        @endif
                        @if (! empty($contact['phone']))
                            <li><x-icon name="phone" /><span><small>{{ __('contact.card.phone') }}</small><a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></span></li>
                        @endif
                        @if ($whatsapp !== '')
                            <li><x-icon name="whatsapp" /><span><small>{{ __('contact.card.whatsapp') }}</small><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">{{ __('contact.card.whatsapp_link') }}</a></span></li>
                        @endif
                        @if (! empty($contact['address']))
                            <li><x-icon name="map-pin" /><span><small>{{ __('contact.card.address') }}</small>{{ $contact['address'] }}</span></li>
                        @endif
                    </ul>
                    <p class="contact-card__note">{{ __('contact.card.note') }}</p>
                    <x-mondrian variant="c" class="contact-card__art" />
                </div>
            </aside>
        </div>
    </section>

    <section class="section section--surface contact-next">
        <div class="container">
            <x-section-heading :eyebrow="__('contact.next_eyebrow')" :title="__('contact.next_title')" align="center" accent="red" />
            <ol class="steps" role="list" data-inview>
                @foreach ((array) __('contact.next') as $step)
                    <x-step :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :title="$step['title']" :text="$step['text']" />
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section contact-faq">
        <div class="container container--narrow">
            <x-section-heading :title="__('contact.faq_title')" accent="yellow" />
            @foreach ((array) __('contact.faq') as $item)
                <x-accordion-item :question="$item['q']">{{ $item['a'] }}</x-accordion-item>
            @endforeach
        </div>
    </section>
@endsection
