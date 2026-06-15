@extends('layouts.app')
@section('title', 'Nuevo Periodo')
@section('content')
<div style="display:grid;gap:24px;width:min(100%,800px);margin:0 auto">
  <div class="screen-head">
    <div><h2 class="nowrap">Nuevo Periodo</h2><p class="nowrap">Define un periodo de captura para agenda SyD o agenda regulatoria.</p></div>
    <div class="head-actions"><a href="{{ route('admin.periodos') }}" class="btn btn-outline">Cancelar</a></div>
  </div>
  <form method="POST" action="{{ route('admin.periodos.guardar') }}">
    @csrf
    <div class="card"><div class="card-body-padded">
      <div class="wizard-fields">
        <div class="field"><label>Tipo de periodo *</label>
          <select required name="tipo" id="selectTipo" onchange="ajustarDuracion(this.value)">
            <option value="agenda_syd" {{ old('tipo','agenda_syd')==='agenda_syd'?'selected':'' }}>Agenda de Simplificación y Desarrollo (semestral)</option>
            <option value="agenda_regulatoria" {{ old('tipo')==='agenda_regulatoria'?'selected':'' }}>Agenda Regulatoria (anual)</option>
          </select>
          <small class="help-small" id="tipoDuracion">Duración recomendada: 6 meses. Solo puede haber 1 periodo activo de este tipo.</small>
        </div>
        <div class="field span-2"><label>Nombre del periodo *</label>
          <input required name="nombre" value="{{ old('nombre') }}" placeholder="Ej. Periodo SyD Julio-Diciembre 2026">
        </div>
        <div class="field"><label>Fecha de inicio *</label>
          <input required name="fecha_inicio" type="date" id="fechaInicio" value="{{ old('fecha_inicio') }}">
        </div>
        <div class="field"><label>Fecha de cierre *</label>
          <input required name="fecha_fin" type="date" id="fechaFin" value="{{ old('fecha_fin') }}">
        </div>
        <div class="field"><label>Estatus</label>
          <select name="estatus">
            <option value="proximo" {{ old('estatus','proximo')==='proximo'?'selected':'' }}>Próximo</option>
            <option value="activo"  {{ old('estatus')==='activo'?'selected':'' }}>Activo (cierra el actual del mismo tipo)</option>
          </select>
        </div>
        <div class="field span-2"><label>Descripción</label>
          <textarea name="descripcion" rows="3" placeholder="Descripción del periodo...">{{ old('descripcion') }}</textarea>
        </div>
      </div>
      <div class="assist-box" style="margin-top:12px">
        <strong>Regla:</strong> Solo puede haber 1 periodo activo por tipo. Si activa este periodo, el activo actual <em>del mismo tipo</em> se cerrará automáticamente. Puede tener un periodo SyD y uno Regulatorio activos al mismo tiempo.
      </div>
    </div>
    <div class="card-actions card-actions-end">
      <a href="{{ route('admin.periodos') }}" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn">Guardar Periodo</button>
    </div></div>
  </form>
</div>
<script>
function ajustarDuracion(tipo) {
  var help = document.getElementById('tipoDuracion');
  if (tipo === 'agenda_regulatoria') {
    help.textContent = 'Duración recomendada: 12 meses (anual). Solo puede haber 1 periodo activo de este tipo.';
  } else {
    help.textContent = 'Duración recomendada: 6 meses (semestral). Solo puede haber 1 periodo activo de este tipo.';
  }
}
</script>
@endsection
