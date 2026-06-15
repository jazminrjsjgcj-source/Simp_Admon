{{--
  Componente: <x-citar-regulacion :name="'regulacion_id'" :selected="$valor" />

  Muestra un selector con búsqueda de regulaciones convertidas (estatus listo).
  Al elegir una, el campo oculto guarda su ID para asociarla como
  fundamento_juridico del trámite o propuesta regulatoria.

  Atributos:
    name      → name del input (por defecto 'regulacion_id')
    selected  → ID de la regulación previamente seleccionada
    label     → texto del label superior
    required  → true/false
--}}
@props([
    'name'     => 'regulacion_id',
    'selected' => null,
    'label'    => 'Citar regulación del catálogo',
    'required' => false,
])

@php
    $regulacionesCitables = \App\Models\Regulacion::query()
        ->where('conversion_estatus', \App\Models\Regulacion::CONVERSION_LISTO)
        ->where('estatus', \App\Models\Regulacion::ESTATUS_VIGENTE)
        ->orderBy('nombre')
        ->get(['id', 'nombre', 'tipo']);
@endphp

<div class="field span-2">
    <label>{{ $label }} @if($required)*@endif</label>
    <select name="{{ $name }}" {{ $required ? 'required' : '' }}>
        <option value="">— Sin regulación citada —</option>
        @foreach($regulacionesCitables as $reg)
            <option value="{{ $reg->id }}" {{ (string) $selected === (string) $reg->id ? 'selected' : '' }}>
                {{ $reg->tipo ? '[' . $reg->tipo . '] ' : '' }}{{ $reg->nombre }}
            </option>
        @endforeach
    </select>
    @if($regulacionesCitables->isEmpty())
        <small class="help-small">Aún no hay regulaciones convertidas en el catálogo.</small>
    @else
        <small class="help-small">Solo se muestran regulaciones vigentes con conversión completada.</small>
    @endif
</div>
