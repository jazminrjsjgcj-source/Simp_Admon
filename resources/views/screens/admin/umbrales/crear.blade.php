@extends('layouts.app')
@section('title', 'Cargar umbral')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Cargar nuevo umbral</h2>
      <p class="nowrap">Configure el monto contra el cual se evaluará el impacto de los trámites.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.umbrales.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if(session('error'))
    <div class="card-body-padded u-error-box">
      <p style="margin:0;color:#991B1B">{{ session('error') }}</p>
    </div>
  @endif

  @if($errors->any())
    <div class="card-body-padded u-error-box">
      <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.umbrales.guardar') }}">
    @csrf
    <div class="card">
      <div class="card-body-padded wizard-fields">

        <x-selector-scian
          :sector="old('sector_id')"
          :subsector="old('subsector_id')" />

        <div class="field">
          <label>Año *</label>
          <select required name="anio">
            @foreach($anios as $a)
              <option value="{{ $a }}" {{ old('anio', now()->year) == $a ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label>Estado *</label>
          <select required name="estatus">
            <option value="activo"   {{ old('estatus', 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
            <option value="inactivo" {{ old('estatus') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
          </select>
        </div>

        <div class="field">
          <label>Monto base *</label>
          <input required type="number" step="0.0001" min="0" name="monto_base" value="{{ old('monto_base') }}" placeholder="3000000">
        </div>

        <div class="field">
          <label>Unidad base *</label>
          <select required name="unidad_base">
            <option value="pesos"          {{ old('unidad_base', 'pesos') === 'pesos' ? 'selected' : '' }}>Pesos mexicanos</option>
            <option value="UMA"            {{ old('unidad_base') === 'UMA' ? 'selected' : '' }}>UMA</option>
            <option value="salario_minimo" {{ old('unidad_base') === 'salario_minimo' ? 'selected' : '' }}>Salario mínimo</option>
            <option value="UDI"            {{ old('unidad_base') === 'UDI' ? 'selected' : '' }}>UDI</option>
          </select>
          <small class="help-small">El sistema convertirá automáticamente a pesos y las demás unidades usando los valores del año seleccionado.</small>
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
          <label>Fuente del umbral</label>
          <input type="text" name="fuente" value="{{ old('fuente') }}" placeholder="Ej: Acuerdo 04/2026, Oficio CRM/123, Lineamiento art. 36">
        </div>

        <div class="field">
          <label>Fecha del documento fuente</label>
          <input type="date" name="fecha_fuente" value="{{ old('fecha_fuente') }}">
        </div>

        <div class="field span-2">
          <label>Observaciones</label>
          <textarea name="observaciones" rows="3" placeholder="Notas sobre el origen y aplicabilidad del umbral...">{{ old('observaciones') }}</textarea>
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.umbrales.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn">Cargar umbral</button>
      </div>
    </div>
  </form>

</div>
@endsection
