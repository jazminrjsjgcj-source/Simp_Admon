@extends('layouts.app')
@section('title', ($reingenieria->exists ? 'Editar' : 'Crear') . ' Reingeniería — ' . $tramite->nombre_oficial)

@section('content')
<div class="page-default" style="max-width:800px">

  <div style="margin-bottom:12px">
    <a href="{{ route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria']) }}" class="btn btn-outline" style="font-size:12px">← Volver al detalle</a>
  </div>

  <div class="card card-pad" style="margin-bottom:20px">
    <h2 style="margin:0 0 4px;color:var(--primary)">
      {{ $reingenieria->exists ? 'Editar' : 'Nueva' }} Reingeniería TO-BE
    </h2>
    <p style="margin:0;color:var(--muted);font-size:13px">
      {{ $tramite->nombre_oficial }} · {{ $tramite->dependencia->nombre ?? '' }}
    </p>
  </div>

  @if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius);font-size:13px;color:#991b1b">
      @foreach($errors->all() as $error)
        <p style="margin:0">{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="POST"
    action="{{ $reingenieria->exists
      ? route('digitalizacion.reingenieria.actualizar', [$tramite, $reingenieria])
      : route('digitalizacion.reingenieria.guardar', $tramite) }}"
    enctype="multipart/form-data">
    @csrf
    @if($reingenieria->exists)
      @method('PUT')
    @endif

    <div class="card">
      {{-- Origen --}}
      <div class="review-section-head"><div><h3>Origen de la reingeniería</h3></div></div>
      <div style="padding:16px 20px">
        <div class="field">
          <label>¿De dónde viene esta reingeniería?</label>
          <div style="display:flex;gap:12px">
            <label class="check-card" style="flex:1">
              <input type="radio" name="origen" value="agenda"
                {{ old('origen', $reingenieria->origen ?? 'agenda') === 'agenda' ? 'checked' : '' }}
                onchange="document.getElementById('bloqueDirecta').style.display='none'">
              <div>
                <strong>Agenda de Digitalización</strong>
                <small>Vinculada a una acción de agenda aprobada.</small>
              </div>
            </label>
            <label class="check-card" style="flex:1">
              <input type="radio" name="origen" value="directa"
                {{ old('origen', $reingenieria->origen) === 'directa' ? 'checked' : '' }}
                onchange="document.getElementById('bloqueDirecta').style.display='block'">
              <div>
                <strong>Reingeniería directa</strong>
                <small>Justificación obligatoria.</small>
              </div>
            </label>
          </div>
        </div>
      </div>

      {{-- Bloque reingeniería directa (solo visible cuando origen=directa) --}}
      <div id="bloqueDirecta" style="padding:0 20px 16px;{{ old('origen', $reingenieria->origen) === 'directa' ? '' : 'display:none' }}">
        <div class="field">
          <label>Motivo de reingeniería directa *</label>
          <select name="motivo_directa" class="input">
            <option value="">— Seleccionar —</option>
            @foreach(\App\Models\Reingenieria::MOTIVOS_DIRECTA as $motivo)
              <option value="{{ $motivo }}" {{ old('motivo_directa', $reingenieria->motivo_directa) === $motivo ? 'selected' : '' }}>
                {{ ucfirst(str_replace('_', ' ', $motivo)) }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label>Justificación *</label>
          <textarea name="justificacion" rows="4" class="input" placeholder="Explique por qué es necesario digitalizar este trámite fuera de la Agenda.">{{ old('justificacion', $reingenieria->justificacion) }}</textarea>
        </div>
        <div class="field">
          <label>Área solicitante</label>
          <input type="text" name="area_solicitante" class="input" value="{{ old('area_solicitante', $reingenieria->area_solicitante) }}" placeholder="Nombre del área que solicita">
        </div>
        <div class="field">
          <label>Fecha límite</label>
          <input type="date" name="fecha_limite" class="input" value="{{ old('fecha_limite', $reingenieria->fecha_limite?->format('Y-m-d')) }}">
        </div>
        <div class="field">
          <label>Documento soporte (opcional)</label>
          <input type="file" name="documento_soporte" class="input" accept=".pdf,.doc,.docx">
        </div>
      </div>

      {{-- Flujo TO-BE --}}
      <div class="review-section-head"><div><h3>Flujo TO-BE</h3><p>Capture los pasos del proceso propuesto después de simplificar o digitalizar.</p></div></div>
      <div style="padding:16px 20px">
        <div id="pasosContainer">
          @php
            $pasosPrevios = old('pasos', $reingenieria->flujo_to_be ?? []);
          @endphp
          @forelse($pasosPrevios as $idx => $paso)
            <div class="dshow-paso-row" data-idx="{{ $idx }}">
              <span class="dshow-paso-num">{{ $idx + 1 }}</span>
              <div style="flex:1;display:grid;gap:8px">
                <input type="text" name="pasos[{{ $idx }}][accion]" class="input" placeholder="Acción o actividad"
                  value="{{ $paso['accion'] ?? '' }}" required>
                <input type="text" name="pasos[{{ $idx }}][detalle]" class="input" placeholder="Detalle (opcional)"
                  value="{{ $paso['detalle'] ?? '' }}">
                <select name="pasos[{{ $idx }}][tipo]" class="input" style="max-width:200px">
                  <option value="paso" {{ ($paso['tipo'] ?? '') === 'paso' ? 'selected' : '' }}>Paso</option>
                  <option value="decision" {{ ($paso['tipo'] ?? '') === 'decision' ? 'selected' : '' }}>Decisión</option>
                  <option value="inspeccion" {{ ($paso['tipo'] ?? '') === 'inspeccion' ? 'selected' : '' }}>Inspección</option>
                  <option value="pago" {{ ($paso['tipo'] ?? '') === 'pago' ? 'selected' : '' }}>Pago</option>
                  <option value="resolutivo" {{ ($paso['tipo'] ?? '') === 'resolutivo' ? 'selected' : '' }}>Resolutivo</option>
                  <option value="notificacion" {{ ($paso['tipo'] ?? '') === 'notificacion' ? 'selected' : '' }}>Notificación</option>
                </select>
              </div>
              <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('.dshow-paso-row').remove();renumerarPasos()" style="align-self:start">×</button>
            </div>
          @empty
            <p style="color:var(--muted);font-size:13px;text-align:center;padding:16px" id="pasosVacio">
              Agregue pasos con el botón de abajo.
            </p>
          @endforelse
        </div>

        <button type="button" class="btn btn-outline" onclick="agregarPaso()" style="margin-top:12px">
          + Agregar paso
        </button>
      </div>
    </div>

    {{-- Acciones --}}
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px">
      <a href="{{ route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria']) }}" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn">{{ $reingenieria->exists ? 'Guardar cambios' : 'Crear reingeniería' }}</button>
    </div>
  </form>
</div>

<style>
  .dshow-paso-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 12px;
    border: 1px solid var(--surface-high);
    border-radius: var(--radius);
    margin-bottom: 8px;
    background: var(--surface);
  }
  .dshow-paso-num {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-pill);
    background: var(--surface-tint);
    color: var(--primary);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
  }
