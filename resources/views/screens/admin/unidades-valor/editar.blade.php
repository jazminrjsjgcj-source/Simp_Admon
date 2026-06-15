@extends('layouts.app')
@section('title', 'Editar unidad de valor')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar {{ $unidad->unidad }} {{ $unidad->anio }}</h2>
      <p class="nowrap">Valor actual: ${{ number_format($unidad->valor_pesos, 4) }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.unidades-valor.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($errors->any())
    <div class="card-body-padded u-error-box">
      <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.unidades-valor.actualizar', $unidad) }}" id="formUnidad">
    @csrf @method('PUT')
    <div class="card">
      <div class="card-body-padded wizard-fields">

        <div class="field span-2">
          <label>Valor en pesos *</label>
          <input required type="number" step="0.0001" min="0" name="valor_pesos" value="{{ old('valor_pesos', $unidad->valor_pesos) }}">
        </div>

        <div class="field">
          <label>Vigencia desde</label>
          <input type="date" name="vigencia_inicio" value="{{ old('vigencia_inicio', optional($unidad->vigencia_inicio)->format('Y-m-d')) }}">
        </div>

        <div class="field">
          <label>Vigencia hasta</label>
          <input type="date" name="vigencia_fin" value="{{ old('vigencia_fin', optional($unidad->vigencia_fin)->format('Y-m-d')) }}">
        </div>

        <div class="field span-2">
          <label>Fuente</label>
          <input type="text" name="fuente" value="{{ old('fuente', $unidad->fuente) }}">
        </div>

        <div class="field span-2">
          <label>
            <input type="checkbox" name="activo" value="1" {{ old('activo', $unidad->activo) ? 'checked' : '' }}>
            Valor activo
          </label>
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.unidades-valor.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="button" class="btn" onclick="document.getElementById('confirmModal').classList.add('open')">Guardar</button>
      </div>
    </div>
  </form>

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Confirmar actualización?</h3>
      <p>Modificar este valor afectará todos los cálculos futuros de umbrales que se carguen con esta unidad.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formUnidad').submit()">Sí, actualizar</button>
      </div>
    </div>
  </div>

</div>
@endsection
