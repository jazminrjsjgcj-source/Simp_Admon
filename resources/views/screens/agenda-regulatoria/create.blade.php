@extends('layouts.app')
@section('title', 'Nueva Propuesta Regulatoria')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Nueva Propuesta Regulatoria</h2>
      <p class="nowrap">Registro conforme al Anexo de Agenda Regulatoria (17 rubros, LNETB arts. 26-32).</p>
    </div>
    <div class="head-actions">
      <x-btn-ejemplo tipo="agenda_regulatoria" />
      <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Volver</a>
    </div>
  </div>

  {{-- Stepper: 6 pasos. Usa el id propuestaWizard, que ya tiene estilos en 06-wizards.css --}}
  <div class="wizard-stepper" id="propuestaWizard">
    <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Identificación</strong><small>Responsable</small></div>
    <div class="wizard-step" data-step="2"><span class="wizard-dot"></span><strong>Naturaleza</strong><small>Tipo y materia</small></div>
    <div class="wizard-step" data-step="3"><span class="wizard-dot"></span><strong>Diagnóstico</strong><small>Justificación</small></div>
    <div class="wizard-step" data-step="4"><span class="wizard-dot"></span><strong>Impacto</strong><small>Trámites y SyD</small></div>
    <div class="wizard-step" data-step="5"><span class="wizard-dot"></span><strong>Fundamento</strong><small>Jurídico</small></div>
    <div class="wizard-step" data-step="6"><span class="wizard-dot"></span><strong>Anexos</strong><small>Envío</small></div>
  </div>

  <form method="POST" action="{{ route('propuestas.store') }}" id="propForm" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="accion" id="accionCampo" value="borrador">

    {{-- ============ PASO 1: Identificación y responsable ============ --}}
    {{-- Rubros 1.0, 1.1, 4.0 --}}
    <div class="wz-card wz-panel activo" data-panel="1">
      <div class="wz-head">
        <div>
          <h3>Identificación y responsable</h3>
          <p>Quién dará seguimiento a la propuesta y su nombre preliminar.</p>
        </div>
      </div>
      <div class="wizard-fields">
        @php
          // El responsable es el enlace (usuario logueado): su nombre y cargo
          // precargan los campos, editables. La dependencia se toma del usuario.
          // El sujeto obligado (titular) se muestra aparte como informativo.
          $depUsuario = auth()->user()->dependencia;
          $titular    = $depUsuario?->sujetoObligado;
        @endphp

        <x-field-help label="Dependencia" class="span-2">
          <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">
          <input type="text" value="{{ $depUsuario->nombre ?? 'Sin dependencia asignada' }}"
            disabled class="u-input-disabled">
        </x-field-help>

        <x-field-help label="Sujeto obligado (titular)" class="span-2">
          <input type="text"
            value="{{ $titular?->nombre ? $titular->nombre.' — '.$titular->cargo : 'Sin titular asignado' }}"
            readonly class="u-input-readonly">
          @unless($titular)
            <small class="help-small">Tu dependencia no tiene titular registrado. Pídele al administrador que lo agregue en Catálogos → Sujetos obligados.</small>
          @endunless
        </x-field-help>

        <x-field-help label="Nombre completo del responsable" class="span-2">
          <input type="text" name="responsable_nombre"
            value="{{ old('responsable_nombre', auth()->user()->name) }}"
            placeholder="Nombre completo del responsable">
        </x-field-help>

        <x-field-help label="Cargo del responsable" class="span-2">
          <input type="text" name="responsable_cargo"
            value="{{ old('responsable_cargo', auth()->user()->cargo) }}"
            placeholder="Cargo del responsable">
        </x-field-help>

        <x-field-help label="Nombre preliminar de la Propuesta Regulatoria" class="span-2">
          <input type="text" name="nombre" id="nombrePropuesta" value="{{ old('nombre') }}" required
            placeholder="Nombre preliminar de la propuesta">
        </x-field-help>
      </div>

      <div class="wz-foot">
        <span></span>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-primary" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 2: Naturaleza de la propuesta ============ --}}
    {{-- Rubros 2.0, 3.0, 5.0, 6.0 --}}
    <div class="wz-card wz-panel" data-panel="2">
      <div class="wz-head">
        <div>
          <h3>Naturaleza de la propuesta</h3>
          <p>Tipo de regulación, materia, sectores impactados y fecha tentativa.</p>
        </div>
      </div>
      <div class="wizard-fields">
        <x-field-help label="Tipo de Regulación">
          <select name="tipo_regulacion" id="tipoRegulacionSelect" onchange="toggleTipoRegulacionOtro()">
            <option value="">— Seleccione —</option>
            @foreach(['Ley', 'Reglamento', 'Decreto', 'Acuerdo', 'Norma municipal', 'Lineamientos', 'Reglas de Operación', 'Circular'] as $tr)
              <option value="{{ $tr }}" @selected(old('tipo_regulacion') == $tr)>{{ $tr }}</option>
            @endforeach
            <option value="Otro" @selected(old('tipo_regulacion') == 'Otro')>Otro</option>
          </select>
        </x-field-help>

        <div id="tipoRegulacionOtroWrap" class="span-2" style="display:none">
          <x-field-help label="Especifique el tipo de regulación">
            <input type="text" name="tipo_regulacion_otro" value="{{ old('tipo_regulacion_otro') }}"
              placeholder="Otro tipo de regulación">
          </x-field-help>
        </div>

        <x-field-help label="Materia sobre la cual versará la Propuesta" class="span-2">
          <input type="text" name="materia" value="{{ old('materia') }}"
            placeholder="Materia de la propuesta">
        </x-field-help>

        <x-field-help label="Sectores o grupos potencialmente impactados" class="span-2">
          <select name="sector_id" id="sectorSelect">
            <option value="">— Seleccione un sector —</option>
            @foreach($sectores as $sec)
              <option value="{{ $sec->id }}" @selected(old('sector_id') == $sec->id)>{{ $sec->nombre }}</option>
            @endforeach
          </select>
        </x-field-help>

        <x-field-help label="Detalle de sectores o grupos (texto libre)" class="span-2">
          <textarea name="sectores_impactados" rows="2"
            placeholder="Describa los sectores o grupos impactados">{{ old('sectores_impactados') }}</textarea>
        </x-field-help>

        <x-field-help label="Fecha tentativa de presentación">
          <input type="date" name="fecha_tentativa" value="{{ old('fecha_tentativa') }}">
        </x-field-help>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <button type="button" class="btn btn-primary" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 3: Diagnóstico y justificación ============ --}}
    {{-- Rubros 7.0, 8.0, 9.0, 10.0, 11.0 --}}
    <div class="wz-card wz-panel" data-panel="3">
      <div class="wz-head">
        <div>
          <h3>Diagnóstico y justificación</h3>
          <p>Por qué es necesaria la propuesta, qué problema resuelve y qué efectos genera.</p>
        </div>
      </div>
      <div class="wizard-fields">
        <x-field-help label="Justificación para emitir la propuesta" class="span-2">
          <textarea name="justificacion" rows="3"
            placeholder="Justificación de la propuesta">{{ old('justificacion') }}</textarea>
        </x-field-help>

        <x-field-help label="Problemática que se pretende resolver" class="span-2">
          <textarea name="problematica" rows="3"
            placeholder="Problemática a resolver">{{ old('problematica') }}</textarea>
        </x-field-help>

        <x-field-help label="Alternativas consideradas" class="span-2">
          <textarea name="alternativas" rows="3"
            placeholder="Alternativas consideradas">{{ old('alternativas') }}</textarea>
        </x-field-help>

        <x-field-help label="Posibles beneficios que generará" class="span-2">
          <textarea name="beneficios" rows="3"
            placeholder="Beneficios esperados">{{ old('beneficios') }}</textarea>
        </x-field-help>

        <x-field-help label="Posibles costos burocráticos que generará" class="span-2">
          <textarea name="costos_burocraticos" rows="3"
            placeholder="Costos burocráticos">{{ old('costos_burocraticos') }}</textarea>
        </x-field-help>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <button type="button" class="btn btn-primary" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 4: Impacto operativo ============ --}}
    {{-- Rubro 12 (trámites impactados) + Rubros 13/14 (acciones SyD) --}}
    <div class="wz-card wz-panel" data-panel="4">
      <div class="wz-head">
        <div>
          <h3>Trámites e impacto operativo</h3>
          <p>Trámites que impacta la propuesta y acciones de simplificación/digitalización asociadas.</p>
        </div>
      </div>

      {{-- Rubro 12: trámites impactados con buscador --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Trámites y servicios en los que impacta</strong>
          <small>Indique para cada trámite o servicio si la propuesta lo crea, modifica o elimina. Solo los que no estén en el Portal Ciudadano Único.</small>
        </div>
        <div class="wz-bloque-body">
          <x-field-help label="Buscar trámite o servicio por nombre, folio u homoclave" class="span-2">
            <input type="text" id="buscadorTramiteImpacto" placeholder="Escriba al menos 2 letras..." autocomplete="off">
          </x-field-help>
          <div id="resultadosTramiteImpacto" class="buscador-resultados"></div>

          {{-- Lista de trámites agregados; cada uno genera inputs ocultos del array tramites_impacto[] --}}
          <div id="listaTramitesImpacto" class="impacto-lista"></div>
          <p id="impactoVacio" class="impacto-vacio">Aún no se han agregado trámites impactados.</p>
        </div>
      </div>

      {{-- Rubro 13: catálogo de tipos de acciones de simplificación. --}}
      {{-- Cada tipo marcado abre su textarea para explicar cómo se aplicará. --}}
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
              <div class="accion-item">
                <label class="accion-check">
                  <input type="checkbox" value="{{ $accion }}" data-target="simpExp{{ $idx }}"
                    onchange="toggleAccionExp(this)">
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="simpExp{{ $idx }}" style="display:none">
                  <textarea name="acciones_simplificacion[{{ $accion }}]" rows="2" disabled
                    placeholder="Explique cómo se aplicará esta acción"></textarea>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- Rubro 14: catálogo de tipos de acciones de digitalización. --}}
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
              <div class="accion-item">
                <label class="accion-check">
                  <input type="checkbox" value="{{ $accion }}" data-target="digExp{{ $idx }}"
                    onchange="toggleAccionExp(this)">
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="digExp{{ $idx }}" style="display:none">
                  <textarea name="acciones_digitalizacion[{{ $accion }}]" rows="2" disabled
                    placeholder="Explique cómo se aplicará esta acción"></textarea>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <button type="button" class="btn btn-primary" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 5: Fundamento jurídico ============ --}}
    {{-- Rubros 15.0, 16.0 --}}
    <div class="wz-card wz-panel" data-panel="5">
      <div class="wz-head">
        <div>
          <h3>Fundamento jurídico</h3>
          <p>Normativa que faculta la propuesta y su impacto en comercio o inversión.</p>
        </div>
      </div>
      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Fundamento jurídico para emitir la Propuesta</strong>
          <small>Normativa que establece la facultad para emitir, modificar, derogar o abrogar la propuesta.</small>
        </div>
        <div class="wz-bloque-body">
          <x-citar-regulacion label="Regulaciones que dan fundamento a la propuesta" />
          <x-field-help label="Fundamento jurídico (texto)" class="span-2">
            <textarea name="fundamento_juridico" rows="3"
              placeholder="Fundamento jurídico">{{ old('fundamento_juridico') }}</textarea>
          </x-field-help>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Impacto en el comercio o la inversión</strong>
          <small>De ser el caso. Indique si la propuesta afecta competitividad, costos, requisitos o procesos comerciales o de inversión.</small>
        </div>
        <div class="wz-bloque-body">
          @php $impComercio = old('impacta_comercio_inversion', '0'); @endphp
          <div class="wz-sino">
            <label><input type="radio" name="impacta_comercio_inversion" value="1" onchange="prToggleComercio(true)" @checked($impComercio==='1')> Sí</label>
            <label><input type="radio" name="impacta_comercio_inversion" value="0" onchange="prToggleComercio(false)" @checked($impComercio==='0')> No</label>
          </div>
          <div class="wz-detalle {{ $impComercio==='1' ? 'visible' : '' }}" id="prComercioDetalle">
            <x-field-help label="Describa el impacto comercial/de inversión" class="span-2">
              <textarea name="impacto_comercio" rows="3"
                placeholder="Describa el impacto...">{{ old('impacto_comercio') }}</textarea>
            </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <button type="button" class="btn btn-primary" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 6: Anexos y envío ============ --}}
    {{-- Rubro 17.0 --}}
    <div class="wz-card wz-panel" data-panel="6">
      <div class="wz-head">
        <div>
          <h3>Anexos y envío</h3>
          <p>Proyecto de la propuesta y anexos de soporte. Máximo 9 archivos.</p>
        </div>
      </div>
      <div class="wz-bloque">
        <div class="wz-bloque-head">
          <strong>Anexos</strong>
          <small>La plataforma admite hasta nueve archivos. Si supera el límite, infórmelo en observaciones.</small>
        </div>
        <div class="wz-bloque-body">
          @php $presentaProy = old('presenta_proyecto', '0'); @endphp
          <x-field-help label="¿Presenta el proyecto de la Propuesta Regulatoria?">
            <div class="wz-sino">
              <label><input type="radio" name="presenta_proyecto" value="1" @checked($presentaProy==='1')> Sí</label>
              <label><input type="radio" name="presenta_proyecto" value="0" @checked($presentaProy==='0')> No</label>
            </div>
          </x-field-help>

          <x-field-help label="Anexos (hasta 9 archivos)" class="span-2">
            <x-carga-archivos name="anexos" :multiple="true" accept=".pdf,.docx,.xlsx,.jpg,.png" :maxMb="10" />
          </x-field-help>

          <x-field-help label="Observaciones y/o comentarios" class="span-2">
            <textarea name="observaciones" rows="2"
              placeholder="Observaciones y/o comentarios">{{ old('observaciones') }}</textarea>
          </x-field-help>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-outline" onclick="enviarPropuesta('borrador')">Guardar borrador</button>
          <button type="button" class="btn btn-success" onclick="enviarPropuesta('enviar')">Guardar y enviar</button>
        </div>
      </div>
    </div>

  </form>
