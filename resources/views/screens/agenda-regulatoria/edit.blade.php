@extends('layouts.app')
@section('title', 'Editar Propuesta Regulatoria')

@section('content')
@php
  // Datos pre-cargados de la propuesta. Los rubros guardados como JSON viven en
  // $detalles; las columnas reales en $propuesta. Para los rubros 12/13/14 se
  // arman los IDs ya vinculados, para marcar los checkboxes y la lista.
  $d = $detalles ?? [];
  $tramitesYa = $propuesta->impactos->map(fn($i) => [
      'id'     => $i->tramite_id,
      'nombre' => $i->tramite->nombre_oficial ?? $i->tramite->nombre ?? ('Trámite #'.$i->tramite_id),
      'accion' => $i->accion ?? 'modifica',
  ])->values();
  // Rubros 13/14: ahora son catálogo de tipos (igual que la Agenda SyD).
  // Las explicaciones guardadas vienen del JSON de detalles como
  // { "tipo de acción": "explicación" }.
  $simpGuardadas = $d['acciones_simplificacion'] ?? [];
  $digGuardadas  = $d['acciones_digitalizacion'] ?? [];
  $simpGuardadas = is_array($simpGuardadas) ? $simpGuardadas : [];
  $digGuardadas  = is_array($digGuardadas)  ? $digGuardadas  : [];
@endphp

