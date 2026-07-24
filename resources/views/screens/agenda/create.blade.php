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
    <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Inicio</strong><small>Registro</small></div>
    <div class="wizard-step" data-step="2" data-opcional="tramite"><span class="wizard-dot"></span><strong>Registro</strong><small>Datos</small></div>
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
          <h3>¿El trámite o servicio ya está cargado?</h3>
          <p>Elija si se precargará desde el catálogo o si se capturará desde cero.</p>
        </div>
      </div>
      <div class="wz-opts">
        <div class="wz-opt" id="opcExistente" onclick="elegirCamino('existente')">
          Sí, seleccionar trámite o servicio existente
          <small>Lo busco en el catálogo y lo selecciono.</small>
        </div>
        <a href="{{ route('tramites.create', ['retorno' => 'agenda']) }}" class="wz-opt" style="text-decoration:none;color:inherit">
          Registrar trámite o servicio nuevo
          <small>Ir al formulario completo y volver aquí al guardar.</small>
        </a>
      </div>

      {{-- Buscador (camino A) --}}
      <div class="wz-sub" id="bloqueBuscar" style="margin-top:16px; display:none">
        <p class="wz-sub-tit">Precargar trámite o servicio existente</p>
        <x-field-help label="Buscar trámite o servicio por nombre, folio u homoclave" class="span-2">
                <input type="text" id="buscadorTramite" placeholder="Escribe al menos 2 letras..." autocomplete="off">
              </x-field-help>
        <div id="resultadosTramite"></div>
        <div id="tramiteElegido" style="display:none; margin-top:8px" class="assist-box">
          <strong>Seleccionado:</strong> <span id="tramiteElegidoNombre"></span>
          <button type="button" class="btn btn-outline btn-sm" style="margin-left:8px" onclick="limpiarTramite()">Cambiar</button>
        </div>
        <div class="assist-box" style="margin-top:12px">
          <strong>¿No aparece?</strong> Si el trámite o servicio no existe en el catálogo, elija "registrar desde cero".
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

    {{-- ============ PASO 4: Acciones de Mejora (Bloques III, V, VI) ============ --}}
    <div class="wz-card wz-panel" data-panel="4">
      <div class="wz-head">
        <span class="wz-head-ic"></span>
        <div>
          <h3>Acciones de Mejora</h3>
          <p>Operación actual del trámite o servicio y qué se simplificará o digitalizará.</p>
        </div>
      </div>

      {{-- Costo burocrático heredado del trámite (solo lectura, camino A) --}}
      <div id="costoHeredado" style="display:none" class="wz-bloque">
        <div class="wz-bloque-head"><strong>Costo burocrático</strong><small>Calculado a partir de los datos del trámite o servicio (metodología ATDT).</small></div>
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
            Este registro aún no tiene su costo burocrático calculado. Se calculará cuando se complete.
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
            {{-- B24: nivel de digitalización (faltaba). Select simple; la
                 calculadora oficial de 35 criterios queda para el trámite normal. --}}
            <x-field-help label="Nivel de digitalización" class="span-2">
              {{-- Igual que en Trámites: el nivel NO se teclea, se calcula con el
                   cuestionario oficial ATDT. El select queda bloqueado (solo
                   visual) y el valor real viaja en el hidden #nivelDigHidden, que
                   la calculadora (partial calculadora-digitalizacion) actualiza.
                   El hidden lleva el nombre con prefijo que espera el backend de
                   Agenda (tramite_nivel_digitalizacion). --}}
              <select id="nivelDigSelect" class="impacto-accion" disabled
                style="background:var(--surface-low);cursor:not-allowed">
                <option value="0" {{ old('tramite_nivel_digitalizacion')==='0'?'selected':'' }}>Nivel 0 — Sin digitalización</option>
                <option value="1" {{ old('tramite_nivel_digitalizacion','1')=='1'?'selected':'' }}>Nivel 1 — Eficiencia administrativa básica</option>
                <option value="2" {{ old('tramite_nivel_digitalizacion')=='2'?'selected':'' }}>Nivel 2 — Productividad y reducción de costos</option>
                <option value="3" {{ old('tramite_nivel_digitalizacion')=='3'?'selected':'' }}>Nivel 3 — Acceso electrónico transaccional</option>
                <option value="4" {{ old('tramite_nivel_digitalizacion')=='4'?'selected':'' }}>Nivel 4 — Experiencia ciudadana unificada</option>
                <option value="5" {{ old('tramite_nivel_digitalizacion')=='5'?'selected':'' }}>Nivel 5 — Innovación, transparencia y participación</option>
              </select>
              <input type="hidden" name="tramite_nivel_digitalizacion" id="nivelDigHidden" value="{{ old('tramite_nivel_digitalizacion', 1) }}">
              <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px" onclick="abrirCalcDig()">Calcular nivel con el cuestionario oficial</button>
              <small class="campo-nota">Use la calculadora oficial para establecer el nivel. No se puede editar a mano.</small>
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
              <textarea name="descripcion" rows="3" placeholder="Ej. Reducir requisitos y digitalizar la licencia de funcionamiento..."></textarea>
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
          <h3>Requisitos</h3>
          <p>Documentos que el ciudadano debe presentar.</p>
        </div>
      </div>
      <div id="tramiteRequisitos" style="display:none; margin-bottom:16px" class="card card-pad">
        <strong style="display:block; margin-bottom:8px; font-size:13px">Requisitos heredados</strong>
        <p style="margin:0 0 10px; font-size:12px; color:var(--muted)">Estos requisitos vienen del registro vinculado. Se editan desde el trámite o servicio, no aquí.</p>
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
                <input type="text" value="{{ auth()->user()->dependencia->nombre ?? '' }}" readonly class="u-input-readonly">
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

  {{-- Calculadora de nivel de digitalización (mismo partial que Trámites).
       Trae su propio modal y JS; el botón "Calcular nivel" la abre y el
       resultado se escribe en #nivelDigHidden. Va fuera del form. --}}
  @include('partials.calculadora-digitalizacion')
</div>{{-- /page-default --}}

@push('scripts')
{{-- Datos de PHP que el JS necesita. Se inyectan aquí para mantener --}}
{{-- agenda-create.js como JS puro, sin interpolaciones Blade.        --}}
<script>
  window.PUNTA = {
    apiTramitesBuscar:          "{{ route('api.tramites.buscar') }}",
    apiTramiteDetalle:          "{{ url('api/tramites') }}",
    apiHomoclavePrevisualizar:  "{{ url('api/homoclave/previsualizar') }}",
    subsectoresPorSector:       @json($subsectoresPorSector ?? []),
    topes:                      @json(config('punta.topes_tramite')),
    tramiteIdRetorno:           {{ (int) request()->query('tramite_id', 0) }}
  };
</script>
<script src="{{ asset('js/horarios.js') }}?v={{ filemtime(public_path('js/horarios.js')) }}"></script>
<script src="{{ asset('js/agenda-create.js') }}?v={{ filemtime(public_path('js/agenda-create.js')) }}"></script>
@endpush
@endsection