@extends('layouts.app')
@section('title', 'Registrar Acción de Agenda')

@section('content')
<style>
  .wz-wrap { max-width:1000px; }
  .wz-card { background:var(--surface); border-radius:var(--radius-lg); box-shadow:var(--shadow); padding:24px; }
  .wz-head { display:flex; align-items:flex-start; gap:14px; margin-bottom:20px; }
  .wz-head-ic { width:44px; height:44px; border-radius:var(--radius-pill); background:var(--secondary-container); flex:0 0 auto; }
  .wz-head h3 { margin:0 0 2px; font-size:20px; color:var(--text); }
  .wz-head p { margin:0; font-size:14px; color:var(--muted); }
  .wz-panel { display:none; }
  .wz-panel.activo { display:block; }
  .wz-opts { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; margin-bottom:8px; }
  .wz-opt { padding:22px 18px; border:1.5px solid var(--surface-high); border-radius:var(--radius); background:var(--surface); cursor:pointer; text-align:center; font-weight:600; color:var(--text); transition:border-color .15s, background .15s; }
  .wz-opt:hover { border-color:var(--primary); }
  .wz-opt.sel { border-color:var(--primary-container); background:var(--primary-container); color:#fff; }
  .wz-opt small { display:block; font-weight:400; font-size:12px; color:var(--muted); margin-top:4px; }
  .wz-opt.sel small { color:#fff; }
  .wz-foot { display:flex; justify-content:space-between; gap:12px; margin-top:22px; }
  .wz-foot-right { display:flex; gap:8px; }
  .wz-sub { border:1px solid var(--surface-high); border-radius:var(--radius); padding:18px; margin-top:4px; }
  .wz-sub-tit { color:var(--primary); font-weight:600; font-size:15px; margin:0 0 14px; }
  .wz-result { padding:10px 12px; border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:6px; cursor:pointer; }
  .wz-result:hover { border-color:var(--primary); }
  .wz-result strong { display:block; color:var(--text); font-size:14px; }
  .wz-result span { font-size:12px; color:var(--muted); }
  .wz-buscando { font-size:13px; color:var(--muted); padding:8px 0; }
  .wz-checks { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-bottom:18px; }
  /* Paquete 3: catálogo de acciones con explicación individual */
  .acciones-catalogo { border:1px solid var(--surface-high); border-radius:var(--radius); padding:14px; }
  .acciones-titulo { display:block; color:var(--text); font-size:14px; margin-bottom:10px; }
  .acciones-titulo small { color:var(--muted); font-weight:normal; }
  .accion-item { border-bottom:1px solid var(--surface-high); padding:8px 0; }
  .accion-item:last-child { border-bottom:none; }
  .accion-check { display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-size:13px; color:var(--text); }
  .accion-check input { margin-top:3px; }
  .accion-exp { margin-top:8px; padding-left:24px; }
  .accion-exp textarea {
    width:100%;
    padding:8px 12px;
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    font-family:inherit;
    font-size:14px;
    color:var(--text);
    background:var(--surface);
    resize:vertical;
    min-height:60px;
  }
  .accion-exp textarea:focus {
    outline:none;
    border-color:var(--primary);
  }
  .wz-check { display:flex; gap:10px; padding:14px 16px; border:1px solid var(--surface-high); border-radius:var(--radius); cursor:pointer; }
  .wz-check input { margin-top:3px; }
  .wz-check strong { display:block; color:var(--text); font-size:14px; }
  .wz-check small { font-size:12px; color:var(--muted); }
  .wz-tipo { border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:14px; overflow:hidden; }
  .wz-tipo-head { background:var(--surface-low); padding:12px 16px; color:var(--text); font-weight:600; border-bottom:1px solid var(--surface-high); }
  .wz-tipo-body { padding:16px; }
  /* Sub-bloque con título (BLOQUE dentro de un paso) */
  .wz-bloque { border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:16px; }
  .wz-bloque-head { padding:12px 16px; border-bottom:1px solid var(--surface-high); }
  .wz-bloque-head strong { color:var(--text); font-size:15px; }
  .wz-bloque-head small { display:block; color:var(--muted); font-size:12px; }
  .wz-bloque-body { padding:16px; }
  /* Pregunta Sí/No con detalle condicional */
  .wz-sino { display:flex; gap:20px; align-items:center; margin:6px 0; }
  .wz-sino label { display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px; color:var(--text); }
  .wz-sino input[type="radio"] { width:16px; height:16px; margin:0; accent-color:var(--primary-container); }
  .wz-detalle { display:none; margin-top:8px; }
  .wz-detalle.visible { display:block; }
  .wz-sublabel { display:block; font-size:12px; font-weight:500; color:var(--muted); margin-bottom:4px; }
</style>

<div class="page-default wz-wrap">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Registrar Acción de Agenda</h2>
      <p class="nowrap">Complete el proceso de simplificación o digitalización paso a paso.</p>
    </div>
    <div class="head-actions">
      <x-btn-ejemplo tipo="agenda_syd" />
      <a href="{{ route('agenda.index') }}" class="btn btn-outline">Volver a agenda</a>
    </div>
  </div>

  {{-- Stepper estándar del sistema (mismas clases y CSS que los demás wizards) --}}
  <div class="wizard-stepper" id="agendaWizard">
    <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Inicio</strong><small>Trámite</small></div>
    <div class="wizard-step" data-step="2" data-opcional="tramite"><span class="wizard-dot"></span><strong>Trámite</strong><small>Datos</small></div>
    <div class="wizard-step" data-step="3"><span class="wizard-dot"></span><strong>Alcance</strong><small>Agenda</small></div>
    <div class="wizard-step" data-step="4"><span class="wizard-dot"></span><strong>Acciones</strong><small>Mejora</small></div>
    <div class="wizard-step" data-step="5"><span class="wizard-dot"></span><strong>Fundamento</strong><small>Requisitos</small></div>
    <div class="wizard-step" data-step="6"><span class="wizard-dot"></span><strong>Seguimiento</strong><small>Fechas</small></div>
  </div>

  <form method="POST" action="{{ route('agenda.store') }}" id="wzForm" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="modo_tramite" id="modoTramite" value="">
    <input type="hidden" name="tramite_id" id="tramiteIdSel" value="">
    <input type="hidden" name="alcance" id="alcanceCampo" value="">
    <input type="hidden" name="accion" id="accionCampo" value="borrador">

    @if($errors->any())
      <div class="toast toast-error u-toast-inline-top">
        <strong>Corrija:</strong>
        <ul style="margin:8px 0 0 16px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    {{-- ============ PASO 1: Inicio (bifurcación) ============ --}}
    <div class="wz-card wz-panel activo" data-panel="1">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>¿El trámite ya está cargado?</h3>
          <p>Elija si se precargará desde el catálogo o si se capturará desde cero.</p>
        </div>
      </div>
      <div class="wz-opts">
        <div class="wz-opt" id="opcExistente" onclick="elegirCamino('existente')">
          Sí, seleccionar trámite existente
          <small>Lo busco en el catálogo y lo ligo.</small>
        </div>
        <div class="wz-opt" id="opcNuevo" onclick="elegirCamino('nuevo')">
          No, registrar desde cero
          <small>Capturo el trámite completo aquí.</small>
        </div>
      </div>

      {{-- Buscador (camino A) --}}
      <div class="wz-sub" id="bloqueBuscar" style="margin-top:16px; display:none">
        <p class="wz-sub-tit">Precargar trámite existente</p>
        <x-field-help label="Buscar trámite, folio u homoclave" class="span-2">
                <input type="text" id="buscadorTramite" placeholder="Escribe al menos 2 letras..." autocomplete="off">
              </x-field-help>
        <div id="resultadosTramite"></div>
        <div id="tramiteElegido" style="display:none; margin-top:8px" class="assist-box">
          <strong>Seleccionado:</strong> <span id="tramiteElegidoNombre"></span>
          <button type="button" class="btn btn-outline btn-sm" style="margin-left:8px" onclick="limpiarTramite()">Cambiar</button>
        </div>
        <div class="assist-box" style="margin-top:12px">
          <strong>¿No aparece?</strong> Si el trámite no existe en el catálogo, elija "registrar desde cero".
        </div>
      </div>

      <div class="wz-foot">
        <span></span>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 3: Alcance ============ --}}
    <div class="wz-card wz-panel" data-panel="3">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Alcance del registro</h3>
          <p>Seleccione qué agenda desea integrar.</p>
        </div>
      </div>
      <div class="wz-opts">
        <div class="wz-opt" data-alcance="simplificacion" onclick="elegirAlcance(this)">Solo simplificación</div>
        <div class="wz-opt" data-alcance="digitalizacion" onclick="elegirAlcance(this)">Solo digitalización</div>
        <div class="wz-opt" data-alcance="ambas" onclick="elegirAlcance(this)">Simplificación y digitalización</div>
      </div>
      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 2: Trámite (formulario completo, solo camino nuevo) ============ --}}
    <div class="wz-card wz-panel" data-panel="2">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Datos base del trámite</h3>
          <p id="precargaSub">Identificación e información general del trámite.</p>
        </div>
      </div>

      {{-- BLOQUE I: Identificación --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Identificación del trámite</strong><small>Dependencia, nombre, clave y fundamento.</small></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Nombre oficial">
                <input name="tramite_nombre_oficial" type="text" maxlength="500" placeholder="Nombre del trámite o servicio">
              </x-field-help>
            <x-field-help label="Dependencia responsable">
                <input type="hidden" name="tramite_dependencia_id" value="{{ auth()->user()->dependencia_id }}">
                <input type="text" value="{{ auth()->user()->dependencia->nombre ?? 'Sin dependencia asignada' }}" disabled class="u-input-disabled">
              </x-field-help>
            <x-field-help label="Unidad administrativa">
                <select name="tramite_unidad_id">
                <option value="">Seleccione</option>
                @foreach(($misUnidades ?? []) as $u)
                  <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                @endforeach
              </select>
              </x-field-help>
            <x-field-help label="Persona servidora pública responsable">
                <input name="tramite_servidor_publico" type="text" placeholder="Nombre completo">
              </x-field-help>
            <x-field-help label="Sector principal">
                <select name="tramite_sector_id" id="selSector" onchange="cargarSubsectores()">
                <option value="">Seleccione</option>
                @foreach(($sectores ?? []) as $s)
                  <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                @endforeach
              </select>
              </x-field-help>
            <x-field-help label="Subsector o actividad relacionada">
                <select name="tramite_subsector_id" id="selSubsector" disabled>
                  <option value="">Primero elija un sector</option>
                </select>
              </x-field-help>
            <x-field-help label="Clave u homoclave" class="span-2">
                <input name="tramite_homoclave" id="homoclaveAgenda" type="text" readonly
                       class="u-input-readonly"
                       placeholder="Se generará al elegir la unidad administrativa">
                <small class="help-small">Se genera automáticamente: {{ config('punta.prefijo_homoclave', 'LPZ') }}-(siglas dependencia)-(siglas unidad)-(consecutivo).</small>
              </x-field-help>
            <x-field-help label="Tipo de registro">
                <select name="tramite_tipo_registro">
                <option value="TR">Trámite</option>
                <option value="SV">Servicio</option>
              </select>
              </x-field-help>
            <x-citar-regulacion />
            <x-field-help label="Resumen del fundamento" class="span-2">
                <textarea name="tramite_fundamento" rows="2" placeholder="Ej. Reglamento de Comercio del Municipio de La Paz, artículo __, fracción __"></textarea>
              </x-field-help>
          </div>
        </div>
      </div>

      {{-- BLOQUE II: Información general --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Información general</strong><small>Objetivo, población, volumen y relación con otros trámites.</small></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Objetivo del trámite" class="span-2">
                <textarea name="tramite_objetivo" rows="3" placeholder="Describa qué resuelve o qué beneficio otorga..."></textarea>
              </x-field-help>
            <x-field-help label="Población objetivo">
                <select name="tramite_dirigido_a">
                <option value="ambas">Ciudadanía en general</option>
                <option value="fisica">Personas físicas</option>
                <option value="moral">Personas morales</option>
              </select>
              </x-field-help>
            <x-field-help label="Volumen anual estimado">
                <input name="tramite_volumen_anual" type="number" min="0" placeholder="0">
              </x-field-help>
            <x-field-help label="Frecuencia">
                <select name="tramite_frecuencia">
                  <option value="">Seleccione</option>
                  <option value="Alta">Alta</option>
                  <option value="Media">Media</option>
                  <option value="Baja">Baja</option>
                  <option value="Eventual">Eventual</option>
                </select>
              </x-field-help>
            <x-field-help label="Plazo máximo de resolución">
                <input name="tramite_plazo_resolucion_cantidad" type="number" min="0" placeholder="0">
              </x-field-help>
            <x-field-help label="Unidad de plazo">
                <select name="tramite_plazo_resolucion_unidad">
                <option value="habiles">Días hábiles</option>
                <option value="naturales">Días naturales</option>
                <option value="meses">Meses</option>
              </select>
              </x-field-help>
            {{-- Grupos de atención prioritaria: catálogo oficial de 11 categorías
                 (LNETB Art. 19 fracc. III). Cuando el enlace vincula un trámite
                 existente, se precargan automáticamente desde ese trámite por JS.
                 Cuando crea un trámite nuevo desde aquí, los marca y se guardan
                 en el trámite creado. --}}
            @php
              $catGruposAgenda = [
                'No Aplica',
                'Niñas, niños y adolescentes',
                'Mujeres',
                'Personas mayores',
                'Personas con discapacidad',
                'Personas pertenecientes a pueblos y comunidades indígenas o afrodescendientes',
                'Personas pertenecientes a la comunidad LGBTTTI',
                'Personas migrantes o refugiadas',
                'Personas víctimas de violaciones a derechos humanos',
                'Personas en situación de calle',
                'Personas periodistas y defensoras de DDHH',
              ];
            @endphp
            <x-field-help label="Grupos de atención prioritaria" class="span-2">
              <div class="check-grid-compact" id="agendaGruposGrid">
                @foreach($catGruposAgenda as $opcion)
                  <label class="check-chip">
                    <input type="checkbox" name="tramite_grupos_atencion[]" value="{{ $opcion }}">
                    <span>{{ $opcion }}</span>
                  </label>
                @endforeach
              </div>
            </x-field-help>
            {{-- Pregunta diagnóstico: relacionados --}}
            <x-field-help label="¿Guarda relación con otros trámites o servicios?" class="span-2">
              <div class="wz-sino">
                <label><input type="radio" name="tramite_tiene_relacionados" value="1" onclick="toggleDetalle('detRelacionados',true)"> Sí</label>
                <label><input type="radio" name="tramite_tiene_relacionados" value="0" checked onclick="toggleDetalle('detRelacionados',false)"> No</label>
              </div>
              <div class="wz-detalle" id="detRelacionados">
                <label class="wz-sublabel">Tipo de relación</label>
                <select name="tramite_tipo_relacion">
                  <option value="">Seleccione el tipo</option>
                  <option value="naturaleza">Naturaleza (se resuelven de forma similar o igual)</option>
                  <option value="secuencia">Secuencia (uno se requiere para iniciar el otro, distinta materia)</option>
                  <option value="dependencia_funcional">Dependencia funcional (uno se requiere para el otro, misma materia)</option>
                </select>
                <label class="wz-sublabel" style="margin-top:8px">Trámites con los que guarda relación</label>
                <input name="tramite_relacionados_detalle" type="text" placeholder="Enliste los trámites o servicios">
              </div>
            </x-field-help>
          </div>
        </div>
      </div>

      {{-- BLOQUE: Costos (alimenta el cálculo del costo burocrático) --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Costos</strong><small>Derechos y copias. El costo burocrático se calcula automáticamente al guardar.</small></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Pago de derechos" class="span-2">
                <div id="derechosLista" class="derechos-lista"></div>
                <div class="derechos-pie" style="display:flex; justify-content:space-between; align-items:center; margin-top:8px">
                  <button type="button" class="btn btn-outline btn-sm" onclick="agregarDerecho()">+ Agregar derecho</button>
                  <span class="derechos-total">Total derechos: <strong id="derechosTotal">$0.00 MXN</strong></span>
                </div>
                <input type="hidden" name="derechos_json" id="derechosJson" value="[]">
              </x-field-help>
            <x-field-help label="Número de copias">
                <input name="tramite_copias_cantidad" type="number" min="0" placeholder="0">
              </x-field-help>
            <x-field-help label="Precio por copia (pesos)">
                <input name="tramite_copias_precio" type="number" min="0" step="0.01" placeholder="0.00">
              </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 4: Acciones de Mejora (Bloques III, V, VI) ============ --}}
    <div class="wz-card wz-panel" data-panel="4">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Acciones de Mejora</h3>
          <p>Operación actual del trámite y qué se simplificará o digitalizará.</p>
        </div>
      </div>

      {{-- Costo burocrático heredado del trámite (solo lectura, camino A) --}}
      <div id="costoHeredado" style="display:none" class="wz-bloque">
        <div class="wz-bloque-head"><strong>Costo burocrático del trámite</strong><small>Calculado a partir de los datos del trámite (metodología ATDT).</small></div>
        <div class="wz-bloque-body">
          <div id="costoHeredadoCalculado">
            <div class="costo-heredado-grid">
              <div class="costo-item"><span>Costo Directo (CBD)</span><strong id="chCbd">—</strong></div>
              <div class="costo-item"><span>Costo Indirecto (CBI)</span><strong id="chCbi">—</strong></div>
              <div class="costo-item"><span>Costo Unitario (CBU)</span><strong id="chCbu">—</strong></div>
              <div class="costo-item"><span>Costo Total (CBT)</span><strong id="chCbt">—</strong></div>
              <div class="costo-item"><span>Categoría</span><strong id="chCat">—</strong></div>
            </div>
          </div>
          <div id="costoHeredadoSinCalcular" class="assist-box" style="display:none">
            Este trámite aún no tiene su costo burocrático calculado. Se calculará cuando el trámite se complete.
          </div>
        </div>
      </div>

      {{-- BLOQUE III: Operación y costos --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Operación y costos burocráticos</strong><small>Tiempos, visitas y procesos.</small></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Visitas requeridas">
                <input name="tramite_visitas_requeridas" type="number" min="0" placeholder="0">
              </x-field-help>
            <x-field-help label="Número de áreas que participan">
                <input name="tramite_num_areas" type="number" min="0" placeholder="0" id="numAreasInput" oninput="toggleAreasDetalle(this.value)">
              </x-field-help>
            <div id="areasDetalleWrap" style="display:none">
              <x-field-help label="¿Cuáles áreas participan?">
                <input name="tramite_areas_participantes" type="text" placeholder="Ej. Ventanilla, Jurídico, Tesorería...">
              </x-field-help>
            </div>
            <x-field-help label="Tiempo promedio de traslado por visita">
              <div style="display:flex; gap:6px; align-items:flex-end">
                <div style="flex:1"><label class="split-label">Horas</label><input name="tramite_tiempo_traslado_horas" type="number" min="0" placeholder="0"></div>
                <div style="flex:1"><label class="split-label">Minutos</label><input name="tramite_tiempo_traslado_min" type="number" min="0" max="59" placeholder="0"></div>
              </div>
            </x-field-help>
            <x-field-help label="Tiempo promedio de espera por visita">
              <div style="display:flex; gap:6px; align-items:flex-end">
                <div style="flex:1"><label class="split-label">Horas</label><input name="tramite_tiempo_espera_horas" type="number" min="0" placeholder="0"></div>
                <div style="flex:1"><label class="split-label">Minutos</label><input name="tramite_tiempo_espera_min" type="number" min="0" max="59" placeholder="0"></div>
              </div>
            </x-field-help>
            <x-field-help label="Tiempo promedio de atención por visita">
              <div style="display:flex; gap:6px; align-items:flex-end">
                <div style="flex:1"><label class="split-label">Horas</label><input name="tramite_tiempo_atencion_horas" type="number" min="0" placeholder="0"></div>
                <div style="flex:1"><label class="split-label">Minutos</label><input name="tramite_tiempo_atencion_min" type="number" min="0" max="59" placeholder="0"></div>
              </div>
            </x-field-help>
            {{-- Pregunta diagnóstico: redundantes --}}
            <x-field-help label="¿Existen procesos redundantes?" class="span-2">
              <div class="wz-sino">
                <label><input type="radio" name="tramite_tiene_redundantes" value="1" onclick="toggleDetalle('detRedundantes',true)"> Sí</label>
                <label><input type="radio" name="tramite_tiene_redundantes" value="0" checked onclick="toggleDetalle('detRedundantes',false)"> No</label>
              </div>
              <div class="wz-detalle" id="detRedundantes">
                <textarea name="tramite_redundantes_detalle" rows="2" placeholder="Describa cuáles procesos son redundantes"></textarea>
              </div>
            </x-field-help>
          </div>
        </div>
      </div>

      {{-- Paquete 3: Descripción general de la acción (SIEMPRE visible, obligatoria).
           Antes vivía dentro del bloque de Simplificación y se ocultaba al elegir
           "solo digitalización", rompiendo el guardado. Ahora es independiente. --}}
      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Descripción de la acción</strong><small>Resumen general de lo que se va a mejorar. Obligatorio.</small></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Descripción general de la acción" class="span-2">
              <textarea name="descripcion" rows="3" placeholder="Ej. Reducir requisitos y digitalizar el trámite de licencia de funcionamiento..."></textarea>
            </x-field-help>
          </div>
        </div>
      </div>

      {{-- #22: Aquí vivía un bloque de 4 checkboxes (Reducir requisitos,
           Reducir tiempos, Pago en línea, Expediente digital) que no tenían
           sustento en la metodología ATDT — no aparecen ni en la LNETB ni
           en los Lineamientos del Modelo Nacional. Los catálogos OFICIALES
           son los 10 de Simplificación (Art. 23 LNETB) y los 4 de
           Digitalización (Art. 24 LNETB), que se eligen en los bloques de
           abajo. NO restaurar este bloque. --}}

      {{-- BLOQUE V: Simplificación --}}
      <div class="wz-tipo" id="bloqueSimplificacion">
        <div class="wz-tipo-head">Simplificación</div>
        <div class="wz-tipo-body">
          {{-- Paquete 3: catálogo oficial de 10 acciones (rubro 14). Cada acción
               marcada abre su propio campo de explicación. Se guarda como objeto
               { "acción": "explicación" } en acciones_simplificacion (JSON). --}}
          <div class="acciones-catalogo">
            <strong class="acciones-titulo">Acciones de simplificación <small>(marque las que apliquen)</small></strong>
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
                  <input type="checkbox" name="simp_check[]" value="{{ $accion }}"
                    data-target="simpExp{{ $idx }}" onchange="toggleAccionExp(this)">
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="simpExp{{ $idx }}" style="display:none">
                  <textarea name="acciones_simplificacion[{{ $accion }}]" rows="2" disabled
                    placeholder="Explique cómo se aplicará esta acción"></textarea>
                </div>
              </div>
            @endforeach
          </div>

          <div class="wizard-fields" style="margin-top:14px">
            <x-field-help label="Meta esperada">
                <input name="meta" type="text" placeholder="Ej. Reducir 40% el tiempo">
              </x-field-help>
            <x-field-help label="Indicador de cumplimiento">
                <input name="indicador" type="text" placeholder="Ej. requisitos eliminados">
              </x-field-help>
            <x-field-help label="Indicador de avance">
                <input name="indicador_avance" type="text" placeholder="Ej. % de pasos digitalizados">
              </x-field-help>
            <x-field-help label="¿Deriva de recomendación de la autoridad?" class="span-2">
              <div class="wz-sino">
                <label><input type="radio" name="deriva_recomendacion" value="1" onclick="toggleDetalle('detRecomendacion',true)"> Sí</label>
                <label><input type="radio" name="deriva_recomendacion" value="0" checked onclick="toggleDetalle('detRecomendacion',false)"> No</label>
              </div>
              <div class="wz-detalle" id="detRecomendacion">
                <input name="recomendacion_detalle" type="text" placeholder="Indique la recomendación o referencia">
              </div>
            </x-field-help>
          </div>
        </div>
      </div>

      {{-- BLOQUE VI: Digitalización --}}
      <div class="wz-tipo" id="bloqueDigitalizacion">
        <div class="wz-tipo-head">Digitalización</div>
        <div class="wz-tipo-body">
          <div class="wizard-fields">
            <x-field-help label="Nivel actual de digitalización">
                <select name="nivel_actual">
                <option value="0">Nivel 0 — Sin digitalización</option>
                <option value="1">Nivel 1 — Eficiencia administrativa básica</option>
                <option value="2">Nivel 2 — Productividad y reducción de costos</option>
                <option value="3">Nivel 3 — Acceso electrónico transaccional</option>
                <option value="4">Nivel 4 — Experiencia ciudadana unificada</option>
                <option value="5">Nivel 5 — Innovación, transparencia y participación</option>
              </select>
              </x-field-help>
            <x-field-help label="Nivel meta de digitalización">
                <select name="nivel_meta">
                <option value="0">Nivel 0 — Sin digitalización</option>
                <option value="1">Nivel 1 — Eficiencia administrativa básica</option>
                <option value="2">Nivel 2 — Productividad y reducción de costos</option>
                <option value="3">Nivel 3 — Acceso electrónico transaccional</option>
                <option value="4">Nivel 4 — Experiencia ciudadana unificada</option>
                <option value="5">Nivel 5 — Innovación, transparencia y participación</option>
              </select>
              </x-field-help>
            <x-field-help label="Objetivo de la digitalización" class="span-2">
                <textarea name="descripcion_digital" rows="3" placeholder="Explique portal, pagos, firma o expediente digital..."></textarea>
              </x-field-help>
          </div>

          {{-- Paquete 3: catálogo oficial DIG de 8 acciones (distinto al SIMP).
               Cada acción marcada abre su explicación. Se guarda como objeto
               { "acción": "explicación" } en acciones_digitalizacion (JSON). --}}
          <div class="acciones-catalogo" style="margin-top:14px">
            <strong class="acciones-titulo">Acciones de digitalización <small>(marque las que apliquen)</small></strong>
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
                  <input type="checkbox" name="dig_check[]" value="{{ $accion }}"
                    data-target="digExp{{ $idx }}" onchange="toggleAccionExp(this)">
                  <span>{{ $accion }}</span>
                </label>
                <div class="accion-exp" id="digExp{{ $idx }}" style="display:none">
                  <textarea name="acciones_digitalizacion[{{ $accion }}]" rows="2" disabled
                    placeholder="Explique cómo se aplicará esta acción"></textarea>
                </div>
              </div>
            @endforeach
          </div>

          <div class="wizard-fields" style="margin-top:14px">
            {{-- Pregunta diagnóstico: interoperabilidad --}}
            <x-field-help label="¿Requiere interoperabilidad con otra institución?" class="span-2">
              <div class="wz-sino">
                <label><input type="radio" name="tramite_requiere_interop" value="1" onclick="toggleDetalle('detInterop',true)"> Sí</label>
                <label><input type="radio" name="tramite_requiere_interop" value="0" checked onclick="toggleDetalle('detInterop',false)"> No</label>
              </div>
              <div class="wz-detalle" id="detInterop">
                <input name="tramite_interop_detalle" type="text" placeholder="Indique con qué institución">
              </div>
            </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 5: Requisitos (Bloque IV) ============ --}}
    <div class="wz-card wz-panel" data-panel="5">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Requisitos del trámite</h3>
          <p>Documentos que el ciudadano debe presentar.</p>
        </div>
      </div>
      <div id="tramiteRequisitos" style="display:none; margin-bottom:16px" class="card card-pad">
        <strong style="display:block; margin-bottom:8px; font-size:13px">Requisitos heredados del trámite</strong>
        <p style="margin:0 0 10px; font-size:12px; color:var(--muted)">Estos requisitos vienen del trámite vinculado. Se editan desde el trámite, no aquí.</p>
        <ol id="tramiteRequisitosLista" class="requisitos-heredados"></ol>
      </div>
      <div id="reqLista"></div>
      <button type="button" class="btn btn-outline btn-sm" onclick="addReq()">+ Agregar requisito</button>
      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" onclick="wzNav(1)">Siguiente</button>
        </div>
      </div>
    </div>

    {{-- ============ PASO 6: Seguimiento (Bloques VII y VIII) ============ --}}
    <div class="wz-card wz-panel" data-panel="6">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Seguimiento y calendario</h3>
          <p>Estatus, periodo, fechas y responsable de seguimiento.</p>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Estatus actual</strong></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Estatus actual">
                <select name="estatus_inicial">
                <option value="pendiente">Pendiente</option>
                <option value="en_proceso">En proceso</option>
              </select>
              </x-field-help>
            <x-field-help label="Observaciones" class="span-2">
                <textarea name="observaciones" rows="2" placeholder="Observaciones del estatus actual..."></textarea>
              </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Calendario de implementación</strong></div>
        <div class="wz-bloque-body">
          <div class="wizard-fields">
            <x-field-help label="Dependencia (de la acción)">
                <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">
                <input type="text" value="{{ auth()->user()->dependencia->nombre ?? 'Sin dependencia asignada' }}" disabled class="u-input-disabled">
              </x-field-help>
            <x-field-help label="Responsable de seguimiento">
                <input name="responsable" type="text" value="{{ auth()->user()->name }}" placeholder="Usuario enlace">
              </x-field-help>
            <x-field-help label="Sujeto obligado">
                <input type="text" value="{{ auth()->user()->dependencia->nombre ?? '—' }}" readonly class="u-input-readonly">
              </x-field-help>
            <x-field-help label="Fecha de inicio">
                <input name="fecha_inicio" type="date">
              </x-field-help>
            <x-field-help label="Fecha estimada de conclusión">
                <input name="fecha_compromiso" type="date">
              </x-field-help>
          </div>
        </div>
      </div>

      <div class="wz-bloque">
        <div class="wz-bloque-head"><strong>Anexos</strong><small>Documentos de soporte (opcional).</small></div>
        <div class="wz-bloque-body">
          <x-field-help label="Carga de anexos" class="span-2">
                <x-carga-archivos name="anexos" :multiple="true" accept=".pdf,.docx,.xlsx,.jpg,.png" :maxMb="10" />
              </x-field-help>
        </div>
      </div>

      <div class="wz-foot">
        <button type="button" class="btn btn-outline" onclick="wzNav(-1)">Atrás</button>
        <div class="wz-foot-right">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-outline" onclick="guardar('borrador')">Guardar como borrador</button>
          <button type="button" class="btn btn-success" onclick="guardar('enviar')">Guardar y enviar</button>
        </div>
      </div>
    </div>

  </form>
</div>{{-- /page-default --}}

@push('scripts')
{{-- Datos de PHP que el JS necesita. Se inyectan aquí para mantener --}}
{{-- agenda-create.js como JS puro, sin interpolaciones Blade.        --}}
<script>
  window.PUNTA = {
    apiTramitesBuscar:          "{{ route('api.tramites.buscar') }}",
    apiTramiteDetalle:          "{{ url('api/tramites') }}",
    apiHomoclavePrevisualizar:  "{{ url('api/homoclave/previsualizar') }}",
    subsectoresPorSector:       @json($subsectoresPorSector ?? [])
  };
</script>
<script src="{{ asset('js/agenda-create.js') }}?v={{ filemtime(public_path('js/agenda-create.js')) }}"></script>
@endpush
@endsection