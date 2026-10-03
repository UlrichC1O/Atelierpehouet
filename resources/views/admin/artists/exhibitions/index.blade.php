{{--
    Admin · the exhibitions of one artist (docs/ARTISTS.md §6.2): three groups on today's date — En cours,
    À venir, Passées — each row with its dates, title, kind, venue · city and actions.
    View data: $artist (with artworks_count / exhibitions_count), $groups (['current' | 'upcoming' | 'past' =>
    Collection<App\Models\Exhibition>], ordered like the public page).
--}}
@extends('admin.layouts.app')

@section('title', __('admin_artists.exhibitions.title', ['name' => $artist->name]))
@section('site_url', $artist->url())

@php
    $groupOrder = ['current', 'upcoming', 'past'];
    $total = collect($groupOrder)->sum(fn ($key) => isset($groups[$key]) ? count($groups[$key]) : 0);
    $kindKeys = \App\Models\Exhibition::KINDS;
@endphp

@section('content')
    @include('admin.artists.partials.assets')

    <x-admin.page-head :title="__('admin_artists.exhibitions.title', ['name' => $artist->name])" :lead="__('admin_artists.exhibitions.lead')"
                       :back="route('admin.artists.index')">
        <a class="btn btn--sm" href="{{ route('admin.artists.exhibitions.create', $artist) }}">
            <x-icon name="plus" />
            <span class="btn__label">{{ __('admin_artists.exhibitions.add') }}</span>
        </a>
    </x-admin.page-head>

    @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'exhibitions', 'toggle' => true])

    @if ($total === 0)
        <x-admin.empty :title="__('admin_artists.exhibitions.empty.title')" :text="__('admin_artists.exhibitions.empty.text')" icon="calendar">
            <a class="btn btn--sm" href="{{ route('admin.artists.exhibitions.create', $artist) }}">
                <x-icon name="plus" />
                <span class="btn__label">{{ __('admin_artists.exhibitions.add') }}</span>
            </a>
        </x-admin.empty>
    @else
        @foreach ($groupOrder as $groupKey)
            @php
                $items = $groups[$groupKey] ?? collect();
            @endphp
            @continue(count($items) === 0)
            <section class="adm-artist-section adm-artist-expos adm-artist-expos--{{ $groupKey }}" aria-labelledby="adm-expos-{{ $groupKey }}">
                <div class="adm-artist-section__head">
                    <h2 class="adm-artist-section__title" id="adm-expos-{{ $groupKey }}">
                        @if ($groupKey === 'current')
                            <span class="adm-artist-live" aria-hidden="true"></span>
                        @endif
                        {{ __('admin_artists.exhibitions.groups.'.$groupKey) }}
                        <span class="adm-artist-section__count">{{ count($items) }}</span>
                    </h2>
                </div>

                <ol class="adm-artist-expos__list" role="list">
                    @foreach ($items as $exhibition)
                        @php
                            $expoTitle = $exhibition->text('title') ?? '';
                            $kind = in_array($exhibition->kind, $kindKeys, true) ? $exhibition->kind : 'other';
                            $place = implode(', ', array_filter([$exhibition->venue, $exhibition->city], fn ($part) => is_string($part) && trim($part) !== ''));
                            $hasDates = $exhibition->starts_on !== null;
                            $visual = \App\Artists\ArtistDirectory::media($exhibition->media_id);
                            $editUrl = route('admin.artists.exhibitions.edit', [$artist, $exhibition]);
                        @endphp
                        <li class="adm-artist-expo">
                            <div class="adm-artist-expo__when">
                                <span class="adm-artist-expo__year">{{ $exhibition->year }}</span>
                                @if ($hasDates)
                                    <span class="adm-artist-expo__dates">{{ $exhibition->dates() }}</span>
                                @endif
                            </div>

                            <div class="adm-artist-expo__body">
                                <p class="adm-artist-expo__kind">{{ __('admin_artists.kinds.'.$kind) }}</p>
                                <h3 class="adm-artist-expo__title"><a class="adm-artist-expo__link" href="{{ $editUrl }}"><cite>{{ $expoTitle }}</cite></a></h3>
                                @if ($place !== '')
                                    <p class="adm-artist-expo__place"><x-icon name="map-pin" />{{ $place }}</p>
                                @endif
                                @unless ($exhibition->is_published)
                                    <p class="adm-artist-row__badges"><x-admin.badge variant="hidden">{{ __('admin_artists.status.hidden') }}</x-admin.badge></p>
                                @endunless
                            </div>

                            @if ($visual)
                                <a class="adm-artist-expo__visual" href="{{ $editUrl }}" tabindex="-1" aria-hidden="true">
                                    <img src="{{ $visual->url(480) }}" alt="" width="160" height="100" loading="lazy" decoding="async"
                                         style="object-position: {{ $visual->objectPosition() }}">
                                </a>
                            @endif

                            <div class="adm-artist-expo__actions">
                                <a class="btn btn--sm" href="{{ $editUrl }}">
                                    <x-admin.icon name="edit" />
                                    <span class="btn__label">{{ __('admin_artists.actions.edit') }}<span class="visually-hidden"> — {{ $expoTitle }}</span></span>
                                </a>
                                <form class="adm-artist-inline-form" method="POST" action="{{ route('admin.artists.exhibitions.destroy', [$artist, $exhibition]) }}"
                                      data-confirm="{{ __('admin_artists.exhibitions.delete_confirm', ['title' => $expoTitle]) }}"
                                      data-confirm-label="{{ __('admin_artists.actions.delete') }}" data-confirm-variant="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn--sm btn--ghost adm-artist-work__delete" type="submit">
                                        <x-admin.icon name="trash" />
                                        <span class="btn__label">{{ __('admin_artists.actions.delete') }}<span class="visually-hidden"> — {{ $expoTitle }}</span></span>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    @endif
@endsection
