@extends('layouts.app')
@section('title', $sector ? 'Editar sector' : 'Nuevo sector SCIAN')

@section('content')
<div class="page-default">
  <div class="screen-head">
    <div><h2 class="nowrap">{{ $sector ? 'Editar sector' : 'Nuevo sector SCIAN' }}</h2></div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.sectores') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST" action="{{ $sector ? route('admin.catalogos.sectores.actualizar', $sector) : route('admin.catalogos.sectores.guardar') }}">
    @csrf
    @if($sector) @method('PUT') @endif
    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">
          <div class="field">
            <label for="codigo">Código SCIAN *</label>
            <input id="codigo" name="codigo" type="text" maxlength="10" required
              placeholder="Ej. 11" value="{{ old('codigo', $sector?->codigo) }}">
            @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
          </div>
          <div class="field">
            <label for="nombre">Nombre del sector *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              placeholder="Ej. Agricultura, cría y explotación de animales"
              value="{{ old('nombre', $sector?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>
        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.sectores') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">{{ $sector ? 'Guardar cambios' : 'Crear sector' }}</button>
      </div>
    </div>
  </form>
</div>
@endsection
