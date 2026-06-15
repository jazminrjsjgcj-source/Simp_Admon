@extends('layouts.app')
@section('title', 'Editar parámetro')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar parámetro</h2>
      <p class="nowrap"><code>{{ $parametro->clave }}</code></p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.parametros.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($errors->any())
    <div class="card-body-padded u-error-box">
      <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.parametros.actualizar', $parametro) }}" id="formParametro">
    @csrf @method('PUT')
    <div class="card">
      <div class="card-body-padded wizard-fields">

        <div class="field">
          <label>Valor *</label>
          <input required type="number" step="0.0001" min="0" name="valor" value="{{ old('valor', $parametro->valor) }}">
        </div>

        <div class="field">
          <label>Unidad *</label>
          <input required type="text" name="unidad" value="{{ old('unidad', $parametro->unidad) }}" placeholder="pesos, horas, días, factor">
        </div>

        <div class="field span-2">
          <label>Fuente</label>
          <input type="text" name="fuente" value="{{ old('fuente', $parametro->fuente) }}" placeholder="Ej: Salario diario INEGI / 8 hrs">
        </div>

        <div class="field">
          <label>Vigencia desde</label>
          <input type="date" name="vigencia_inicio" value="{{ old('vigencia_inicio', optional($parametro->vigencia_inicio)->format('Y-m-d')) }}">
        </div>

        <div class="field">
          <label>Vigencia hasta</label>
          <input type="date" name="vigencia_fin" value="{{ old('vigencia_fin', optional($parametro->vigencia_fin)->format('Y-m-d')) }}">
        </div>

        <div class="field span-2">
          <label>
            <input type="checkbox" name="activo" value="1" {{ old('activo', $parametro->activo) ? 'checked' : '' }}>
            Parámetro activo (si se desactiva, el sistema usa el valor por defecto del código)
          </label>
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.parametros.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="button" class="btn" onclick="document.getElementById('confirmModal').classList.add('open')">Guardar cambios</button>
      </div>
    </div>
  </form>

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Confirmar cambio?</h3>
      <p>Modificar este parámetro afectará el cálculo de costo burocrático de todos los trámites que se editen a partir de este momento. La acción quedará registrada en bitácora.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formParametro').submit()">Sí, guardar</button>
      </div>
    </div>
  </div>

</div>
@endsection