</style>

<script>
  var pasoIdx = {{ count($pasosPrevios) }};

  function agregarPaso() {
    var vacio = document.getElementById('pasosVacio');
    if (vacio) vacio.remove();

    var container = document.getElementById('pasosContainer');
    var div = document.createElement('div');
    div.className = 'dshow-paso-row';
    div.setAttribute('data-idx', pasoIdx);
    div.innerHTML =
      '<span class="dshow-paso-num">' + (pasoIdx + 1) + '</span>' +
      '<div style="flex:1;display:grid;gap:8px">' +
        '<input type="text" name="pasos[' + pasoIdx + '][accion]" class="input" placeholder="Acción o actividad" required>' +
        '<input type="text" name="pasos[' + pasoIdx + '][detalle]" class="input" placeholder="Detalle (opcional)">' +
        '<select name="pasos[' + pasoIdx + '][tipo]" class="input" style="max-width:200px">' +
          '<option value="paso">Paso</option>' +
          '<option value="decision">Decisión</option>' +
          '<option value="inspeccion">Inspección</option>' +
          '<option value="pago">Pago</option>' +
          '<option value="resolutivo">Resolutivo</option>' +
          '<option value="notificacion">Notificación</option>' +
        '</select>' +
      '</div>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="this.closest(\'.dshow-paso-row\').remove();renumerarPasos()" style="align-self:start">×</button>';
    container.appendChild(div);
    pasoIdx++;
  }

  function renumerarPasos() {
    document.querySelectorAll('.dshow-paso-row').forEach(function (row, i) {
      row.querySelector('.dshow-paso-num').textContent = i + 1;
    });
  }
</script>
@endsection
