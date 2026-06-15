@extends('layouts.app')
@section('title', $unidad ? 'Editar unidad' : 'Nueva unidad administrativa')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $unidad ? 'Editar unidad' : 'Nueva unidad administrativa' }}</h2>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.unidades') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST"
    action="{{ $unidad
      ? route('admin.catalogos.unidades.actualizar', $unidad)
      : route('admin.catalogos.unidades.guardar') }}">
    @csrf
    @if($unidad) @method('PUT') @endif

    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">

          <div class="field span-2">
            <label for="dependencia_id">Dependencia *</label>
            <select id="dependencia_id" name="dependencia_id" required>
              <option value="">— Seleccione dependencia —</option>
              @foreach($dependencias as $dep)
                <option value="{{ $dep->id }}"
                  {{ old('dependencia_id', $unidad?->dependencia_id) == $dep->id ? 'selected' : '' }}>
                  {{ $dep->nombre }}
                </option>
              @endforeach
            </select>
            @error('dependencia_id')<span class="field-error">{{ $message }}</span>@enderror
          </div>

          <div class="field">
            <label for="codigo">Código *</label>
            <input id="codigo" name="codigo" type="text" maxlength="10" required
              placeholder="Ej. DGA"
              value="{{ old('codigo', $unidad?->codigo) }}">
            @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
            <small class="help-small">Debe ser único dentro de la dependencia.</small>
          </div>

          <div class="field">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              placeholder="Ej. Dirección General de Administración"
              value="{{ old('nombre', $unidad?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>

        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.unidades') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">
          {{ $unidad ? 'Guardar cambios' : 'Crear unidad' }}
        </button>
      </div>
    </div>
  </form>

</div>
@endsection
