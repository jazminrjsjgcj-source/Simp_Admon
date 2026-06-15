@extends('layouts.app')
@section('title', 'Registrar unidad de valor')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Registrar unidad de valor</h2>
      <p class="nowrap">UMA, salario mínimo o UDI para un año específico.</p>
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

  <form method="POST" action="{{ route('admin.unidades-valor.guardar') }}">
    @csrf
    <div class="card">
      <div class="card-body-padded wizard-fields">

        <div class="field">
          <label>Unidad *</label>
          <select required name="unidad">
            <option value="">Seleccione...</option>
            @foreach($tiposUnidad as $t)
              <option value="{{ $t }}" {{ old('unidad') === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label>Año *</label>
          <select required name="anio">
            @foreach($anios as $a)
              <option value="{{ $a }}" {{ old('anio', now()->year) == $a ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
          </select>
        </div>

        <div class="field span-2">
          <label>Valor en pesos *</label>
          <input required type="number" step="0.0001" min="0" name="valor_pesos" value="{{ old('valor_pesos') }}" placeholder="113.14">
        </div>

        <div class="field">
          <label>Vigencia desde</label>
          <input type="date" name="vigencia_inicio" value="{{ old('vigencia_inicio') }}">
        </div>

        <div class="field">
          <label>Vigencia hasta</label>
          <input type="date" name="vigencia_fin" value="{{ old('vigencia_fin') }}">
        </div>

        <div class="field span-2">
          <label>Fuente</label>
          <input type="text" name="fuente" value="{{ old('fuente') }}" placeholder="Ej: INEGI - Valor anual UMA 2026">
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.unidades-valor.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn">Guardar</button>
      </div>
    </div>
  </form>

</div>
@endsection
