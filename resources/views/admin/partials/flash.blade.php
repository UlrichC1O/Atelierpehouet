{{--
    Flash messages + validation summary (docs/CMS.md §6).
      session('status') ⇒ success · session('error') ⇒ error · session('info') ⇒ info
    Each value is a sentence or a translation key (e.g. 'admin.flash.saved').
    data-adm-flash="success" tells admin.js the last submitted form was saved (its draft goes, §13 D20).
    $summary (default true): list the $errors of the page, each linked to its field
    (#field-… ids, see <x-admin.field>); focused on load by admin.js.
--}}
@php
    $summary = $summary ?? true;
    $flashes = [];

    foreach (['status' => 'success', 'error' => 'error', 'info' => 'info'] as $sessionKey => $flashType) {
        $flashValue = session($sessionKey);

        if (is_string($flashValue) && trim($flashValue) !== '') {
            $flashes[] = [
                'type' => $flashType,
                'text' => preg_match('/^[a-z_]+(\.[a-z0-9_]+)+$/', $flashValue) && app('translator')->has($flashValue) ? __($flashValue) : $flashValue,
            ];
        }
    }

    $errorBag = $summary && isset($errors) ? $errors->getBag('default') : null;
    $errorMessages = $errorBag && $errorBag->isNotEmpty() ? $errorBag->messages() : [];
    $errorCount = count($errorMessages);
    $flashIcons = ['success' => 'success', 'error' => 'error', 'info' => 'info'];
@endphp
@if ($flashes || $errorCount)
    <div class="adm-flashes">
        @foreach ($flashes as $flash)
            <div class="adm-flash adm-flash--{{ $flash['type'] }}" role="{{ $flash['type'] === 'error' ? 'alert' : 'status' }}" data-adm-flash="{{ $flash['type'] }}">
                <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon :name="$flashIcons[$flash['type']]" /></span>
                <p class="adm-flash__text">{{ $flash['text'] }}</p>
                <button class="adm-flash__close" type="button" data-adm-flash-close>
                    <x-icon name="close" />
                    <span class="visually-hidden">{{ __('admin.a11y.dismiss') }}</span>
                </button>
            </div>
        @endforeach

        @if ($errorCount)
            <div class="adm-flash adm-flash--error adm-flash--summary" role="alert" tabindex="-1" data-adm-autofocus>
                <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon name="warning" /></span>
                <div class="adm-flash__body">
                    <p class="adm-flash__title">{{ trans_choice('admin.validation.summary', $errorCount, ['count' => $errorCount]) }}</p>
                    <ul class="adm-flash__list" role="list">
                        @foreach (array_slice($errorMessages, 0, 8, true) as $errorKey => $messages)
                            <li><a href="#field-{{ trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $errorKey), '-') }}">{{ $messages[0] ?? '' }}</a></li>
                        @endforeach
                    </ul>
                    @if ($errorCount > 8)
                        <p class="adm-flash__more">{{ trans_choice('admin.validation.more', $errorCount - 8, ['count' => $errorCount - 8]) }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
