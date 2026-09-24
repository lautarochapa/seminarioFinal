<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
        class="form-control @error($name) is-invalid @enderror"
        @if(($type ?? 'text') !== 'password') value="{{ $value ?? old($name) }}" @endif
        autocomplete="{{ $autocomplete ?? $name }}" required
        @if(($type ?? '') === 'password' && ($autocomplete ?? '') === 'new-password') minlength="8" @endif>
    @error($name)<span class="invalid-feedback" role="alert">{{ $message }}</span>@enderror
    <span class="invalid-feedback" data-field-error="{{ $name }}" role="alert"></span>
</div>
