@extends('layouts.app')
@section('title', $dependencia ? 'Editar dependencia' : 'Nueva dependencia')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $dependencia ? 'Editar dependencia' : 'Nueva dependencia' }}</h2>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.dependencias') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST"
    action="{{ $dependencia
      ? route('admin.catalogos.dependencias.actualizar', $dependencia)
      : route('admin.catalogos.dependencias.guardar') }}">
    @csrf
    @if($dependencia) @method('PUT') @endif

    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">

          <div class="field">
            <label for="codigo">Código *</label>
            <input id="codigo" name="codigo" type="text" maxlength="10" required
              placeholder="Ej. 000"
              value="{{ old('codigo', $dependencia?->codigo) }}">
            @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
            <small class="help-small">Identificador corto único (máx. 10 caracteres).</small>
          </div>

          <div class="field">
            <label for="nombre">Nombre oficial *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              placeholder="Ej. H. Ayuntamiento de La Paz"
              value="{{ old('nombre', $dependencia?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>

        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.dependencias') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">
          {{ $dependencia ? 'Guardar cambios' : 'Crear dependencia' }}
        </button>
      </div>
    </div>
  </form>

</div>
@endsection
