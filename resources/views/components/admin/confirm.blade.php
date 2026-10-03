{{--
    A one-button form that asks for confirmation first (admin.js opens a dialog on form[data-confirm]).
      <x-admin.confirm :action="route('admin.pages.destroy', $page)" :label="__('admin.actions.delete')"
                       :message="__('admin.confirm.delete')" />
    method: DELETE (default) | PUT | PATCH | POST | GET; variant: danger (default) | primary | secondary | ghost.
    The slot may add hidden inputs. Without JavaScript the form submits directly.
--}}
@props([
    'action',
    'label',
    'message',
    'method' => 'DELETE',
    'variant' => 'danger',
])
@php
    $method = strtoupper((string) $method);
    $buttonClasses = match ($variant) {
        'primary' => 'btn btn--sm',
        'secondary' => 'btn btn--sm btn--secondary',
        'ghost' => 'btn btn--sm btn--ghost',
        default => 'btn btn--sm adm-btn--danger',
    };
@endphp
<form {{ $attributes->class(['adm-confirm'])->merge([
    'method' => $method === 'GET' ? 'GET' : 'POST',
    'action' => $action,
    'data-confirm' => $message,
    'data-confirm-label' => $label,
    'data-confirm-variant' => $variant,
]) }}>
    @if ($method !== 'GET')
        @csrf
    @endif
    @if (! in_array($method, ['GET', 'POST'], true))
        @method($method)
    @endif
    {{ $slot }}
    <button class="{{ $buttonClasses }}" type="submit">
        @if ($variant === 'danger')
            <x-admin.icon name="trash" />
        @endif
        <span class="btn__label">{{ $label }}</span>
    </button>
</form>
