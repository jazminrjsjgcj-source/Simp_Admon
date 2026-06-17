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
  .wz-check { display:flex; gap:10px; padding:14px 16px; border:1px solid var(--surface-high); border-radius:var(--radius); cursor:pointer; }
  .wz-check input { margin-top:3px; }
  .wz-check strong { display:block; color:var(--text); font-size:14px; }
  .wz-check small { font-size:12px; color:var(--muted); }
  .wz-tipo { border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:14px; overflow:hidden; }
  .wz-tipo-head { background:var(--primary-fixed); padding:12px 16px; color:var(--primary); font-weight:600; }
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
            {{-- Pregunta diagnóstico: grupos prioritarios --}}
            <x-field-help label="¿Está dirigido a grupos de atención prioritaria?" class="span-2">
              <div class="wz-sino">
                <label><input type="radio" name="tramite_grupo_prioritario" value="1" onclick="toggleDetalle('detPrioritario',true)"> Sí</label>
                <label><input type="radio" name="tramite_grupo_prioritario" value="0" checked onclick="toggleDetalle('detPrioritario',false)"> No</label>
              </div>
              <div class="wz-detalle" id="detPrioritario">
                <input name="tramite_grupo_prioritario_detalle" type="text" placeholder="Especifique el grupo">
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

      {{-- Checkboxes de tipo de mejora --}}
      <div class="wz-checks">
        <label class="wz-check"><input type="checkbox" name="mejora_reducir_requisitos" value="1"><span><strong>Reducir requisitos</strong><small>Eliminar documentos no necesarios.</small></span></label>
        <label class="wz-check"><input type="checkbox" name="mejora_reducir_tiempos" value="1"><span><strong>Reducir tiempos</strong><small>Acortar atención o resolución.</small></span></label>
        <label class="wz-check"><input type="checkbox" name="mejora_pago_linea" value="1"><span><strong>Pago en línea</strong><small>Integrar pago de derechos.</small></span></label>
        <label class="wz-check"><input type="checkbox" name="mejora_expediente_digital" value="1"><span><strong>Expediente digital</strong><small>Subir documentos y consultar estatus.</small></span></label>
      </div>

      {{-- BLOQUE V: Simplificación --}}
      <div class="wz-tipo" id="bloqueSimplificacion">
        <div class="wz-tipo-head">Simplificación</div>
        <div class="wz-tipo-body">
          <div class="wizard-fields">
            <x-field-help label="Objetivo de la simplificación" class="span-2">
                <textarea name="descripcion" rows="3" placeholder="Ej. Reducir requisitos y visitas presenciales..."></textarea>
              </x-field-help>
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
                <select name="tramite_nivel_digitalizacion">
                <option value="1">1 - Sin digitalización</option>
                <option value="2">2 - Información en línea</option>
                <option value="3">3 - Formulario en línea</option>
                <option value="4">4 - Pago en línea</option>
                <option value="5">5 - Expediente digital completo</option>
              </select>
              </x-field-help>
            <x-field-help label="Meta esperada">
                <input name="meta_digital" type="text" placeholder="Ej. Formulario y pago en línea">
              </x-field-help>
            <x-field-help label="Objetivo de la digitalización" class="span-2">
                <textarea name="descripcion_digital" rows="3" placeholder="Explique portal, pagos, firma o expediente digital..."></textarea>
              </x-field-help>
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
                <input type="file" name="anexos[]" multiple accept=".pdf,.docx,.xlsx,.jpg,.png">
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
<script>
(function () {
  var paso = 1;
  var caminoNuevo = false;
  var ULTIMO = 6;

  // ¿El paso forma parte del recorrido actual? El paso 2 (Trámite) solo si es camino nuevo.
  function pasoAplica(n) {
    if (n === 2) return caminoNuevo;
    return n >= 1 && n <= ULTIMO;
  }

  // Oculta del stepper el paso Trámite cuando no aplica.
  function ajustarStepperVisible() {
    document.querySelectorAll('[data-opcional="tramite"]').forEach(function (s) {
      s.style.display = caminoNuevo ? '' : 'none';
    });
  }

  function pintarStepper(n) {
    document.querySelectorAll('[data-step]').forEach(function (s) {
      var d = parseInt(s.dataset.step);
      var completo = d < n && pasoAplica(d);
      s.classList.toggle('active', d === n);
      s.classList.toggle('done', completo);
      s.classList.toggle('completed', completo);
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
    if (dir > 0 && !validar(paso)) return;
    var n = paso + dir;
    // Saltar pasos que no aplican (ej. Trámite cuando es camino existente).
    while (n >= 1 && n <= ULTIMO && !pasoAplica(n)) { n += dir; }
    if (n >= 1 && n <= ULTIMO) mostrar(n);
  };

  // Muestra error inline en el paso activo en lugar de alert()
  function mostrarErrorPaso(msg) {
    var panel = document.querySelector('.wizard-panel.active, .wizard-step-panel.active, [data-paso="' + paso + '"]');
    var existente = document.getElementById('wzErrorMsg');
    if (existente) existente.remove();
    var div = document.createElement('div');
    div.id = 'wzErrorMsg';
    div.style.cssText = 'background:#fef2f2;border:1px solid #fca5a5;border-left:4px solid #ef4444;border-radius:8px;padding:10px 14px;margin-bottom:12px;color:#991b1b;font-size:13px;font-weight:600';
    div.textContent = msg;
    if (panel) panel.prepend(div);
    else document.querySelector('.wizard-body, form').prepend(div);
    setTimeout(function(){ if(div.parentNode) div.remove(); }, 5000);
  }

  // Limpia el error al avanzar exitosamente
  function limpiarErrorPaso() {
    var existente = document.getElementById('wzErrorMsg');
    if (existente) existente.remove();
  }

  // Toggle campo áreas participantes
  function toggleAreasDetalle(val) {
    var wrap = document.getElementById('areasDetalleWrap');
    if (wrap) wrap.style.display = (parseInt(val) > 1) ? '' : 'none';
  }

  function validar(n) {
    limpiarErrorPaso();
    if (n === 1) {
      if (!document.getElementById('modoTramite').value) {
        mostrarErrorPaso('Elija si el trámite ya existe o si se registrará desde cero.');
        return false;
      }
    }
    if (n === 2 && caminoNuevo) {
      var nom = document.querySelector('[name="tramite_nombre_oficial"]');
      var dep = document.querySelector('[name="tramite_dependencia_id"]');
      if (!nom.value.trim()) { mostrarErrorPaso('El nombre del trámite es obligatorio.'); nom.focus(); return false; }
      if (!dep.value) { mostrarErrorPaso('La dependencia del trámite es obligatoria.'); dep.focus(); return false; }
    }
    if (n === 4) {
      var desc = document.querySelector('[name="descripcion"]');
      if (!desc.value || desc.value.trim().length < 10) {
        mostrarErrorPaso('El objetivo de la simplificación es obligatorio (mínimo 10 caracteres).');
        desc.focus(); return false;
      }
    }
    return true;
  }

  // ---- Camino (paso 1) ----
  window.elegirCamino = function (cual) {
    caminoNuevo = (cual === 'nuevo');
    document.getElementById('modoTramite').value = cual;
    document.getElementById('opcExistente').classList.toggle('sel', !caminoNuevo);
    document.getElementById('opcNuevo').classList.toggle('sel', caminoNuevo);
    document.getElementById('bloqueBuscar').style.display = caminoNuevo ? 'none' : '';
    document.getElementById('precargaSub').textContent = caminoNuevo
      ? 'Capture la identificación e información del trámite nuevo.'
      : 'Estos datos se precargan del trámite seleccionado.';
    if (caminoNuevo) limpiarTramite();
    ajustarStepperVisible();
  };

  // ---- Alcance ----
  window.elegirAlcance = function (el) {
    document.querySelectorAll('[data-alcance]').forEach(function (o) { o.classList.remove('sel'); });
    el.classList.add('sel');
    document.getElementById('alcanceCampo').value = el.dataset.alcance;
  };

  // ---- Detalle condicional Sí/No ----
  window.toggleDetalle = function (id, mostrarlo) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('visible', mostrarlo);
  };

  // ---- Búsqueda de trámite (camino A) ----
  var timer = null;
  var buscador = document.getElementById('buscadorTramite');
  if (buscador) {
    buscador.addEventListener('input', function () {
      clearTimeout(timer);
      var q = this.value.trim();
      var cont = document.getElementById('resultadosTramite');
      if (q.length < 2) { cont.innerHTML = ''; return; }
      cont.innerHTML = '<div class="wz-buscando">Buscando...</div>';
      timer = setTimeout(function () { buscar(q); }, 300);
    });
  }
  function buscar(q) {
    fetch('{{ route('api.tramites.buscar') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var cont = document.getElementById('resultadosTramite');
        if (!data.resultados || !data.resultados.length) {
          cont.innerHTML = '<div class="wz-buscando">Sin resultados.</div>';
          return;
        }
        cont.innerHTML = '';
        data.resultados.forEach(function (t) {
          var div = document.createElement('div');
          div.className = 'wz-result';
          div.innerHTML = '<strong>' + t.nombre + '</strong><span>' + (t.homoclave || 'Sin folio') + ' · ' + t.dependencia + '</span>';
          div.onclick = function () { elegirTramite(t); };
          cont.appendChild(div);
        });
      })
      .catch(function () {
        document.getElementById('resultadosTramite').innerHTML = '<div class="wz-buscando">Error al buscar.</div>';
      });
  }
  window.elegirTramite = function (t) {
    document.getElementById('tramiteIdSel').value = t.id;
    document.getElementById('tramiteElegidoNombre').textContent = t.nombre + ' (' + (t.homoclave || 'sin folio') + ')';
    document.getElementById('tramiteElegido').style.display = '';
    document.getElementById('resultadosTramite').innerHTML = '';
    document.getElementById('buscadorTramite').value = '';
    // Traer el detalle completo y precargar en solo-lectura.
    precargarTramite(t.id);
  };

  function precargarTramite(id) {
    var url = '{{ url('api/tramites') }}/' + id + '/detalle';
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('El servidor respondió ' + r.status + ' al pedir ' + url); }
        return r.json();
      })
      .then(function (d) {
        // Mapa: name del campo en el wizard -> valor del trámite.
        var mapa = {
          'tramite_nombre_oficial': d.nombre_oficial,
          'tramite_objetivo': d.objetivo,
          'tramite_servidor_publico': d.servidor_publico,
          'tramite_volumen_anual': d.volumen_anual,
          'tramite_plazo_resolucion_cantidad': d.plazo_resolucion_cantidad,
          'tramite_plazo_resolucion_unidad': d.plazo_resolucion_unidad,
          'tramite_nivel_digitalizacion': d.nivel_digitalizacion,
          'tramite_visitas_requeridas': d.visitas_requeridas,
          'tramite_fundamento': d.normativa_nombre,
          'tramite_dirigido_a': d.dirigido_a,
        };
        Object.keys(mapa).forEach(function (name) {
          var el = document.querySelector('[name="' + name + '"]');
          if (el && mapa[name] != null) {
            el.value = mapa[name];
            el.setAttribute('readonly', 'readonly');
            el.classList.add('u-input-readonly');
            if (el.tagName === 'SELECT') el.setAttribute('disabled', 'disabled');
          }
        });
      })
      .catch(function (err) {
        console.error('Error al precargar trámite:', err);
        mostrarErrorPaso('No se pudo precargar el trámite: ' + err.message);
      });
  }

  // Marca un select mostrando el texto recibido (cuando no tenemos el id).
  window.limpiarTramite = function () {
    document.getElementById('tramiteIdSel').value = '';
    var el = document.getElementById('tramiteElegido');
    if (el) el.style.display = 'none';
    // Revertir solo-lectura de los campos del trámite.
    document.querySelectorAll('[name^="tramite_"]').forEach(function (campo) {
      campo.removeAttribute('readonly');
      campo.removeAttribute('disabled');
      campo.classList.remove('u-input-readonly');
    });
  };

  // ---- Requisitos dinámicos (paso 5) ----
  var reqIdx = 0;
  window.addReq = function () {
    var i = reqIdx++;
    var art = document.createElement('article');
    art.className = 'requirement-card';
    art.style.marginBottom = '8px';
    art.innerHTML =
      '<div class="wizard-fields">' +
        '<div class="field span-2"><label>Nombre del requisito</label><input name="requisitos[' + i + '][nombre]" placeholder="Ej. Identificación oficial"></div>' +
        '<div class="field"><label>¿Original?</label><select name="requisitos[' + i + '][original]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>' +
        '<div class="field"><label>¿Copia?</label><select name="requisitos[' + i + '][copia]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>' +
      '</div>';
    document.getElementById('reqLista').appendChild(art);
  };

  // ---- Guardar ----
  window.guardar = function (modo) {
    document.getElementById('accionCampo').value = modo;
    var alc = document.getElementById('alcanceCampo').value;
    if (!document.querySelector('[name="tipo"]')) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'tipo';
      h.value = (alc === 'digitalizacion') ? 'digitalizacion' : 'simplificacion';
      document.getElementById('wzForm').appendChild(h);
    }
    document.getElementById('wzForm').submit();
  };

  // ---- Pago de derechos: lista dinámica (alimenta el costo burocrático) ----
  var _derechos = [];
  function renderDerechos() {
    var cont = document.getElementById('derechosLista');
    if (!cont) return;
    cont.innerHTML = '';
    if (_derechos.length === 0) {
      cont.innerHTML = '<p class="u-muted" style="font-size:13px">Sin conceptos de derechos. Si el trámite no cobra derechos, déjalo vacío.</p>';
    }
    _derechos.forEach(function (d, i) {
      var fila = document.createElement('div');
      fila.className = 'derecho-fila';
      fila.style.cssText = 'display:flex; gap:8px; margin-bottom:6px;';
      fila.innerHTML =
        '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)" style="flex:2">' +
        '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)" style="flex:1">' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>';
      cont.appendChild(fila);
    });
    sincronizarDerechos();
  }
  function sincronizarDerechos() {
    var total = _derechos.reduce(function (s, d) { return s + (parseFloat(d.monto) || 0); }, 0);
    document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  }
  window.agregarDerecho = function () { _derechos.push({ concepto: '', monto: 0 }); renderDerechos(); };
  window.quitarDerecho = function (i) { _derechos.splice(i, 1); renderDerechos(); };
  window.setDerecho = function (i, campo, valor) {
    if (_derechos[i]) {
      _derechos[i][campo] = campo === 'monto' ? (parseFloat(valor) || 0) : valor;
      sincronizarDerechos();
    }
  };
  renderDerechos();

  // ---- Subsector dependiente del sector ----
  var SUBSECTORES = @json($subsectoresPorSector ?? []);
  window.cargarSubsectores = function () {
    var sectorId = document.getElementById('selSector').value;
    var sel = document.getElementById('selSubsector');
    sel.innerHTML = '';
    var lista = SUBSECTORES[sectorId] || [];
    if (!sectorId || lista.length === 0) {
      sel.innerHTML = '<option value="">' + (sectorId ? 'Sin subsectores para este sector' : 'Primero elija un sector') + '</option>';
      sel.disabled = true;
      return;
    }
    sel.disabled = false;
    sel.innerHTML = '<option value="">Seleccione</option>';
    lista.forEach(function (s) {
      var op = document.createElement('option');
      op.value = s.id; op.textContent = s.nombre;
      sel.appendChild(op);
    });
  };

  // ---- Previsualización de homoclave (se genera de dependencia + unidad) ----
  (function previsualizarHomoclave() {
    var depInput  = document.querySelector('[name="tramite_dependencia_id"]');
    var unidadEl  = document.querySelector('[name="tramite_unidad_id"]');
    var homoclave = document.getElementById('homoclaveAgenda');
    if (!depInput || !unidadEl || !homoclave) return;
    function actualizar() {
      var depId = depInput.value, uniId = unidadEl.value;
      if (!depId || !uniId) { homoclave.value = ''; return; }
      fetch('{{ url('api/homoclave/previsualizar') }}?dependencia_id=' + depId + '&unidad_id=' + uniId, {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data && data.homoclave) homoclave.value = data.homoclave; })
        .catch(function () { /* si falla, el backend la genera al guardar */ });
    }
    unidadEl.addEventListener('change', actualizar);
    actualizar();
  })();

  ajustarStepperVisible();
  mostrar(1);
})();
</script>
@endpush
@endsection