<div class="page-default wz-wrap">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar Propuesta Regulatoria</h2>
      <p class="nowrap">{{ $propuesta->folio ?? 'Propuesta' }} — actualice los rubros del Anexo.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  {{-- Stepper estándar del sistema (mismas clases que los demás wizards) --}}
  <div class="wizard-stepper" id="propuestaWizard">
    <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Identificación</strong><small>Responsable</small></div>
    <div class="wizard-step" data-step="2"><span class="wizard-dot"></span><strong>Naturaleza</strong><small>Tipo y materia</small></div>
    <div class="wizard-step" data-step="3"><span class="wizard-dot"></span><strong>Diagnóstico</strong><small>Justificación</small></div>
    <div class="wizard-step" data-step="4"><span class="wizard-dot"></span><strong>Impacto</strong><small>Trámites y SyD</small></div>
    <div class="wizard-step" data-step="5"><span class="wizard-dot"></span><strong>Fundamento</strong><small>Jurídico</small></div>
    <div class="wizard-step" data-step="6"><span class="wizard-dot"></span><strong>Anexos</strong><small>Archivos</small></div>
  </div>

  <form method="POST" action="{{ route('propuestas.update', $propuesta) }}" id="prForm" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="accion" id="prAccion" value="borrador">

    {{-- ============ PASO 1: Identificación y responsable ============ --}}
    {{-- Rubros 1.0, 1.1, 4.0 --}}
    <div class="wz-card wz-panel activo" data-panel="1">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Identificación y responsable</h3>
          <p>Quién dará seguimiento a la propuesta y su nombre preliminar.</p>
        </div>
      </div>

      <div class="wizard-fields">
        @php
          // En edición la dependencia ya está fijada en la propuesta; se muestra
          // como informativa junto con el titular (sujeto obligado) de esa
          // dependencia. El responsable se conserva tal como se guardó.
          $depPropuesta = $propuesta->dependencia;
          $titular      = $depPropuesta?->sujetoObligado;
        @endphp

        <x-field-help label="Dependencia" class="span-2">
          <input type="hidden" name="dependencia_id" value="{{ $propuesta->dependencia_id }}">
          <input type="text" value="{{ $depPropuesta->nombre ?? 'Sin dependencia asignada' }}"
            disabled class="u-input-disabled">
        </x-field-help>

        <x-field-help label="Sujeto obligado (titular)" class="span-2">
          <input type="text"
            value="{{ $titular?->nombre ? $titular->nombre.' — '.$titular->cargo : 'Sin titular asignado' }}"
            readonly class="u-input-readonly">
        </x-field-help>

        <x-field-help label="Nombre completo del responsable">
          <input name="responsable_nombre" type="text" maxlength="255" value="{{ old('responsable_nombre', $d['responsable_nombre'] ?? '') }}" placeholder="Nombre del responsable de la propuesta">
        </x-field-help>
        <x-field-help label="Cargo del responsable">
          <input name="responsable_cargo" type="text" maxlength="255" value="{{ old('responsable_cargo', $d['responsable_cargo'] ?? '') }}" placeholder="Cargo del responsable">
        </x-field-help>
        <x-field-help label="Nombre preliminar de la Propuesta Regulatoria" class="span-2">
          <input name="nombre" type="text" maxlength="500" required value="{{ old('nombre', $propuesta->nombre) }}" placeholder="Título descriptivo de la propuesta">
        </x-field-help>
      </div>

      <div class="wz-foot">
        <span></span>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 2: Naturaleza de la propuesta ============ --}}
    {{-- Rubros 2.0, 3.0, 5.0, 6.0 --}}
    <div class="wz-card wz-panel" data-panel="2">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Naturaleza de la propuesta</h3>
          <p>Tipo de regulación, materia, sectores impactados y fecha tentativa.</p>
        </div>
      </div>

      @php
        $tiposFijos = ['Ley','Reglamento','Decreto','Acuerdo','Lineamientos','Reglas de Operación','Norma'];
        $tipoActual = old('tipo_regulacion', $propuesta->tipo_regulacion);
        $tipoEsOtro = $tipoActual && !in_array($tipoActual, $tiposFijos, true);
        $sectorActual = old('sector_id', $propuesta->sector_id);
        $sectorEsOtro = !$sectorActual && !empty($d['sectores_impactados']);
      @endphp
      <div class="wizard-fields">
        <x-field-help label="Tipo de Regulación">
          <select name="tipo_regulacion" id="prTipoRegulacion" onchange="prToggleOtro('tipo_regulacion')">
            <option value="">Seleccione...</option>
            @foreach($tiposFijos as $tf)
              <option value="{{ $tf }}" @selected(!$tipoEsOtro && $tipoActual===$tf)>{{ $tf }}</option>
            @endforeach
            <option value="otro" @selected($tipoEsOtro)>Otro (especificar)</option>
          </select>
        </x-field-help>
        <x-field-help label="Especifique el tipo" id="prTipoRegulacionOtroWrap" class="pr-otro-wrap" style="{{ $tipoEsOtro ? '' : 'display:none' }}">
          <input name="tipo_regulacion_otro" type="text" maxlength="200" value="{{ old('tipo_regulacion_otro', $tipoEsOtro ? $tipoActual : '') }}" placeholder="Precise el tipo de regulación">
        </x-field-help>

        <x-field-help label="Materia sobre la cual versará la Propuesta">
          <input name="materia" type="text" maxlength="255" value="{{ old('materia', $d['materia'] ?? '') }}" placeholder="Materia o tema central">
        </x-field-help>

        <x-field-help label="Fecha tentativa de presentación">
          <input name="fecha_tentativa" type="date" value="{{ old('fecha_tentativa', $propuesta->fecha_tentativa ? \Carbon\Carbon::parse($propuesta->fecha_tentativa)->format('Y-m-d') : '') }}">
        </x-field-help>

        <x-field-help label="Sectores o grupos potencialmente impactados" class="span-2">
          <select name="sector_id" id="prSector" onchange="prToggleOtro('sector')">
            <option value="">Seleccione...</option>
            @foreach($sectores as $sector)
              <option value="{{ $sector->id }}" @selected(!$sectorEsOtro && $sectorActual==$sector->id)>{{ $sector->nombre }}</option>
            @endforeach
            <option value="otro" @selected($sectorEsOtro)>Otro (especificar)</option>
          </select>
        </x-field-help>
        <x-field-help label="Especifique el sector/grupo" id="prSectorOtroWrap" class="pr-otro-wrap span-2" style="{{ $sectorEsOtro ? '' : 'display:none' }}">
          <input name="sectores_impactados" type="text" maxlength="500" value="{{ old('sectores_impactados', $d['sectores_impactados'] ?? '') }}" placeholder="Precise los sectores o grupos impactados">
        </x-field-help>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 3: Diagnóstico y justificación ============ --}}
    {{-- Rubros 7.0, 8.0, 9.0, 10.0, 11.0 --}}
    <div class="wz-card wz-panel" data-panel="3">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Diagnóstico y justificación</h3>
          <p>Por qué es necesaria la propuesta y qué problema resuelve.</p>
        </div>
      </div>

      <div class="wizard-fields">
        <x-field-help label="Justificación para emitir la propuesta" class="span-2">
          <textarea name="justificacion" rows="3" placeholder="Justificación...">{{ old('justificacion', $d['justificacion'] ?? '') }}</textarea>
        </x-field-help>
        <x-field-help label="Problemática que se pretende resolver" class="span-2">
          <textarea name="problematica" rows="3" placeholder="Problemática...">{{ old('problematica', $d['problematica'] ?? '') }}</textarea>
        </x-field-help>
        <x-field-help label="Alternativas consideradas" class="span-2">
          <textarea name="alternativas" rows="3" placeholder="Alternativas...">{{ old('alternativas', $d['alternativas'] ?? '') }}</textarea>
        </x-field-help>
        <x-field-help label="Posibles beneficios que generará" class="span-2">
          <textarea name="beneficios" rows="3" placeholder="Beneficios...">{{ old('beneficios', $d['beneficios'] ?? '') }}</textarea>
        </x-field-help>
        <x-field-help label="Posibles costos burocráticos que generará" class="span-2">
          <textarea name="costos_burocraticos" rows="3" placeholder="Costos burocráticos...">{{ old('costos_burocraticos', $d['costos_burocraticos'] ?? '') }}</textarea>
        </x-field-help>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 4: Trámites e impacto operativo ============ --}}
    {{-- Rubro 12.0 (trámites impactados) + 13.0/14.0 (acciones SyD) --}}
    <div class="wz-card wz-panel" data-panel="4">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Trámites e impacto operativo</h3>
          <p>Trámites afectados y acciones de simplificación/digitalización asociadas.</p>
        </div>
      </div>

      {{-- Rubro 12: Trámites impactados --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Trámites y servicios en los que impacta</strong>
          <small>Listado de trámites impactados de manera directa por la propuesta, indicando si los crea, modifica o elimina.</small>
        </div>
        <div class="wz-bloque-body">
          <x-field-help label="Buscar trámite o servicio para agregar">
            <input type="text" id="prBuscaTramite" placeholder="Escriba el nombre del trámite..." autocomplete="off" oninput="prBuscarTramite(this.value)">
          </x-field-help>
          <div id="prBuscaResultados"></div>

          <div id="prTramitesLista" style="margin-top:12px"></div>
          <p id="prTramitesVacio" style="font-size:13px; color:var(--muted)">Aún no ha agregado trámites impactados. Si la propuesta no impacta ninguno, puede continuar.</p>
        </div>
      </div>

      {{-- Rubros 13 y 14: catálogo de tipos de acción (igual que la Agenda SyD). --}}
      {{-- Pre-marca y rellena las explicaciones guardadas en el JSON. --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Acciones de simplificación asociadas</strong>
          <small>Marque las acciones de simplificación que aplicarán y explique cómo en cada caso.</small>
        </div>
        <div class="wz-bloque-body">
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
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Acciones de digitalización asociadas</strong>
          <small>Marque las acciones de digitalización que aplicarán y explique cómo en cada caso.</small>
        </div>
        <div class="wz-bloque-body">
          <div class="acciones-catalogo">
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
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 5: Fundamento jurídico e impacto comercial ============ --}}
    {{-- Rubros 15.0 y 16.0 --}}
    <div class="wz-card wz-panel" data-panel="5">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Fundamento jurídico</h3>
          <p>Normativa que sustenta la propuesta e impacto comercial.</p>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Fundamento jurídico para emitir la Propuesta</strong>
          <small>Normativa que establezca la facultad para emitir, modificar, derogar o abrogar la Propuesta Regulatoria.</small>
        </div>
        <div class="wz-bloque-body">
          <x-citar-regulacion label="Regulaciones que dan fundamento a la propuesta" />
          <x-field-help label="Fundamento jurídico (texto)" class="span-2">
            <textarea name="fundamento_juridico" rows="3" placeholder="Fundamento jurídico...">{{ old('fundamento_juridico', $d['fundamento_juridico'] ?? '') }}</textarea>
          </x-field-help>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Impacto en el comercio o la inversión</strong>
          <small>De ser el caso. Indique si la propuesta afecta la competitividad, costos, requisitos o procesos comerciales o de inversión.</small>
        </div>
        <div class="wz-bloque-body">
          @php $impComercio = old('impacta_comercio_inversion', $propuesta->impacta_comercio_inversion ? '1' : '0'); @endphp
          <div class="wz-sino">
            <label><input type="radio" name="impacta_comercio_inversion" value="1" onchange="prToggleComercio(true)" @checked($impComercio==='1')> Sí</label>
            <label><input type="radio" name="impacta_comercio_inversion" value="0" onchange="prToggleComercio(false)" @checked($impComercio==='0')> No</label>
          </div>
          <div class="wz-detalle {{ $impComercio==='1' ? 'visible' : '' }}" id="prComercioDetalle">
            <x-field-help label="Describa el impacto comercial/de inversión" class="span-2">
              <textarea name="impacto_comercio" rows="3" placeholder="Describa el impacto...">{{ old('impacto_comercio', $d['impacto_comercio'] ?? '') }}</textarea>
            </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 6: Anexos y envío ============ --}}
    {{-- Rubro 17.0 --}}
    <div class="wz-card wz-panel" data-panel="6">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Anexos y envío</h3>
          <p>Adjunte el proyecto de la propuesta y otros documentos de soporte.</p>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Anexos</strong>
          <small>La plataforma admite la carga de hasta nueve archivos complementarios.</small>
        </div>
        <div class="wz-bloque-body">
          @php $presentaProy = old('presenta_proyecto', ($d['presenta_proyecto'] ?? '0')); @endphp
          <div class="wz-sino">
            <span class="wz-sublabel" style="margin-right:8px">¿Presenta el proyecto de la Propuesta Regulatoria?</span>
            <label><input type="radio" name="presenta_proyecto" value="1" @checked($presentaProy==='1' || $presentaProy===1)> Sí</label>
            <label><input type="radio" name="presenta_proyecto" value="0" @checked($presentaProy==='0' || $presentaProy===0)> No</label>
          </div>
          <x-field-help label="Carga de anexos (hasta 9 archivos)" class="span-2">
            <x-carga-archivos name="anexos" :multiple="true" accept=".pdf,.docx,.xlsx,.jpg,.png" :maxMb="10" />
          </x-field-help>
          <x-field-help label="Observaciones y/o comentarios" class="span-2">
            <textarea name="observaciones" rows="2" placeholder="Observaciones...">{{ old('observaciones', $d['observaciones'] ?? '') }}</textarea>
          </x-field-help>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-outline" onclick="prGuardar('borrador')">Guardar como borrador</button>
          <button type="button" class="btn btn-success" onclick="prGuardar('enviar')">Guardar y enviar</button>
        </div>
      </div>
    </div>

  </form>
