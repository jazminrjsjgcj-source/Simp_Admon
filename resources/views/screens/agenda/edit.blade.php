@extends('layouts.app')
@section('title', 'Editar Acción de Agenda')

@section('content')
<style>
  .acc-seccion { border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:12px; overflow:hidden; background:var(--surface); }
  .acc-cabecera { width:100%; display:flex; align-items:center; gap:10px; padding:14px 16px; background:transparent; border:none; cursor:pointer; text-align:left; }
  .acc-titulo { font-size:15px; font-weight:600; color:var(--text); }
  .acc-sub { font-size:12px; color:var(--muted); flex:1; }
  .acc-flecha { font-size:14px; color:var(--muted); transition:transform .2s; }
  .acc-seccion.abierta .acc-flecha { transform:rotate(180deg); }
  .acc-cuerpo { display:none; padding:4px 16px 18px; border-top:1px solid var(--surface-high); }
  .acc-seccion.abierta .acc-cuerpo { display:block; }
</style>
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar Acción de Agenda</h2>
      <p class="nowrap">AGD-{{ str_pad($agenda->id, 3, '0', STR_PAD_LEFT) }} — {{ ucfirst($agenda->tipo) }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('agenda.show', $agenda) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($errors->any())
    <div class="card-body-padded" style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:10px;margin-bottom:16px">
      <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="detalle-con-timeline">
    <div class="detalle-main">
  <form method="POST" action="{{ route('agenda.update', $agenda) }}">
    @csrf @method('PUT')

    <div class="acordeon-tramite">

      {{-- Observaciones a atender (#18) --}}
      @include('partials.observaciones-todas', [
        'observacionesPorSeccion' => $observacionesPorSeccion,
        'campos'                  => $camposObservables,
      ])

      {{-- SECCIÓN 1: Datos de la acción --}}
      <section class="acc-seccion abierta" data-acc="1">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Datos de la acción</span>
          <span class="acc-sub">Descripción, tipo y meta</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="wizard-fields">
            <div class="field span-2">
              <label>Descripción de la acción *</label>
              <textarea required name="descripcion" rows="4" placeholder="Describa la acción de simplificación o digitalización...">{{ old('descripcion', $agenda->descripcion) }}</textarea>
            </div>

            <div class="field">
              <label>Tipo de acción *</label>
              <select required name="tipo">
                <option value="simplificacion" {{ old('tipo', $agenda->tipo) === 'simplificacion' ? 'selected' : '' }}>Simplificación</option>
                <option value="digitalizacion" {{ old('tipo', $agenda->tipo) === 'digitalizacion' ? 'selected' : '' }}>Digitalización</option>
              </select>
            </div>

            <div class="field">
              <label>Dependencia</label>
              <input type="text" value="{{ $agenda->dependencia->nombre ?? '—' }}" disabled class="u-input-disabled">
            </div>

            <div class="field span-2">
              <label>Meta o entregable</label>
              <input name="meta" type="text" maxlength="500" value="{{ old('meta', $agenda->meta) }}" placeholder="Ej. Reducir requisitos de 8 a 4">
            </div>
          </div>
        </div>
      </section>

      {{-- SECCIÓN 2: Seguimiento --}}
      <section class="acc-seccion" data-acc="2">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Seguimiento</span>
          <span class="acc-sub">Fechas, responsable e indicador</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="wizard-fields">
            <div class="field">
              <label>Fecha de inicio</label>
              <input name="fecha_inicio" type="date" value="{{ old('fecha_inicio', optional($agenda->fecha_inicio)->format('Y-m-d')) }}">
            </div>

            <div class="field">
              <label>Fecha compromiso</label>
              <input name="fecha_compromiso" type="date" value="{{ old('fecha_compromiso', optional($agenda->fecha_compromiso)->format('Y-m-d')) }}">
            </div>

            <div class="field">
              <label>Responsable</label>
              <input name="responsable" type="text" maxlength="255" value="{{ old('responsable', $agenda->responsable) }}" placeholder="Nombre del responsable">
            </div>

            <div class="field">
              <label>Indicador de éxito</label>
              <input name="indicador" type="text" maxlength="500" value="{{ old('indicador', $agenda->indicador) }}" placeholder="Ej. % de reducción en tiempo de resolución">
            </x-field-help>
            <x-field-help label="Indicador de avance (rubro 18)">
              <input name="indicador_avance" type="text" maxlength="500" value="{{ old('indicador_avance', $agenda->indicador_avance) }}" placeholder="Ej. % de avance en la implementación">
            </div>
          </div>
        </div>
      </section>

      <div class="card-actions card-actions-end">
        <a href="{{ route('agenda.show', $agenda) }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn">Guardar cambios</button>
      </div>

    </div>{{-- /acordeon-tramite --}}

  </form>
    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion,
        'campos'                  => $camposObservables,
      ])
      @include('partials.timeline', ['tipo' => 'agenda', 'id' => $agenda->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}
</div>{{-- /page-default --}}

@push('scripts')
<script>
  window.toggleAcc = function (boton) {
    var seccion = boton.closest('.acc-seccion');
    if (seccion) seccion.classList.toggle('abierta');
  };
</script>
@endpush
@endsection
