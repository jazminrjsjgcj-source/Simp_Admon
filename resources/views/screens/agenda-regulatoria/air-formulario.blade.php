@extends('layouts.app')
@section('title', $air ? 'Editar AIR' : 'Nuevo AIR')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Análisis de Impacto Regulatorio</h2>
      <p class="nowrap">{{ $propuesta->nombre }} — Art. 38 LNETB (7 elementos)</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">← Volver</a>
    </div>
  </div>

  {{-- Estado actual del AIR --}}
  @if($air)
    <div class="assist-box">
      <strong>Folio:</strong> {{ $air->folio }} —
      <strong>Estatus:</strong> {{ ucfirst(str_replace('_', ' ', $air->estatus)) }}
      @if($air->estatus === 'dictaminado')
        — <strong>Dictamen:</strong>
        <span class="badge {{ $air->dictamen === 'favorable' ? 'success-b' : 'danger-b' }}">
          {{ ucfirst(str_replace('_', ' ', $air->dictamen)) }}
        </span>
      @endif
    </div>
  @endif

  <form method="POST" action="{{ route('air.guardar', $propuesta) }}" id="airForm">
    @csrf
    @if($air) @method('POST') @endif

    <div id="airWizard">
      {{-- Barra lateral de pasos --}}
      <div class="wizard-sidebar">
        @foreach(\App\Models\AnalisisImpactoRegulatorio::ELEMENTOS_ART38 as $campo => $etiqueta)
          <div class="wizard-step {{ $loop->first ? 'active' : '' }}" data-step="{{ $loop->index + 1 }}">
            <span class="wizard-dot"></span>
            <div>
              <strong>{{ $etiqueta }}</strong>
              <small>Campo {{ $loop->index + 1 }} de 7</small>
            </div>
          </div>
        @endforeach
      </div>

      <div class="wizard-panel">
        {{-- Paneles de los 7 elementos --}}
        @foreach(\App\Models\AnalisisImpactoRegulatorio::ELEMENTOS_ART38 as $campo => $etiqueta)
        <div class="wizard-content {{ $loop->first ? 'active' : '' }}" data-panel="{{ $loop->index + 1 }}">
          <div class="wizard-panel-head">
            <span class="wizard-panel-icon"></span>
            <div>
              <h3>{{ $etiqueta }}</h3>
              <p>{{ \App\Models\AnalisisImpactoRegulatorio::ELEMENTOS_ART38[$campo] }}</p>
            </div>
          </div>
          <div class="wizard-fields">
            <div class="field span-2">
              <label for="{{ $campo }}">{{ $etiqueta }}</label>
              <textarea id="{{ $campo }}" name="{{ $campo }}" rows="8"
                placeholder="Desarrolle este elemento del AIR...">{{ old($campo, $air?->{$campo}) }}</textarea>
              @error($campo)
                <span class="field-error">{{ $message }}</span>
              @enderror
            </div>
          </div>
        </div>
        @endforeach

        {{-- Paso final: datos complementarios --}}
        <div class="wizard-content" data-panel="8">
          <div class="wizard-panel-head">
            <span class="wizard-panel-icon"></span>
            <div><h3>Datos complementarios</h3><p>Sector, población y contexto.</p></div>
          </div>
          <div class="wizard-fields">
            <div class="field">
              <label for="ambito_aplicacion">Ámbito de aplicación</label>
              <select id="ambito_aplicacion" name="ambito_aplicacion">
                <option value="">— Seleccione —</option>
                @foreach(['Municipal','Estatal','Regional','Nacional'] as $a)
                  <option value="{{ $a }}" {{ old('ambito_aplicacion', $air?->ambito_aplicacion) === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label for="poblacion_volumen">Volumen de población afectada</label>
              <input id="poblacion_volumen" name="poblacion_volumen" type="text"
                placeholder="Ej. 12,000 ciudadanos"
                value="{{ old('poblacion_volumen', $air?->poblacion_volumen) }}">
            </div>
            <div class="field span-2">
              <label for="acciones_derivadas">Acciones de simplificación derivadas</label>
              <textarea id="acciones_derivadas" name="acciones_derivadas" rows="3"
                placeholder="Trámites o servicios que se simplificarán...">{{ old('acciones_derivadas', $air?->acciones_derivadas) }}</textarea>
            </div>
            <div class="field span-2">
              <label for="anexos">Anexos técnicos</label>
              <textarea id="anexos" name="anexos" rows="3"
                placeholder="Referencias, estudios o documentos de soporte...">{{ old('anexos', $air?->anexos) }}</textarea>
            </div>
          </div>
        </div>

        {{-- Pie de wizard --}}
        <div class="wizard-foot card-actions">
          <button type="button" class="btn btn-outline" id="btnAtras" onclick="airNav(-1)" style="display:none">Atrás</button>
          <div class="form-actions form-actions-end">
            <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">Cancelar</a>
            <button type="button" class="btn btn-outline" id="btnSig" onclick="airNav(1)">Siguiente</button>
            <button type="submit" name="accion" value="borrador" class="btn btn-outline" id="btnBorrador" style="display:none">Guardar borrador</button>
            <button type="submit" name="accion" value="enviar" class="btn btn-success" id="btnEnviar" style="display:none">Enviar para dictamen</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var cur = 1, total = 8;

  function go(step) {
    document.querySelectorAll('#airWizard [data-panel]').forEach(function (p) {
      p.classList.toggle('active', parseInt(p.dataset.panel) === step);
    });
    document.querySelectorAll('#airWizard [data-step]').forEach(function (s) {
      var n = parseInt(s.dataset.step);
      s.classList.toggle('active', n === step);
      s.classList.toggle('done',   n < step);
    });
    document.getElementById('btnAtras').style.display  = step > 1      ? '' : 'none';
    document.getElementById('btnSig').style.display    = step < total  ? '' : 'none';
    document.getElementById('btnBorrador').style.display = step === total ? '' : 'none';
    document.getElementById('btnEnviar').style.display   = step === total ? '' : 'none';
    cur = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  window.airNav = function (d) {
    var n = cur + d;
    if (n >= 1 && n <= total) go(n);
  };

  go(1);
})();
</script>
@endpush