</div>{{-- /page-default --}}

@push('scripts')
<script>
  window.PR = {
    apiTramitesBuscar: "{{ route('api.tramites.buscar') }}",
    tramitesPrecargados: @json($tramitesYa)
  };
</script>
<script>
(function () {
  var paso = 1;
  var ULTIMO = 6;

  function pintarStepper(n) {
    document.querySelectorAll('[data-step]').forEach(function (s) {
      var d = parseInt(s.dataset.step);
      s.classList.toggle('active', d === n);
      s.classList.toggle('done', d < n);
      s.classList.toggle('completed', d < n);
    });
  }
  function mostrar(n) {
    document.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('activo', parseInt(p.dataset.panel) === n);
    });
    pintarStepper(n);
    paso = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  window.wzNav = function (dir) {
    var n = paso + dir;
    if (n >= 1 && n <= ULTIMO) mostrar(n);
  };

  // Campos "otro": muestran un input adicional cuando se elige esa opción.
  window.prToggleOtro = function (campo) {
    if (campo === 'tipo_regulacion') {
      var sel = document.getElementById('prTipoRegulacion');
      var wrap = document.getElementById('prTipoRegulacionOtroWrap');
      if (wrap) wrap.style.display = (sel.value === 'otro') ? '' : 'none';
    }
    if (campo === 'sector') {
      var selS = document.getElementById('prSector');
      var wrapS = document.getElementById('prSectorOtroWrap');
      if (wrapS) wrapS.style.display = (selS.value === 'otro') ? '' : 'none';
    }
  };

  // Detalle condicional del impacto comercial (rubro 16).
  window.prToggleComercio = function (mostrar) {
    var det = document.getElementById('prComercioDetalle');
    if (det) det.classList.toggle('visible', !!mostrar);
  };
  // Rubros 13/14: al marcar un tipo de acción, abre su textarea de explicación.
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

  // --- Rubro 12: buscador y repetidor de trámites impactados ---
  // En edición, arranca con los trámites ya vinculados a la propuesta.
  var tramitesAgregados = (window.PR.tramitesPrecargados || []).map(function (t) {
    return { id: t.id, nombre: t.nombre, accion: t.accion || 'modifica' };
  });

  window.prBuscarTramite = function (q) {
    var cont = document.getElementById('prBuscaResultados');
    if (!q || q.length < 2) { cont.innerHTML = ''; return; }
    fetch(window.PR.apiTramitesBuscar + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = data.data || data || [];
        cont.innerHTML = items.slice(0, 6).map(function (t) {
          return '<div class="pr-buscar-result" onclick="prAgregarTramite(' + t.id + ', \'' + escaparComilla(t.nombre_oficial || t.nombre || '') + '\')">' +
                   '<strong>' + escaparHtml(t.nombre_oficial || t.nombre || 'Trámite o servicio') + '</strong>' +
                   '<span>' + escaparHtml(t.folio || '') + '</span>' +
                 '</div>';
        }).join('');
      })
      .catch(function () { cont.innerHTML = ''; });
  };

  window.prAgregarTramite = function (id, nombre) {
    if (tramitesAgregados.some(function (t) { return t.id === id; })) return;
    tramitesAgregados.push({ id: id, nombre: nombre });
    document.getElementById('prBuscaTramite').value = '';
    document.getElementById('prBuscaResultados').innerHTML = '';
    renderTramites();
  };

  window.prQuitarTramite = function (id) {
    tramitesAgregados = tramitesAgregados.filter(function (t) { return t.id !== id; });
    renderTramites();
  };

  function renderTramites() {
    var lista = document.getElementById('prTramitesLista');
    var vacio = document.getElementById('prTramitesVacio');
    vacio.style.display = tramitesAgregados.length ? 'none' : '';
    lista.innerHTML = tramitesAgregados.map(function (t, i) {
      var acc = t.accion || 'modifica';
      return '<div class="pr-tramite-fila">' +
               '<span class="pr-nombre">' + escaparHtml(t.nombre) +
                 '<input type="hidden" name="tramites_impacto[' + i + '][tramite_id]" value="' + t.id + '">' +
               '</span>' +
               '<select name="tramites_impacto[' + i + '][accion]">' +
                 '<option value="crea"' + (acc === 'crea' ? ' selected' : '') + '>Crea</option>' +
                 '<option value="modifica"' + (acc === 'modifica' ? ' selected' : '') + '>Modifica</option>' +
                 '<option value="elimina"' + (acc === 'elimina' ? ' selected' : '') + '>Elimina</option>' +
               '</select>' +
               '<button type="button" class="pr-quitar" onclick="prQuitarTramite(' + t.id + ')">Quitar</button>' +
             '</div>';
    }).join('');
  }

  // Envío: fija la acción (borrador/enviar) y manda el formulario.
  window.prGuardar = function (modo) {
    document.getElementById('prAccion').value = (modo === 'enviar') ? 'enviar' : 'borrador';
    document.getElementById('prForm').submit();
  };

  function escaparHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escaparComilla(s) {
    return String(s == null ? '' : s).replace(/'/g, "\\'").replace(/"/g, '');
  }

  // Estado inicial al cargar.
  document.addEventListener('DOMContentLoaded', function () {
    mostrar(1);
    renderTramites();
  });
})();
</script>
@endpush
@endsection