{{--
    Maintenance (docs/CMS.md §7.2, §13 E23/F30), rendered by App\Http\Controllers\Admin\MaintenanceController@index.
    Vars: $pending (list of ['name', 'id' (?string), 'label'] — migrations not run yet — or null when the
    database cannot tell), $databaseError (?string), $outage (bool: the database does not answer),
    $log (['ok' => bool, 'output' => string] of the update just run, or null), $environment (array key ⇒ value,
    labels admin.maintenance.environment.{key}).
--}}
@extends('admin.layouts.app')

@section('title', __('admin.maintenance.title'))

@php
    $pendingCount = $pending === null ? null : count($pending);
@endphp

@section('content')
    <x-admin.page-head :title="__('admin.maintenance.title')" :lead="__('admin.maintenance.lead')" />

    {{-- The database updates: "Mettre à jour la base de données" --}}
    <x-admin.card :title="__('admin.maintenance.migrations.title')" :accent="$pendingCount === 0 ? 'yellow' : 'red'" id="maintenance-database">
        <x-slot:actions>
            @if ($pendingCount === 0)
                <x-admin.badge variant="success">{{ __('admin.dashboard.status.migrations_ok') }}</x-admin.badge>
            @elseif ($pendingCount !== null)
                <x-admin.badge variant="new">{{ trans_choice('admin.dashboard.status.migrations_pending', $pendingCount, ['count' => $pendingCount]) }}</x-admin.badge>
            @endif
        </x-slot:actions>

        <div class="adm-stack">
            @if ($pendingCount === null)
                <p><strong>{{ __('admin.maintenance.migrations.unknown') }}</strong></p>
                <p class="adm-muted">{{ __($outage ? 'admin.maintenance.migrations.unknown_outage' : 'admin.maintenance.migrations.unknown_error') }}</p>
            @elseif ($pendingCount > 0)
                <p><strong>{{ trans_choice('admin.maintenance.migrations.pending', $pendingCount, ['count' => $pendingCount]) }}</strong></p>
                <p class="adm-muted">{{ __('admin.maintenance.migrations.pending_lead') }}</p>
                <details class="adm-small" id="maintenance-pending">
                    <summary class="adm-link">{{ __('admin.maintenance.migrations.list') }}</summary>
                    <ul class="adm-stack" role="list" style="--adm-stack-gap: 0.35rem; margin-top: 0.75rem;">
                        @foreach ($pending as $migration)
                            <li>
                                @if ($migration['id'])
                                    <span class="adm-mono adm-muted">{{ $migration['id'] }}</span>
                                @endif
                                {{ $migration['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @else
                <p><strong>{{ __('admin.maintenance.migrations.none') }}</strong></p>
                <p class="adm-muted">{{ __('admin.maintenance.migrations.none_lead') }}</p>
            @endif

            @if ($log)
                <div class="field">
                    @unless ($log['ok'])
                        <p class="adm-muted">{{ __('admin.maintenance.migrations.failed_lead') }}</p>
                    @endunless
                    <label class="field__label" for="maintenance-log">{{ __('admin.maintenance.migrations.output') }}</label>
                    <textarea class="field__input adm-mono adm-small" id="maintenance-log" rows="8" readonly spellcheck="false">{{ $log['output'] }}</textarea>
                </div>
            @endif
        </div>

        <div class="adm-card__foot">
            @if ($pendingCount === 0)
                <a class="btn btn--sm" href="{{ route('admin.dashboard') }}">
                    <span class="btn__label">{{ __('admin.maintenance.migrations.dashboard') }}</span>
                    <x-admin.icon name="arrow-right" />
                </a>
            @else
                <x-admin.confirm :action="route('admin.maintenance.migrate')" method="POST" variant="primary"
                                 :label="__('admin.maintenance.migrations.run')" :message="__('admin.maintenance.migrations.confirm')" />
            @endif
        </div>
    </x-admin.card>

    <div class="adm-grid adm-grid--2">
        <x-admin.card :title="__('admin.maintenance.cache.title')" id="maintenance-cache">
            <p class="adm-muted">{{ __('admin.maintenance.cache.lead') }}</p>
            <div class="adm-card__foot">
                <form method="POST" action="{{ route('admin.maintenance.flush') }}">
                    @csrf
                    <button class="btn btn--sm btn--secondary" type="submit">
                        <x-admin.icon name="refresh" />
                        <span class="btn__label">{{ __('admin.maintenance.cache.flush') }}</span>
                    </button>
                </form>
            </div>
        </x-admin.card>

        {{-- The JSON export comes in phase 2 (admin-shell): the route answers with a note meanwhile. --}}
        <x-admin.card :title="__('admin.maintenance.export.title')" id="maintenance-export">
            <x-slot:actions>
                <x-admin.badge variant="info">{{ __('admin.maintenance.export.badge') }}</x-admin.badge>
            </x-slot:actions>
            <p class="adm-muted">{{ __('admin.maintenance.export.lead') }}</p>
            <div class="adm-card__foot">
                <a class="btn btn--sm btn--ghost" href="{{ route('admin.maintenance.export') }}">
                    <x-admin.icon name="download" />
                    <span class="btn__label">{{ __('admin.maintenance.export.download') }}</span>
                </a>
            </div>
        </x-admin.card>
    </div>

    {{-- Technical details, collapsed (§13 F30): drivers and names only, never a secret. --}}
    <x-admin.card id="maintenance-environment">
        <details class="accordion" @if ($databaseError) open @endif>
            <summary class="accordion__summary">
                <span>{{ __('admin.maintenance.environment.title') }}</span>
                <span class="accordion__icon" aria-hidden="true"></span>
            </summary>
            <div class="accordion__content adm-stack">
                <p class="adm-small">{{ __('admin.maintenance.environment.lead') }}</p>
                <dl class="adm-dl">
                    @foreach ($environment as $key => $value)
                        <dt>{{ __('admin.maintenance.environment.'.$key) }}</dt>
                        <dd class="adm-mono">{{ $value }}</dd>
                    @endforeach
                    @if ($databaseError)
                        <dt>{{ __('admin.maintenance.environment.database_error') }}</dt>
                        <dd class="adm-mono">{{ $databaseError }}</dd>
                    @endif
                </dl>
            </div>
        </details>
    </x-admin.card>
@endsection
