{{--
    Admin dashboard (docs/CMS.md §7.2, minimal version — admin-shell adds statistics and activity in
    phase 2), rendered by App\Http\Controllers\Admin\DashboardController.
    Vars: $greeting (string), $pendingCount (int: database updates not applied yet, §13 F30),
    $unread (int), $sections (list of ['key', 'url', 'label', 'text', 'icon', 'accent', 'badge']).
--}}
@extends('admin.layouts.app')

@section('title', __('admin.dashboard.title'))

@section('content')
    <x-admin.page-head :title="$greeting" :lead="__('admin.dashboard.lead')" />

    @if ($pendingCount > 0)
        <x-admin.card :title="__('admin.dashboard.pending.title')" accent="red" id="dashboard-pending">
            <div class="adm-stack">
                <p><strong>{{ trans_choice('admin.maintenance.migrations.pending', $pendingCount, ['count' => $pendingCount]) }}</strong></p>
                <p class="adm-muted">{{ __('admin.dashboard.pending.lead') }}</p>
                <div class="adm-cluster">
                    <x-admin.confirm :action="route('admin.maintenance.migrate')" method="POST" variant="primary"
                                     :label="__('admin.maintenance.migrations.run')" :message="__('admin.maintenance.migrations.confirm')" />
                    <a class="btn btn--sm btn--ghost" href="{{ route('admin.maintenance') }}">
                        <x-admin.icon name="database" />
                        <span class="btn__label">{{ __('admin.dashboard.pending.details') }}</span>
                    </a>
                </div>
            </div>
        </x-admin.card>
    @endif

    @if (\Illuminate\Support\Facades\Route::has('admin.messages.index'))
        <div class="adm-grid adm-grid--stats">
            <x-admin.stat :value="$unread" :label="__('admin.dashboard.stats.unread')" :href="route('admin.messages.index')" accent="red" icon="inbox" />
        </div>
    @endif

    <section class="adm-grid adm-grid--auto" aria-label="{{ __('admin.dashboard.sections.title') }}">
        @foreach ($sections as $section)
            <x-admin.card :title="$section['label']" :accent="$section['accent']" id="dashboard-{{ $section['key'] }}">
                <p class="adm-muted">{{ $section['text'] }}</p>
                <div class="adm-card__foot">
                    @if ($section['badge'] > 0)
                        <x-admin.badge variant="new">{{ trans_choice('admin.nav.unread', $section['badge'], ['count' => $section['badge']]) }}</x-admin.badge>
                    @endif
                    <a class="btn btn--sm btn--ghost" href="{{ $section['url'] }}">
                        <x-admin.icon :name="$section['icon']" />
                        <span class="btn__label">{{ __('admin.dashboard.sections.open') }}<span class="visually-hidden"> — {{ $section['label'] }}</span></span>
                    </a>
                </div>
            </x-admin.card>
        @endforeach
    </section>
@endsection
