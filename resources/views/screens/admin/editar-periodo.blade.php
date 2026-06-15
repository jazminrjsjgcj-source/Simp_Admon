@extends('layouts.app')
@section('title', 'Editar Periodo')
@section('content')
<div class="page-narrow">
  <div class="screen-head">
    <div><h2 class="nowrap">Editar Periodo</h2><p class="nowrap">{{ $periodo->nombre }}</p></div>
    <div class="head-actions"><a href="{{ route('admin.periodos') }}" class="btn btn-outline">Cancelar</a></div>
  </div>

  @if($errors->any())
  <div class="assist-box" style="background:#FEF2F2;border-color:#FCA5A5">
    <ul class="error-list">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.periodos.actualizar', $periodo->id) }}">
    @csrf @method('PUT')
    <div class="card"><div class="card-body-padded">
      <div class="wizard-fields">
        <div class="field">
          <label>Tipo de periodo</label>
          <input type="text" value="{{ $periodo->tipo === 'agenda_regulatoria' ? 'Agenda Regulatoria (anual)' : 'Agenda SyD (semestral)' }}" disabled class="u-input-disabled">
          <small class="help-small">El tipo no se puede cambiar después de creado.</small>
        </div>
        <div class="field span-2">
          <label>Nombre del periodo *</label>
          <input required name="nombre" value="{{ old('nombre', $periodo->nombre) }}">
        </div>
        <div class="field">
          <label>Fecha de inicio *</label>
          <input required name="fecha_inicio" type="date" value="{{ old('fecha_inicio', optional($periodo->fecha_inicio)->format('Y-m-d') ?? $periodo->fecha_inicio) }}">
        </div>
        <div class="field">
          <label>Fecha de fin *</label>
          <input required name="fecha_fin" type="date" value="{{ old('fecha_fin', optional($periodo->fecha_fin)->format('Y-m-d') ?? $periodo->fecha_fin) }}">
        </div>
        <div class="field">
          <label>Estatus</label>
          <select name="estatus">
            <option value="proximo"  {{ old('estatus',$periodo->estatus)==='proximo' ?'selected':'' }}>Próximo</option>
            <option value="activo"   {{ old('estatus',$periodo->estatus)==='activo'  ?'selected':'' }}>Activo (cierra el actual del mismo tipo)</option>
            <option value="cerrado"  {{ old('estatus',$periodo->estatus)==='cerrado' ?'selected':'' }}>Cerrado</option>
          </select>
        </div>
        <div class="field span-2">
          <label>Descripción</label>
          <textarea name="descripcion" rows="2">{{ old('descripcion', $periodo->descripcion) }}</textarea>
        </div>
      </div>
    </div>
    <div class="card-actions card-actions-end">
      <a href="{{ route('admin.periodos') }}" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn">Guardar cambios</button>
    </div></div>
  </form>
</div>
@endsection