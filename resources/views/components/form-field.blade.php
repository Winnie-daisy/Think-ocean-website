@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'autocomplete' => null,
    'rows' => null,
    'maxlength' => null,
])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = collect([$hint ? $id.'-hint' : null, $error ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div {{ $attributes->class(['space-y-2']) }}>
    <label for="{{ $id }}" class="block text-sm font-semibold text-navy">
        {{ $label }}
        @if ($required)
            <span class="font-normal text-slate-muted">(required)</span>
        @else
            <span class="font-normal text-slate-muted">(optional)</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-slate-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="flex items-start gap-2 text-sm font-medium text-red-700">
            <x-ocean-icon name="exclamation-circle" class="mt-0.5 size-4 shrink-0" />
            <span><span class="sr-only">Error:</span> {{ $error }}</span>
        </p>
    @endif

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 6 }}"
                  @if ($required) required aria-required="true" @endif
                  @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                  @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($error) aria-invalid="true" @endif
                  class="form-input">{{ old($name) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}"
               @if ($required) required aria-required="true" @endif
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @if ($maxlength) maxlength="{{ $maxlength }}" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($error) aria-invalid="true" @endif
               class="form-input">
    @endif
</div>
