{{--
  Componente: <x-input-validado />

  Renderiza un <input> con los atributos del patrón de validación
  cargado desde config/validation_patterns.php

  Atributos:
    tipo        → clave del patrón (numero_entero, solo_texto, alfanumerico, etc.)
    name        → name del input
    value       → valor actual
    required    → true/false
    placeholder → texto guía (sobreescribe el del catálogo si se da)
    min, max    → límites numéricos (para numero_entero / numero_decimal)
    minlength, maxlength → límites de caracteres
    label       → texto del label visible
    help        → texto pequeño debajo del input
    id          → id del input
--}}
@props([
    'tipo'        => 'descripcion_libre',
    'name'        => '',
    'value'       => null,
    'required'    => false,
    'placeholder' => null,
    'min'         => null,
    'max'         => null,
    'minlength'   => null,
    'maxlength'   => null,
    'label'       => null,
    'help'        => null,
    'id'          => null,
    'step'        => null,
])

@php
    $patron     = config("validation_patterns.{$tipo}", config('validation_patterns.descripcion_libre'));
    $inputId    = $id ?? ('inp_' . uniqid());
    $ph         = $placeholder ?? $patron['placeholder'];
    $tipoInput  = in_array($tipo, ['numero_entero', 'numero_decimal']) ? 'number' : 'text';
    $stepFinal  = $step ?? ($tipo === 'numero_decimal' ? 'any' : ($tipo === 'numero_entero' ? '1' : null));
@endphp

<div class="field">
    @if($label)
        <label for="{{ $inputId }}">{{ $label }} @if($required)*@endif</label>
    @endif

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $tipoInput }}"
        value="{{ old($name, $value) }}"
        {{ $required ? 'required' : '' }}
        @if($patron['pattern'] && $tipoInput === 'text') pattern="{{ $patron['pattern'] }}" @endif
        @if($patron['inputmode']) inputmode="{{ $patron['inputmode'] }}" @endif
        @if($ph) placeholder="{{ $ph }}" @endif
        @if($min !== null) min="{{ $min }}" @endif
        @if($max !== null) max="{{ $max }}" @endif
        @if($stepFinal !== null) step="{{ $stepFinal }}" @endif
        @if($minlength !== null) minlength="{{ $minlength }}" @endif
        @if($maxlength !== null) maxlength="{{ $maxlength }}" @endif
        data-validacion-tipo="{{ $tipo }}"
        data-validacion-mensaje="{{ $patron['mensaje'] }}"
    >

    @if($help)
        <small class="help-small">{{ $help }}</small>
    @endif
    <small class="help-small input-error-msg" style="display:none;color:#991B1B"></small>
</div>
