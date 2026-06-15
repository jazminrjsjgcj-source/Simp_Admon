@props([
    'name',
    'multiple' => false,
    'accept' => '.pdf,.docx,.xlsx,.jpg,.png',
    'maxMb' => 10,
    'required' => false,
])

@php
    // Texto legible de los formatos permitidos (de la lista 'accept').
    $formatosLegibles = collect(explode(',', $accept))
        ->map(fn ($f) => strtoupper(ltrim(trim($f), '.')))
        ->implode(', ');
    // id único para enlazar el input oculto con el componente.
    $uid = 'carga_' . $name . '_' . uniqid();
@endphp

<div class="carga-archivos" data-carga="{{ $uid }}">
    {{-- Input real, oculto: el botón de abajo lo dispara --}}
    <input
        type="file"
        id="{{ $uid }}"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        accept="{{ $accept }}"
        {{ $multiple ? 'multiple' : '' }}
        {{ $required ? 'required' : '' }}
        class="carga-input-oculto"
        onchange="cargaArchivosActualizar('{{ $uid }}')">

    <div class="carga-control">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('{{ $uid }}').click()">
            Seleccionar archivo{{ $multiple ? 's' : '' }}
        </button>
        <span class="carga-estado" id="{{ $uid }}_estado">Ningún archivo seleccionado</span>
    </div>

    <ul class="carga-lista" id="{{ $uid }}_lista">
        {{-- Las filas de archivos se generan al seleccionar --}}
    </ul>

    <p class="carga-reglas">
        Formatos permitidos: {{ $formatosLegibles }}.
        Tamaño máximo por archivo: {{ $maxMb }} MB.
    </p>
</div>
