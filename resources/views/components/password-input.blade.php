{{--
    Password field with a show/hide (eye) button.
    Usage: <x-password-input name="password" autocomplete="new-password" />
    The toggle itself is handled in resources/js/app.js.
--}}
@props(['name', 'id' => null, 'autocomplete' => 'current-password'])
@php($id = $id ?? $name)

<div class="input-group has-validation">
    <input id="{{ $id }}" type="password" name="{{ $name }}"
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
           required autocomplete="{{ $autocomplete }}">
    <button type="button" class="btn password-toggle" data-password-toggle="{{ $id }}"
            title="Show password" aria-label="Show password" aria-pressed="false">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </button>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
