@extends('layouts.app')
@section('title', 'Solicitud de Exención AIR')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Solicitud de Exención del AIR</h2>
      <p class="nowrap">Art. 36 LNETB — {{ $propuesta->nombre }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">← Volver</a>
    </div>
  </div>

  <div class="assist-box">
    <strong>¿Cuándo aplica la exención?</strong> El Art. 36 LNETB establece que ciertas propuestas
    regulatorias no requieren AIR. Seleccione la fracción que justifica la exención y proporcione
    la justificación correspondiente.
  </div>

  @if($exencion)
    <div class="card">
      <div class="panel-head">
        <div><h3>Solicitud existente</h3><p>Estatus actual de la exención.</p></div>
        <span class="badge {{ $exencion->estadoBadge() }}">
          {{ ucfirst($exencion->estatus) }}
        </span>
      </div>
      <div class="card-body-padded">
        <div class="modal-grid">
          <div class="modal-data-item u-span-2">
            <span>Fracciones invocadas</span>
            <strong>{{ $exencion->fraccionesTexto() }}</strong>
          </div>
          <div class="modal-data-item u-span-2">
            <span>Justificación</span>
            <p style="margin:4px 0 0;font-size:14px">{{ $exencion->justificacion }}</p>
          </div>
        </div>
      </div>

      {{-- Botón para resolver (solo revisora/admin) --}}
      @if(auth()->user()->tienePermiso('agenda_regulatoria.aprobar') && $exencion->estatus === 'solicitada')
        <div class="card-foot">
          <form method="POST" action="{{ route('air.exencion.resolver', $propuesta) }}" class="u-inline">
            @csrf
            <input type="hidden" name="resolucion" value="aprobada">
            <button type="submit" class="btn btn-success btn-sm"
              onclick="return confirmarAccion(this, '¿Aprobar la exención? La propuesta quedará exenta de AIR.')">
              Aprobar exención
            </button>
          </form>
          <form method="POST" action="{{ route('air.exencion.resolver', $propuesta) }}" class="u-inline">
            @csrf
            <input type="hidden" name="resolucion" value="rechazada">
            <button type="submit" class="btn btn-outline btn-sm danger"
              onclick="return confirmarAccion(this, '¿Rechazar la exención? La propuesta deberá presentar AIR completo.')">
              Rechazar
            </button>
          </form>
        </div>
      @endif
    </div>
  @endif

  {{-- Formulario de solicitud --}}
  @if(!$exencion || $exencion->estatus === 'rechazada')
  <form method="POST" action="{{ route('air.exencion.guardar', $propuesta) }}">
    @csrf

    <div class="card">
      <div class="panel-head">
        <div><h3>Fracciones aplicables (Art. 36)</h3><p>Seleccione una o más fracciones que justifiquen la exención.</p></div>
      </div>
      <div class="card-body-padded">
        @error('fracciones')
          <div class="toast toast-error u-toast-inline">{{ $message }}</div>
        @enderror

        <div class="wizard-fields">
          @foreach(\App\Models\ExencionAir::FRACCIONES_ART36 as $num => $texto)
            <div class="field span-2">
              <label class="check-row" style="display:flex;align-items:flex-start;gap:12px;cursor:pointer">
                <input type="checkbox" name="fracciones[]" value="{{ $num }}"
                  {{ in_array($num, old('fracciones', $exencion?->fracciones ?? [])) ? 'checked' : '' }}
                  style="margin-top:3px;flex-shrink:0">
                <span><strong>Fracción {{ $num }}:</strong> {{ $texto }}</span>
              </label>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="card">
      <div class="panel-head">
        <div><h3>Justificación</h3><p>Explique por qué aplica la exención en esta propuesta específica.</p></div>
      </div>
      <div class="card-body-padded">
        <div class="wizard-fields">
          <div class="field span-2">
            <label for="justificacion">Justificación detallada *</label>
            <textarea id="justificacion" name="justificacion" rows="6" required
              placeholder="Argumente claramente por qué esta propuesta queda exenta del AIR conforme a las fracciones seleccionadas..."
              minlength="30">{{ old('justificacion', $exencion?->justificacion) }}</textarea>
            @error('justificacion')
              <span class="field-error">{{ $message }}</span>
            @enderror
          </div>
          <div class="field">
            <label for="costos_estimados">Costos estimados (MXN)</label>
            <input id="costos_estimados" name="costos_estimados" type="number" min="0" step="0.01"
              placeholder="0.00"
              value="{{ old('costos_estimados', $exencion?->costos_estimados) }}">
            <small class="help-small">Opcional. Estimación del impacto económico aunque sea exenta de AIR formal.</small>
          </div>
        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">Enviar solicitud de exención</button>
      </div>
    </div>
  </form>
  @endif

</div>
@endsection
