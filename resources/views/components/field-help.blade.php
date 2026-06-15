@props(['label', 'name' => null, 'required' => false])
@php
    $helpTexts = config('helpTexts');
    // Try exact match, then without asterisk, then normalized
    $key = $label;
    $helpText = $helpTexts[$key]
        ?? $helpTexts[rtrim($key, ' *')]
        ?? $helpTexts[trim(preg_replace('/\s*\*$/', '', $key))]
        ?? null;
@endphp

<div class="field {{ $attributes->get('class') }}">
    <label>
        {{ $label }}{{ $required ? ' *' : '' }}
        @if($helpText)
            <button type="button" class="field-help-btn"
                onclick="toggleHelp(this)"
                aria-label="Ayuda para {{ $label }}">?</button>
        @endif
    </label>
    @if($helpText)
        <div class="field-help-box">{{ $helpText }}</div>
    @endif
    {{ $slot }}
</div>
