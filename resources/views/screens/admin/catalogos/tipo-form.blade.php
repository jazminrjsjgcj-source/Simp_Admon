@extends('layouts.app')
@section('title', ($item ? 'Editar' : 'Nuevo') . ' — ' . $titulo)

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $item ? 'Editar' : 'Nuevo' }} — {{ $titulo }}</h2>
    </div>
    <div class="head-actions">
      <a href="{{ route($ruta_cancelar) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST"
    action="{{ $item
      ? route($ruta_actualizar, $item)
      : route($ruta_guardar) }}">
    @csrf
    @if($item) @method('PUT') @endif

    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">

          <div class="field">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" type="text" maxlength="100" required
              placeholder="Ej. Reglamento"
              value="{{ old('nombre', $item?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>

          <div class="field">
            <label for="orden">Orden en el listado</label>
            <input id="orden" name="orden" type="number" min="0" max="99"
              value="{{ old('orden', $item?->orden ?? 0) }}">
            <small class="help-small">Número menor aparece primero en los selects.</small>
          </div>

          <div class="field span-2">
            <label for="descripcion">Descripción</label>
            <input id="descripcion" name="descripcion" type="text" maxlength="500"
              placeholder="Descripción breve opcional"
              value="{{ old('descripcion', $item?->descripcion) }}">
          </div>

        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route($ruta_cancelar) }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">
          {{ $item ? 'Guardar cambios' : 'Crear' }}
        </button>
      </div>
    </div>
  </form>

</div>
@endsection
