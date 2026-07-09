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

<div class="carga-archivos" data-carga="{{ $uid }}" data-required="{{ $required ? '1' : '0' }}">
    {{-- Input real, oculto: el botón de abajo lo dispara.
         OJO: aunque el campo sea obligatorio, NO se usa el atributo `required`
         nativo de HTML aquí. El input está oculto con display:none, y el
         navegador no puede dar foco a un control oculto para mostrar su mensaje
         de validación: lanza "An invalid form control is not focusable" y el
         envío se rompe en silencio. La obligatoriedad se valida por JS (ver
         carga-archivos.js), que sí puede avisar sin depender del foco. --}}
    <input
        type="file"
        id="{{ $uid }}"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        accept="{{ $accept }}"
        {{ $multiple ? 'multiple' : '' }}
        class="carga-input-oculto"
        onchange="cargaArchivosActualizar('{{ $uid }}')">

    <div class="carga-control">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('{{ $uid }}').click()">
            Seleccionar archivo{{ $multiple ? 's' : '' }}
        </button>
        <span class="carga-estado" id="{{ $uid }}_estado">Ningún archivo seleccionado</span>
    </div>

    {{-- Zona de arrastrar y soltar (#20). El JS captura el drop y vuelca los
         archivos en el input oculto, reusando la misma actualización de lista.
         Funciona como complemento del botón: ambos caminos terminan igual. --}}
    <div class="carga-dropzone" id="{{ $uid }}_dropzone" data-target="{{ $uid }}">
        Arrastra aquí tu{{ $multiple ? 's' : '' }} archivo{{ $multiple ? 's' : '' }}, o usa el botón.
    </div>

    <ul class="carga-lista" id="{{ $uid }}_lista">
        {{-- Las filas de archivos se generan al seleccionar --}}
    </ul>

    <p class="carga-reglas">
        Formatos permitidos: {{ $formatosLegibles }}.
        Tamaño máximo por archivo: {{ $maxMb }} MB.
    </p>
</div>
