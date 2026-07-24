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
  /* Paquete 3: catálogo de acciones con explicación individual */
  .acciones-catalogo { border:1px solid var(--surface-high); border-radius:var(--radius); padding:14px; }
  .accion-item { border-bottom:1px solid var(--surface-high); padding:8px 0; }
  .accion-item:last-child { border-bottom:none; }
  .accion-check { display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-size:13px; color:var(--text); }
  .accion-check input { margin-top:3px; }
  .accion-exp { margin-top:8px; padding-left:24px; }
  .accion-exp textarea { width:100%; }
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
              <label>Alcance de la acción *</label>
              <select required name="tipo" id="tipoAlcance" onchange="aplicarAlcanceEdit()">
                <option value="simplificacion" {{ old('tipo', $agenda->tipo) === 'simplificacion' ? 'selected' : '' }}>Solo simplificación</option>
                <option value="digitalizacion" {{ old('tipo', $agenda->tipo) === 'digitalizacion' ? 'selected' : '' }}>Solo digitalización</option>
                <option value="ambas"          {{ old('tipo', $agenda->tipo) === 'ambas'          ? 'selected' : '' }}>Simplificación y digitalización</option>
              </select>
            </div>

            <div class="field">
              <label>Dependencia</label>
              <input type="text" value="{{ $agenda->dependencia->nombre ?? '' }}" disabled class="u-input-disabled">
            </div>

            <div class="field span-2">
              <label>Meta o entregable</label>
              <input name="meta" type="text" maxlength="500" value="{{ old('meta', $agenda->meta) }}" placeholder="Ej. Reducir requisitos de 8 a 4">
            </div>
          </div>
        </div>
      </section>

      {{-- Paquete 3: catálogos oficiales con explicación por acción (pre-cargados) --}}
      @php
        $simpGuardadas = old('acciones_simplificacion', $agenda->acciones_simplificacion ?? []);
        $digGuardadas  = old('acciones_digitalizacion', $agenda->acciones_digitalizacion ?? []);
        $simpGuardadas = is_array($simpGuardadas) ? $simpGuardadas : [];
        $digGuardadas  = is_array($digGuardadas)  ? $digGuardadas  : [];
      @endphp

      {{-- SECCIÓN Simplificación --}}
      <section class="acc-seccion" data-acc="simp" id="seccionSimp">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Acciones de simplificación</span>
          <span class="acc-sub">Catálogo oficial (rubro 14)</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="acciones-catalogo">
            @foreach([
              'Ampliación de la vigencia de resoluciones',
              'Reducción de los plazos de resolución o respuesta',
              'Reducción de requisitos',
              'Eliminación de requisitos',
              'Eliminación de Trámites o Servicios',
              'Supresión de obligaciones regulatorias que representen costos burocráticos para las personas',
              'Fusión de trámites y/o modalidades',
              'Acciones afirmativas en materia de accesibilidad universal',
              'Conversión de Trámites en Avisos o manifestaciones',
              'Otro',
            ] as $idx => $accion)
              @php $marcada = array_key_exists($accion, $simpGuardadas); @endphp
              <div class="accion-item">
                <label class="accion-check">
                  <input type="checkbox" value="{{ $accion }}" data-target="eSimpExp{{ $idx }}"
                    onchange="toggleAccionExp(this)" {{ $marcada ? 'checked' : '' }}>
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="eSimpExp{{ $idx }}" style="display:{{ $marcada ? '' : 'none' }}">
                  <textarea name="acciones_simplificacion[{{ $accion }}]" rows="2"
                    placeholder="Explique cómo se aplicará esta acción" {{ $marcada ? '' : 'disabled' }}>{{ $simpGuardadas[$accion] ?? '' }}</textarea>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </section>

      {{-- SECCIÓN Digitalización --}}
      <section class="acc-seccion" data-acc="dig" id="seccionDig">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Digitalización</span>
          <span class="acc-sub">Niveles y catálogo oficial (rubro 14 DIG)</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          @php
            // Descripciones oficiales ATDT (mismas que la calculadora y el create).
            // Se centralizan aquí para mantener consistencia entre edit y create.
            $nivelesAtdt = [
              0 => 'Nivel 0 — Sin digitalización',
              1 => 'Nivel 1 — Eficiencia administrativa básica',
              2 => 'Nivel 2 — Productividad y reducción de costos',
              3 => 'Nivel 3 — Acceso electrónico transaccional',
              4 => 'Nivel 4 — Experiencia ciudadana unificada',
              5 => 'Nivel 5 — Innovación, transparencia y participación',
            ];
          @endphp
          <div class="wizard-fields">
            <x-field-help label="Nivel actual de digitalización">
              <select name="nivel_actual">
                @foreach($nivelesAtdt as $n => $desc)
                  <option value="{{ $n }}" {{ (string) old('nivel_actual', $agenda->nivel_actual) === (string) $n ? 'selected' : '' }}>{{ $desc }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="Nivel meta de digitalización">
              <select name="nivel_meta">
                @foreach($nivelesAtdt as $n => $desc)
                  <option value="{{ $n }}" {{ (string) old('nivel_meta', $agenda->nivel_meta) === (string) $n ? 'selected' : '' }}>{{ $desc }}</option>
                @endforeach
              </select>
            </x-field-help>
          </div>
          <div class="acciones-catalogo" style="margin-top:14px">
            @foreach([
              'Reducción de los plazos de resolución o respuesta',
              'Reducción de requisitos',
              'Eliminación de requisitos',
              'Acciones afirmativas en materia de accesibilidad universal',
              'Eliminación de copias e impresiones',
              'Mejorar experiencia de usuario',
              'Reducción de pasos en su proceso digital',
              'Otro',
            ] as $idx => $accion)
              @php $marcada = array_key_exists($accion, $digGuardadas); @endphp
              <div class="accion-item">
                <label class="accion-check">
                  <input type="checkbox" value="{{ $accion }}" data-target="eDigExp{{ $idx }}"
                    onchange="toggleAccionExp(this)" {{ $marcada ? 'checked' : '' }}>
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="eDigExp{{ $idx }}" style="display:{{ $marcada ? '' : 'none' }}">
                  <textarea name="acciones_digitalizacion[{{ $accion }}]" rows="2"
                    placeholder="Explique cómo se aplicará esta acción" {{ $marcada ? '' : 'disabled' }}>{{ $digGuardadas[$accion] ?? '' }}</textarea>
                </div>
              </div>
            @endforeach
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
            </div>
            <div class="field">
              <label>Indicador de avance (rubro 18)</label>
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

  // Paquete 3: abre/cierra y habilita/deshabilita la explicación de cada acción.
  window.toggleAccionExp = function (chk) {
    var exp = document.getElementById(chk.dataset.target);
    if (!exp) return;
    exp.style.display = chk.checked ? '' : 'none';
    var ta = exp.querySelector('textarea');
    if (ta) {
      ta.disabled = !chk.checked;
      if (!chk.checked) ta.value = '';
    }
  };

  // Paquete 3: muestra solo las secciones del alcance elegido.
  window.aplicarAlcanceEdit = function () {
    var alc = document.getElementById('tipoAlcance');
    if (!alc) return;
    var v = alc.value;
    var simp = document.getElementById('seccionSimp');
    var dig  = document.getElementById('seccionDig');
    if (simp) simp.style.display = (v === 'simplificacion' || v === 'ambas') ? '' : 'none';
    if (dig)  dig.style.display  = (v === 'digitalizacion' || v === 'ambas') ? '' : 'none';
  };
  document.addEventListener('DOMContentLoaded', aplicarAlcanceEdit);

</script>
@endpush
@endsection