</div>{{-- /page-default --}}

@push('scripts')
<script>
  window.PROP = {
    apiTramitesBuscar: "{{ route('api.tramites.buscar') }}"
  };
</script>
<script>
(function () {
  // ---- Navegación del wizard (6 pasos lineales) ----
  var paso = 1;
  var ULTIMO = 6;

  function pintarStepper(n) {
    document.querySelectorAll('#propuestaWizard [data-step]').forEach(function (s) {
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
    if (dir > 0 && !validarPaso(paso)) return;
    var n = paso + dir;
    if (n >= 1 && n <= ULTIMO) mostrar(n);
  };

  // Validación mínima por paso. El paso 1 exige el nombre preliminar (rubro 4).
  function validarPaso(n) {
    var panel = document.querySelector('[data-panel="' + n + '"]');
    if (panel) clearErrors(panel);
    if (n === 1) {
      var nombre = document.getElementById('nombrePropuesta');
      if (nombre && !nombre.value.trim()) {
        showError(nombre, 'El nombre preliminar de la propuesta es obligatorio.');
        nombre.focus();
        return false;
      }
    }
    return true;
  }
  // Mismo patrón que el create de trámites: error en rojo, abajo del campo.
  function showError(field, msg) {
    field.classList.add('field-error-input');
    field.style.borderColor = '#dc2626';
    var err = document.createElement('p');
    err.className = 'field-error';
    err.textContent = msg;
    err.style.cssText = 'color:#dc2626;font-size:12px;margin:4px 0 0;';
    field.parentElement.appendChild(err);
  }
  function clearErrors(panel) {
    panel.querySelectorAll('.field-error').forEach(function (e) { e.remove(); });
    panel.querySelectorAll('.field-error-input').forEach(function (el) {
      el.classList.remove('field-error-input');
      el.style.borderColor = '';
    });
  }

  // ---- Toggles dinámicos ----
  window.toggleTipoRegulacionOtro = function () {
    var sel = document.getElementById('tipoRegulacionSelect');
    var wrap = document.getElementById('tipoRegulacionOtroWrap');
    if (sel && wrap) wrap.style.display = (sel.value === 'Otro') ? '' : 'none';
  };
  // Rubro 16: muestra el detalle del impacto comercial solo si se elige "Sí".
  window.prToggleComercio = function (mostrar) {
    var det = document.getElementById('prComercioDetalle');
    if (det) det.classList.toggle('visible', !!mostrar);
  };
  // Rubros 13/14: al marcar un tipo de acción, abre su textarea de explicación.
  // Mismo comportamiento que el catálogo de la Agenda SyD (toggleAccionExp).
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

  // ---- Rubro 12: buscador de trámites impactados ----
  var tramitesAgregados = {}; // id -> {id, nombre}

  function renderImpactos() {
    var cont = document.getElementById('listaTramitesImpacto');
    var vacio = document.getElementById('impactoVacio');
    if (!cont) return;
    var ids = Object.keys(tramitesAgregados);
    vacio.style.display = ids.length ? 'none' : '';
    cont.innerHTML = ids.map(function (id, i) {
      var t = tramitesAgregados[id];
      return '<div class="impacto-fila">' +
        '<input type="hidden" name="tramites_impacto[' + i + '][tramite_id]" value="' + t.id + '">' +
        '<span class="impacto-nombre">' + escaparHtml(t.nombre) + '</span>' +
        '<select name="tramites_impacto[' + i + '][accion]" class="impacto-accion">' +
          '<option value="crea">Crea</option>' +
          '<option value="modifica" selected>Modifica</option>' +
          '<option value="elimina">Elimina</option>' +
        '</select>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="quitarImpacto(\'' + t.id + '\')">Quitar</button>' +
      '</div>';
    }).join('');
  }
  window.quitarImpacto = function (id) {
    delete tramitesAgregados[id];
    renderImpactos();
  };
  function agregarImpacto(id, nombre) {
    if (tramitesAgregados[id]) return;
    tramitesAgregados[id] = { id: id, nombre: nombre };
    renderImpactos();
  }

  var buscador = document.getElementById('buscadorTramiteImpacto');
  if (buscador) {
    var timer = null;
    buscador.addEventListener('input', function () {
      var q = buscador.value.trim();
      var cont = document.getElementById('resultadosTramiteImpacto');
      if (q.length < 2) { cont.innerHTML = ''; return; }
      clearTimeout(timer);
      timer = setTimeout(function () {
        // El endpoint api.tramites.buscar devuelve { resultados: [...] } con
        // campos nombre, homoclave, dependencia (mismo que usa la Agenda SyD).
        fetch(window.PROP.apiTramitesBuscar + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var items = (data && data.resultados) || [];
            if (!items.length) { cont.innerHTML = '<div class="wz-buscando">Sin resultados.</div>'; return; }
            cont.innerHTML = items.map(function (t) {
              var nombre = t.nombre || ('Trámite #' + t.id);
              return '<div class="wz-result" onclick="window.__addImpacto(' + t.id + ',\'' + escaparAttr(nombre) + '\')">' +
                '<strong>' + escaparHtml(nombre) + '</strong>' +
                '<span>' + escaparHtml(t.homoclave || 'Sin folio') + ' · ' + escaparHtml(t.dependencia || '—') + '</span>' +
              '</div>';
            }).join('');
          })
          .catch(function () { cont.innerHTML = '<div class="wz-buscando">Error al buscar.</div>'; });
      }, 250);
    });
  }
  window.__addImpacto = function (id, nombre) {
    agregarImpacto(String(id), nombre);
    document.getElementById('resultadosTramiteImpacto').innerHTML = '';
    if (buscador) buscador.value = '';
  };

  // ---- Envío ----
  window.enviarPropuesta = function (modo) {
    if (!validarPaso(1)) { mostrar(1); return; }
    document.getElementById('accionCampo').value = modo;
    document.getElementById('propForm').submit();
  };

  // Ruta que usa el botón flotante "Guardar borrador": pasa por enviarPropuesta,
  // así respeta la validación del paso 1 antes de guardar.
  window.guardarBorradorWizard = function () { window.enviarPropuesta('borrador'); };

  // ---- Utilidades anti-inyección ----
  function escaparHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escaparAttr(s) {
    return String(s == null ? '' : s).replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  // Estado inicial
  toggleTipoRegulacionOtro();
  // Limpiar el error del nombre en cuanto el usuario empiece a escribir.
  var campoNombre = document.getElementById('nombrePropuesta');
  if (campoNombre) {
    campoNombre.addEventListener('input', function () {
      this.classList.remove('field-error-input');
      this.style.borderColor = '';
      var e = this.parentElement.querySelector('.field-error');
      if (e) e.remove();
    });
  }
})();
</script>
@endpush
@endsection