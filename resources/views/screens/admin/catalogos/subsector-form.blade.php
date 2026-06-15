@extends('layouts.app')
@section('title', $subsector ? 'Editar subsector' : 'Nuevo subsector')

@section('content')
<div class="page-default">
  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $subsector ? 'Editar subsector' : 'Nuevo subsector' }}</h2>
      <p class="nowrap">Sector: {{ $sector->codigo }} — {{ $sector->nombre }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.subsectores', $sector) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST" action="{{ $subsector
    ? route('admin.catalogos.subsectores.actualizar', [$sector, $subsector])
    : route('admin.catalogos.subsectores.guardar', $sector) }}">
    @csrf
    @if($subsector) @method('PUT') @endif
    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">
          <div class="field">
            <label for="codigo">Código SCIAN *</label>
            <input id="codigo" name="codigo" type="text" maxlength="10" required
              placeholder="Ej. 111" value="{{ old('codigo', $subsector?->codigo) }}">
            @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
          </div>
          <div class="field">
            <label for="nombre">Nombre del subsector *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              value="{{ old('nombre', $subsector?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>
        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.subsectores', $sector) }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">{{ $subsector ? 'Guardar cambios' : 'Crear subsector' }}</button>
      </div>
    </div>
  </form>
</div>
@endsection
