@extends('layouts.app')
@section('title', 'Nuevo registro')

@section('content')
<div class="page-body">

  <div class="screen-head">
    <div><h2 class="nowrap" id="wizardTituloPrincipal">Registrar nuevo trámite o servicio</h2><p class="nowrap">Paso <span id="stepLabel">1</span> de 7</p></div>
    <div class="head-actions"><x-btn-ejemplo tipo="tramite" /></div>
  </div>

  <form method="POST" action="{{ route('tramites.store') }}" id="tramiteForm" novalidate>
    @csrf
    <style>select.input-invalid { border-color: #DC2626; background: #FEF2F2; }</style>
    @if(request('retorno') === 'agenda')
      <input type="hidden" name="retorno" value="agenda">
      <div class="assist-box" style="margin:12px 16px;background:#eff6ff;border-left:4px solid #3b82f6;padding:10px 14px">
        <strong>Viniendo de la Agenda SyD.</strong> <span data-nat="Al guardar este trámite volverá automáticamente al wizard de la agenda con el trámite ya seleccionado.|Al guardar este servicio volverá automáticamente al wizard de la agenda con el servicio ya seleccionado.">Al guardar este trámite volverá automáticamente al wizard de la agenda con el trámite ya seleccionado.</span>
      </div>
      <style>
        /* Desde agenda: sin opción de borrador, solo enviar.
           Aquí solo queda el botón del paso 7. */
        #btnBorrador { display: none !important; }
      </style>
    @endif

    <div class="wizard-shell">

      <div class="wizard-stepper" id="tramiteStepper">
        <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Identificación</strong><small>Datos base</small></div>
        <div class="wizard-step"        data-step="2"><span class="wizard-dot"></span><strong>Información</strong><small>Objetivo</small></div>
        <div class="wizard-step"        data-step="3"><span class="wizard-dot"></span><strong>Operación</strong><small>ATDT</small></div>
        <div class="wizard-step"        data-step="4"><span class="wizard-dot"></span><strong>Requisitos</strong><small>Documentos</small></div>
        <div class="wizard-step"        data-step="5"><span class="wizard-dot"></span><strong>Fundamento</strong><small>De dónde sale</small></div>
        <div class="wizard-step"        data-step="6"><span class="wizard-dot"></span><strong>Portal</strong><small>Ciudadanía</small></div>
        <div class="wizard-step"        data-step="7"><span class="wizard-dot"></span><strong>Confirmar</strong><small>Guardar</small></div>
      </div>

      <div class="wizard-panel">

        {{-- PASO 1: Identificación --}}
        <div class="wizard-content active" data-panel="1">
          <div class="wizard-panel-head">
            <span class="wizard-panel-icon"></span>
            <div>
              <h3><span data-nat="Identificación del trámite|Identificación del servicio">Identificación del trámite</span></h3>
              <p><span data-nat="Nombre, unidad responsable y clave del trámite.|Nombre, unidad responsable y clave del servicio.">Nombre, unidad responsable y clave del trámite.</span></p>
            </div>
          </div>

          <div class="wizard-fields">

            {{-- ── Selector de naturaleza: ¿Trámite o Servicio? ──────────── --}}
            {{-- Patrón visual wz-opt del wizard de agenda SyD: dos cards
                 clickeables que destacan con borde guinda al seleccionar.
                 El valor se guarda en un hidden input 'naturaleza'. --}}
            <input type="hidden" name="naturaleza" id="naturalezaHidden" value="{{ old('naturaleza', 'tramite') }}">

            <div class="field span-2">
              <label>¿Qué va a registrar? *</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px">
                <div class="wz-opt {{ old('naturaleza', 'tramite') === 'tramite' ? 'sel' : '' }}"
                     onclick="elegirNaturaleza('tramite')" id="optTramite" style="cursor:pointer">
                  Trámite
                  <small>Solicitud o entrega de información que la persona realiza ante la autoridad.</small>
                </div>
                <div class="wz-opt {{ old('naturaleza') === 'servicio' ? 'sel' : '' }}"
                     onclick="elegirNaturaleza('servicio')" id="optServicio" style="cursor:pointer">
                  Servicio
                  <small>Beneficio, programa o actividad que la autoridad brinda a las personas.</small>
                </div>
              </div>
            </div>

            {{-- Nombre oficial: texto libre con longitud máxima --}}
            <div class="field span-2">
              <label for="nombre_oficial" id="labelNombreOficial"><span data-nat="Nombre oficial del trámite *|Nombre oficial del servicio *">Nombre oficial del trámite *</span></label>
              <input id="nombre_oficial" name="nombre_oficial" type="text" required
                     maxlength="500"
                     placeholder="Ej. Licencia de Funcionamiento Comercial Tipo A"
                     value="{{ old('nombre_oficial') }}">
              <small class="help-small">Nombre completo según la disposición que lo crea.</small>
            </div>

            {{-- Tipo de trámite desde catálogo (visible solo cuando naturaleza=tramite) --}}
            <div class="field" id="campoTipoTramite" style="{{ old('naturaleza') === 'servicio' ? 'display:none' : '' }}">
              <label for="tipo_tramite_id">Tipo de trámite</label>
              <select id="tipo_tramite_id" name="tipo_tramite_id">
                <option value="">Seleccione una opción</option>
                @foreach(\App\Models\TipoTramite::activos()->get() as $tt)
                  <option value="{{ $tt->id }}"
                    {{ old('tipo_tramite_id') == $tt->id ? 'selected' : '' }}
                    title="{{ $tt->descripcion }}">
                    {{ $tt->nombre }}
                  </option>
                @endforeach
              </select>
              <small class="help-small"><span data-nat="Clasifica el trámite (Licencia, Permiso, Registro…).|Clasifica el servicio según la LNETB.">Clasifica el trámite (Licencia, Permiso, Registro…).</span></small>
            </div>

            {{-- Tipo de servicio (visible solo cuando naturaleza=servicio) --}}
            <div class="field" id="campoTipoServicio" style="{{ old('naturaleza') !== 'servicio' ? 'display:none' : '' }}">
              <label for="tipo_servicio">Tipo de servicio</label>
              <select id="tipo_servicio" name="tipo_servicio">
                <option value="">Seleccione una opción</option>
                @foreach($tiposServicio as $ts)
                  <option value="{{ $ts }}" {{ old('tipo_servicio') === $ts ? 'selected' : '' }}>
                    {{ $ts }}
                  </option>
                @endforeach
              </select>
              <small class="help-small">Clasifica el servicio según la LNETB.</small>
            </div>

            {{-- Dependencia: se toma del perfil del usuario, no se puede cambiar.
                 $misUnidades y $unidadAutoId vienen del controlador (create()). --}}
            @php $miDependencia = auth()->user()->dependencia; @endphp
            <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">

            <x-field-help label="Dependencia">
              <input type="text" value="{{ $miDependencia?->nombre ?? 'Sin dependencia asignada' }}" disabled class="u-input-disabled">
              <small class="help-small">Asignada desde tu perfil de usuario. Contacta al administrador para cambiarla.</small>
            </x-field-help>

            {{-- Unidad administrativa: si la dependencia tiene una sola, se selecciona sola --}}
            <div class="field">
              <label for="unidad_id">Unidad administrativa</label>
              @if($unidadAutoId)
                <input type="hidden" name="unidad_id" value="{{ $unidadAutoId }}">
                <input type="text" value="{{ $misUnidades->first()->nombre }}" disabled class="u-input-readonly">
                <small class="help-small">Seleccionada automáticamente.</small>
              @elseif($misUnidades->count() > 1)
                <select id="unidad_id" name="unidad_id">
                  <option value="">Seleccione una opción</option>
                  @foreach($misUnidades as $uni)
                    <option value="{{ $uni->id }}" {{ old('unidad_id') == $uni->id ? 'selected' : '' }}>
                      {{ $uni->codigo }} — {{ $uni->nombre }}
                    </option>
                  @endforeach
                </select>
                @error('unidad_id')<span class="field-error">{{ $message }}</span>@enderror
              @else
                <input type="text" value="Sin unidades activas para esta dependencia" disabled style="background:#f3f4f6;color:#9ca3af">
              @endif
            </div>

            {{-- La homoclave se genera de dependencia + unidad administrativa.
                 La previsualización en vivo está en el script al final. --}}

            {{-- Sujeto Obligado: titular de la dependencia del usuario (auto, solo lectura) --}}
            @php
              $sujetoObligado = \App\Models\SujetoObligado::vigenteDe(auth()->user()->dependencia_id);
            @endphp
            <x-field-help label="Sujeto Obligado">
              <input type="text"
                value="{{ $sujetoObligado?->nombre ?? 'Sin titular registrado' }}"
                disabled class="u-input-disabled">
              <input type="hidden" name="sujeto_obligado_id" value="{{ $sujetoObligado?->id }}">
              {{-- servidor_publico conserva el nombre del titular para el guardado existente --}}
              <input type="hidden" name="servidor_publico" value="{{ $sujetoObligado?->nombre }}">
              @if($sujetoObligado?->cargo)
                <small class="help-small">{{ $sujetoObligado->cargo }}</small>
              @elseif(!$sujetoObligado)
                <small class="help-small">Tu dependencia no tiene titular registrado. Pídele al administrador que lo agregue en Catálogos → Sujetos obligados.</small>
              @endif
            </x-field-help>

            {{-- Enlace: la persona que captura (usuario logueado, auto, solo lectura) --}}
            <x-field-help label="Enlace">
              <input type="text" value="{{ auth()->user()->name }}" disabled class="u-input-disabled">
              <input type="hidden" name="enlace_id" value="{{ auth()->id() }}">
              <small class="help-small"><span data-nat="Persona que registra el trámite.|Persona que registra el servicio.">Persona que registra el trámite.</span></small>
            </x-field-help>

            {{-- Homoclave: se genera automáticamente al elegir la unidad administrativa --}}
            <div class="field">
              <label for="homoclave_input">Homoclave</label>
              <input id="homoclave_input" name="homoclave" type="text"
                     maxlength="50"
                     placeholder="Se generará al elegir la unidad administrativa"
                     value="{{ old('homoclave') }}"
                     readonly
                     class="u-input-readonly">
              <small class="help-small">Se genera automáticamente: {{ config('punta.prefijo_homoclave', 'LPZ') }}-(siglas dependencia)-(siglas unidad)-(consecutivo).</small>
            </div>

          </div>
        </div>

        {{-- PASO 2: Información general --}}
        <div class="wizard-content" data-panel="2">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Información general</h3><p>Objetivo, población, sector económico y plazos.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="Objetivo del trámite" data-nat-label="Objetivo del trámite|Objetivo del servicio" :required="true" class="span-2">
              <textarea required name="objetivo" rows="4" placeholder="Describa qué resuelve o qué beneficio otorga...">{{ old('objetivo') }}</textarea>
            </x-field-help>
            <div class="field">
              <x-field-help label="¿A quién va dirigido el trámite?" data-nat-label="¿A quién va dirigido el trámite?|¿A quién va dirigido el servicio?">
                <select name="dirigido_a" onchange="toggleEtapaOperacion()">
                  <option value="ambas"  {{ old('dirigido_a','ambas')==='ambas'?'selected':'' }}>Personas físicas y morales</option>
                  <option value="fisica" {{ old('dirigido_a')==='fisica'?'selected':'' }}>Solo personas físicas</option>
                  <option value="moral"  {{ old('dirigido_a')==='moral'?'selected':'' }}>Solo personas morales</option>
                </select>
              </x-field-help>
              {{-- Etapa de operación: vive junto a "¿a quién va dirigido?" porque
                   depende lógicamente de él. Aparece solo cuando el trámite va
                   dirigido a personas morales o a ambas (se controla por JS). --}}
              @php $dirigidoActual = old('dirigido_a', 'ambas'); @endphp
              <div id="etapaOperacionWrap" style="display:{{ in_array($dirigidoActual, ['moral','ambas']) ? '' : 'none' }}; margin-top:12px">
                <x-field-help label="Etapa de operación de la persona moral">
                  <select name="etapa_operacion">
                    <option value="">No aplica / sin especificar</option>
                    <option value="APERTURA"  {{ old('etapa_operacion')==='APERTURA'  ? 'selected' : '' }}>Apertura — la empresa va a iniciar operaciones</option>
                    <option value="OPERACIÓN" {{ old('etapa_operacion')==='OPERACIÓN' ? 'selected' : '' }}>Operación — la empresa ya está operando</option>
                    <option value="CIERRE"    {{ old('etapa_operacion')==='CIERRE'    ? 'selected' : '' }}>Cierre — la empresa va a cerrar operaciones</option>
                  </select>
                </x-field-help>
              </div>
            </div>
            {{-- Frecuencia + Volumen apilados en una sola celda (columna derecha),
                 mismo patrón que "¿A quién va dirigido?" + "Etapa de operación".
                 Así Sector y Subsector quedan como pareja en su propia fila. --}}
            <div class="field">
              <x-field-help label="Frecuencia">
                <select name="frecuencia">
                  <option value="" {{ old('frecuencia') ? '' : 'selected' }}>Seleccione una opción</option>
                  <option {{ old('frecuencia')==='Alta'?'selected':'' }}>Alta</option>
                  <option {{ old('frecuencia')==='Media'?'selected':'' }}>Media</option>
                  <option {{ old('frecuencia')==='Baja'?'selected':'' }}>Baja</option>
                  <option {{ old('frecuencia')==='Eventual'?'selected':'' }}>Eventual</option>
                </select>
              </x-field-help>

              <div style="margin-top:12px">
                <x-input-validado tipo="numero_entero" name="volumen_anual" label="Volumen anual estimado *" min="0" :max="config('punta.topes_tramite.volumen_anual')" placeholder="Ej. 1250" :value="old('volumen_anual')" help="Máximo permitido: {{ number_format(config('punta.topes_tramite.volumen_anual')) }} trámites al año." />
              </div>
            </div>

            {{-- Sector y subsector económico SCIAN --}}
            <x-selector-scian :sector="old('sector_id')" :subsector="old('subsector_id')" />
          </div>
        </div>

        {{-- PASO 3: Operación ATDT --}}
        <div class="wizard-content" data-panel="3">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Operación y costos burocráticos</h3><p>Capture el esfuerzo que representa para la ciudadanía (metodología ATDT).</p></div></div>

          {{-- Sub-tarjeta 1: Esfuerzo administrativo --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Esfuerzo administrativo</h4>
              <p>Cuántas áreas tocan el expediente, cuántas visitas hace la persona y plazo legal de resolución.</p>
            </div>
            <div class="wizard-fields">
              <x-input-validado tipo="numero_entero" name="num_areas" id="numAreas" label="Número de áreas que participan" min="0" :max="config('punta.topes_tramite.num_areas')" placeholder="Ej. 3" :value="old('num_areas')" help="Máximo permitido: {{ config('punta.topes_tramite.num_areas') }} áreas." />
              <div id="areasParticipantesWrap" style="display:none">
                <x-field-help label="Áreas que participan">
                  <input name="areas_participantes" placeholder="Ej. Ventanilla, Tesorería, Protección Civil" value="{{ old('areas_participantes') }}">
                </x-field-help>
              </div>
              <x-input-validado tipo="numero_entero" name="visitas_requeridas" label="Visitas requeridas" min="0" :max="config('punta.topes_tramite.visitas')" placeholder="0" :value="old('visitas_requeridas')" help="Máximo permitido: {{ config('punta.topes_tramite.visitas') }} visitas." />

              {{-- Plazo de resolución --}}
              <div class="field">
                <label>Plazo máximo de resolución</label>
                <div class="split-fields">
                  <input name="plazo_resolucion_cantidad" type="number" min="0" step="1" inputmode="numeric" placeholder="Cantidad" value="{{ old('plazo_resolucion_cantidad') }}">
                  <select name="plazo_resolucion_unidad">
                    <option value="habiles"   {{ old('plazo_resolucion_unidad','habiles')==='habiles'?'selected':'' }}>Días hábiles</option>
                    <option value="naturales" {{ old('plazo_resolucion_unidad')==='naturales'?'selected':'' }}>Días naturales</option>
                    <option value="meses"     {{ old('plazo_resolucion_unidad')==='meses'?'selected':'' }}>Meses</option>
                    <option value="anios"     {{ old('plazo_resolucion_unidad')==='anios'?'selected':'' }}>Años</option>
                  </select>
                </div>
                @php $maxAnios = config('punta.topes_tramite.plazo_anios'); @endphp
                <small class="help-small">Máximo permitido: {{ $maxAnios }} {{ $maxAnios == 1 ? 'año' : 'años' }} ({{ $maxAnios * 12 }} meses o {{ $maxAnios * 365 }} días).</small>
              </div>
            </div>
          </div>

          {{-- Sub-tarjeta 2: Tiempos del ciudadano (CBI) --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Tiempos del ciudadano (CBI)</h4>
              <p>Tiempos invertidos por la persona usuaria. Entran al Costo Burocrático Indirecto.</p>
            </div>
            <div class="wizard-fields">
              <x-field-help label="Tiempo de traslado a la oficina">
                <div class="split-fields">
                  <input name="tiempo_traslado_horas" type="number" min="0" step="1" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_traslado_horas') }}">
                  <input name="tiempo_traslado_min" type="number" min="0" max="59" step="1" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_traslado_min') }}">
                </div>
              </x-field-help>
              <x-field-help label="Tiempo de espera en la oficina">
                <div class="split-fields">
                  <input name="tiempo_espera_horas" type="number" min="0" step="1" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_espera_horas') }}">
                  <input name="tiempo_espera_min" type="number" min="0" max="59" step="1" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_espera_min') }}">
                </div>
              </x-field-help>
              <x-field-help label="Tiempo de atención (duración del trámite)" data-nat-label="Tiempo de atención (duración del trámite)|Tiempo de atención (duración del servicio)">
                <div class="split-fields">
                  <input name="tiempo_atencion_horas" type="number" min="0" step="1" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_atencion_horas') }}">
                  <input name="tiempo_atencion_min" type="number" min="0" max="59" step="1" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_atencion_min') }}">
                </div>
              </x-field-help>
            </div>
          </div>

          {{-- Sub-tarjeta 3: Nivel de digitalización --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Nivel de digitalización</h4>
              <p>Nivel actual del trámite según la escala oficial ATDT (0 a 5). Use la calculadora si no está seguro.</p>
            </div>
            <div class="wizard-fields">
              <x-field-help label="Nivel de digitalización" class="span-2">
                {{-- El select está bloqueado: el nivel solo se fija con la calculadora
                     oficial (los 35 criterios ATDT), no a mano. El input oculto lleva
                     el valor real al servidor, porque un <select disabled> no se
                     envía con el formulario. --}}
                {{-- Select (bloqueado) y botón de la calculadora, lado a lado. --}}
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
                  <select name="nivel_digitalizacion_display" id="nivelDigSelect" disabled style="background:var(--surface-low);cursor:not-allowed; flex:1 1 260px">
                  <option value="" {{ old('nivel_digitalizacion', '') === '' ? 'selected' : '' }}>Sin definir — use la calculadora</option>
                  <option value="0" {{ old('nivel_digitalizacion')==='0' || old('nivel_digitalizacion')===0 ? 'selected' : '' }}>Nivel 0 — Sin digitalización</option>
                  <option value="1" {{ old('nivel_digitalizacion')==1?'selected':'' }}>Nivel 1 — Eficiencia administrativa básica</option>
                  <option value="2" {{ old('nivel_digitalizacion')==2?'selected':'' }}>Nivel 2 — Productividad y reducción de costos</option>
                  <option value="3" {{ old('nivel_digitalizacion')==3?'selected':'' }}>Nivel 3 — Acceso electrónico transaccional</option>
                  <option value="4" {{ old('nivel_digitalizacion')==4?'selected':'' }}>Nivel 4 — Experiencia ciudadana unificada</option>
                  <option value="5" {{ old('nivel_digitalizacion')==5?'selected':'' }}>Nivel 5 — Innovación, transparencia y participación</option>
                  </select>
                  <button type="button" class="btn btn-outline btn-sm" onclick="abrirCalcDig()" style="white-space:nowrap">Calcular nivel con el cuestionario oficial</button>
                </div>
                <input type="hidden" name="nivel_digitalizacion" id="nivelDigHidden" value="{{ old('nivel_digitalizacion') }}">
                <small class="campo-nota">Use la calculadora oficial para establecer el nivel. No se puede editar a mano.</small>
              </x-field-help>
            </div>
          </div>

          {{-- Sub-tarjeta 4: Costos del trámite (CBD) --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4><span data-nat="Costos del trámite (CBD)|Costos del servicio (CBD)">Costos del trámite (CBD)</span></h4>
              <p>Costo público, derechos, copias y fundamento jurídico del cobro. Entran al Costo Burocrático Directo.</p>
            </div>
            {{-- Fórmula ATDT disponible bajo demanda (ayuda), en vez de un recuadro fijo. --}}
            <div class="field" style="margin-bottom:8px">
              <label style="display:inline-flex;align-items:center;gap:6px">
                Fórmula ATDT
                <button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda: fórmula ATDT">?</button>
              </label>
              <div class="field-help-box">CBD = Derechos + Copias + Requisitos con costo · CBI = Tiempo requisitos + Tiempo resolución · CBU = CBD + CBI · CBT = CBU × Volumen anual.</div>
            </div>
            <div class="wizard-fields">
              <x-field-help label="Costo público">
                <div class="costo-grupo">
                  <select id="costoTipo" onchange="actualizarCosto()">
                    <option value="" {{ old('costo_tipo') ? '' : 'selected' }}>Seleccione una opción</option>
                    <option value="gratuito" {{ old('costo_tipo') === 'gratuito' ? 'selected' : '' }}>Gratuito</option>
                    <option value="con_costo" {{ old('costo_tipo') === 'con_costo' ? 'selected' : '' }}>Con precio</option>
                    <option value="con_costo_variable" {{ old('costo_tipo') === 'con_costo_variable' ? 'selected' : '' }}>Con precio variable</option>
                  </select>
                  <input type="number" id="costoMonto" min="0" step="0.01"
                    placeholder="0.00" value="{{ old('costo_monto', 0) }}"
                    oninput="actualizarCosto()" style="display:none">
                  <select id="costoUnidad" onchange="actualizarCosto()" style="display:none">
                    <option value="pesos" {{ old('costo_unidad') === 'UMA' ? '' : 'selected' }}>Pesos</option>
                    <option value="UMA" {{ old('costo_unidad') === 'UMA' ? 'selected' : '' }}>UMA</option>
                  </select>
                  <span class="costo-moneda" id="costoEquiv"></span>
                </div>
                {{-- Lo que se guarda: texto legible + monto YA en pesos + unidad original --}}
                <input type="hidden" name="portal_costo_publico" id="costoTexto" value="{{ old('portal_costo_publico') }}">
                <input type="hidden" name="costo_tipo" id="costoTipoHidden" value="{{ old('costo_tipo') }}">
                <input type="hidden" name="costo_monto" id="costoMontoHidden" value="{{ old('costo_monto', 0) }}">
                <input type="hidden" name="costo_unidad" id="costoUnidadHidden" value="{{ old('costo_unidad', 'pesos') }}">
              </x-field-help>
              <div class="field span-2 fj-bloque">
                <label class="fj-pregunta">¿El costo del trámite tiene fundamento jurídico? *</label>
                <div class="fj-radios">
                  <label><input type="radio" name="costo_fj_tiene" value="1" required onchange="toggleFjRadio(this)" {{ old('costo_fj_tiene') === '1' ? 'checked' : '' }}> Sí</label>
                  <label><input type="radio" name="costo_fj_tiene" value="0" required onchange="toggleFjRadio(this)" {{ old('costo_fj_tiene') === '0' ? 'checked' : '' }}> No</label>
                </div>
                <div class="fj-campos fj-linea" style="display:{{ old('costo_fj_tiene') === '1' ? '' : 'none' }}">
                  <div>
                    @php $hLey = config('helpTexts')['Ley o reglamento'] ?? null; @endphp
                    <label>Ley o reglamento @if($hLey)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Ley o reglamento">?</button>@endif</label>
                    @if($hLey)<div class="field-help-box">{{ $hLey }}</div>@endif
                    <input name="costo_fj_norma" placeholder="Ej. Ley de Hacienda Municipal" value="{{ old('costo_fj_norma') }}">
                  </div>
                  <div>
                    @php $hCap = config('helpTexts')['Capítulo'] ?? null; @endphp
                    <label>Capítulo @if($hCap)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Capítulo">?</button>@endif</label>
                    @if($hCap)<div class="field-help-box">{{ $hCap }}</div>@endif
                    <input name="costo_fj_capitulo" placeholder="Ej. Cap. II" value="{{ old('costo_fj_capitulo') }}">
                  </div>
                  <div>
                    @php $hArt = config('helpTexts')['Artículo'] ?? null; @endphp
                    <label>Artículo @if($hArt)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Artículo">?</button>@endif</label>
                    @if($hArt)<div class="field-help-box">{{ $hArt }}</div>@endif
                    <input name="costo_fj_articulo" placeholder="Ej. Art. 45" value="{{ old('costo_fj_articulo') }}">
                  </div>
                </div>
              </div>
              {{-- Pago de derechos: conceptos de cobro ligados al trámite,
                   independientes del costo público. Un trámite puede ser
                   gratuito y aun así tener derechos por pagar. --}}
              <x-field-help label="Pago de derechos" class="span-2">
                <div id="derechosLista" class="derechos-lista">
                  {{-- filas generadas por JS --}}
                </div>
                <div class="derechos-pie">
                  <button type="button" class="btn btn-outline btn-sm" onclick="agregarDerecho()">+ Agregar derecho</button>
                  <span class="derechos-total">Total derechos: <strong id="derechosTotal">$0.00 MXN</strong></span>
                </div>
                <input type="hidden" name="derechos_json" id="derechosJson" value="{{ old('derechos_json', '[]') }}">
              </x-field-help>
              {{-- Monto de derechos. Se oculta cuando algún derecho es "variable",
                   porque mostrar $0.00 confundiría al usuario. En su lugar aparece
                   el aviso #montoDerechosVariableAviso. --}}
              <div id="montoDerechosFijoWrap" class="span-2" style="display:{{ old('monto_derechos_variable') ? 'none' : '' }}">
                <x-field-help label="Monto de derechos (pesos)" class="span-2">
                  <input type="number" id="montoDerechosCalc" name="monto_derechos_display" readonly
                    value="{{ old('monto_derechos', 0) }}"
                    style="background:var(--surface-low);cursor:not-allowed">
                  <small class="campo-nota">Resultado del total de "Pago de derechos" (convertido a pesos).</small>
                </x-field-help>
              </div>
              {{-- Aviso cuando "es variable": explica al enlace por qué no se calcula. --}}
              <div id="montoDerechosVariableAviso" class="assist-box span-2" style="display:{{ old('monto_derechos_variable') ? '' : 'none' }}">
                <strong>Costo variable.</strong> El monto de derechos no se incluye en el cálculo del CBD porque depende del caso (ej. el predial varía según el valor catastral). El costo total del trámite seguirá considerando el tiempo del ciudadano (CBI) y las copias.
              </div>
              {{-- Pago de derechos variable. Se detecta solo cuando algún derecho se
                   marca como "Variable" (un checkbox por derecho); no es un checkbox
                   global, se calcula a partir de los derechos. --}}
              <input type="hidden" name="monto_derechos_variable" id="montoVariableChk" value="{{ old('monto_derechos_variable', 0) }}">
              <div id="montoReferenciaWrap" style="display:none">
                <x-field-help label="Base de cálculo del monto (referencia)" class="span-2">
                  <input name="monto_derechos_referencia" placeholder="Ej. tarifa mínima de la tabla municipal" value="{{ old('monto_derechos_referencia') }}">
                  <small class="campo-nota">El monto capturado se usa como estimación; esta nota explica de dónde sale.</small>
                </x-field-help>
              </div>
              <x-input-validado tipo="numero_entero" name="copias_cantidad" label="Número de copias requeridas" min="0" :max="config('punta.topes_tramite.copias')" placeholder="0" :value="old('copias_cantidad', 0)" help="Máximo permitido: {{ config('punta.topes_tramite.copias') }} copias." />
              <x-input-validado tipo="numero_decimal" name="copias_precio" label="Precio por copia (pesos)" min="0" step="0.01" placeholder="1.50" :value="old('copias_precio', 1.50)" />
            </div>
          </div>

          {{-- Población objetivo del trámite (a quién va dirigido en términos reales). --}}
          <div class="wizard-section">
            <div class="wizard-fields">
              <x-field-help label="Población objetivo" class="span-2">
                <input name="poblacion_objetivo" placeholder="Ej. Comerciantes establecidos del municipio" value="{{ old('poblacion_objetivo') }}">
              </x-field-help>
            </div>
          </div>

          {{-- Sub-tarjeta 5: Grupos de atención prioritaria (Art. 19 LNETB) --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Grupos de atención prioritaria</h4>
              <p>Art. 19 LNETB. La agenda los lee para priorizar simplificaciones y digitalizaciones.</p>
            </div>
            <div class="wizard-fields">
              @include('partials.catalogos-tramite', [
                'gruposSel' => old('grupos_atencion', []),
              ])
            </div>
          </div>

          {{-- Sub-tarjeta 6: Pasos para realizar el trámite --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4><span data-nat="Pasos para realizar el trámite|Pasos para realizar el servicio">Pasos para realizar el trámite</span></h4>
              <p>Enumere el proceso paso a paso (1, 2, 3) con subpasos (1.1, 1.2). Cada paso indica quién lo realiza y en qué consiste. Se hereda a la agenda en solo lectura.</p>
            </div>
            <div id="pasosLista" class="pasos-lista">
              {{-- filas generadas por JS --}}
            </div>
            <div class="section-actions section-actions-start mt-3">
              <button type="button" class="btn btn-outline btn-sm" onclick="agregarPaso(false)">+ Agregar paso</button>
              <button type="button" class="btn btn-outline btn-sm" id="btnAgregarSubpaso" onclick="agregarPaso(true)">+ Agregar subpaso</button>
            </div>
            <input type="hidden" name="pasos_json" id="pasosJson" value="{{ old('pasos_json', '[]') }}">
          </div>
        </div>

        {{-- PASO 4: Requisitos --}}
        <div class="wizard-content" data-panel="4">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Requisitos</h3><p>Registre cada documento necesario de forma clara.</p></div></div>

          {{-- Art. 29, fracción VI de los Lineamientos LNETB: relación entre
               trámites por naturaleza, secuencia o dependencia funcional. --}}
          <div class="wizard-fields" style="margin-bottom:1.2rem">
            <x-field-help label="¿Guarda relación con otros trámites? (Art. 29-VI LNETB)" class="span-2">
              @php $rel = old('tipo_relacion', 'Ninguna'); @endphp
              <select name="tipo_relacion" onchange="toggleRelacionados()">
                <option value="Ninguna" {{ $rel === 'Ninguna' ? 'selected' : '' }}>Ninguna</option>
                <option value="Naturaleza" {{ $rel === 'Naturaleza' ? 'selected' : '' }}>Naturaleza — comparten el mismo tema o materia</option>
                <option value="Secuencia" {{ $rel === 'Secuencia' ? 'selected' : '' }}>Secuencia — uno debe completarse antes que otro</option>
                <option value="Dependencia funcional" {{ $rel === 'Dependencia funcional' ? 'selected' : '' }}>Dependencia funcional — el resultado de uno es requisito del otro</option>
              </select>
            </x-field-help>
            <div id="relacionadosDetalleWrap" style="display:{{ ($rel !== 'Ninguna' && $rel !== '') ? '' : 'none' }}">
              <x-citar-tramite :relacionados="[]" :tramite_actual_id="null" />
            </div>
          </div>

          <div id="reqContainer">
            <article class="requirement-card">
              <strong>Requisito 1</strong>
              <div class="wizard-fields">
                <x-field-help label="Nombre del requisito" class="span-2">
                  <input name="requisitos[0][nombre]" placeholder="Ej. Identificación oficial vigente">
                </x-field-help>
                {{-- Tipo de presentación: se pueden marcar varias (original, copia, digital) --}}
                <x-field-help label="Tipo de presentación">
                  <div class="tipo-pres-checks">
                    <label><input type="checkbox" name="requisitos[0][tipo][]" value="original"> Original</label>
                    <label><input type="checkbox" name="requisitos[0][tipo][]" value="copia"> Copia</label>
                    <label><input type="checkbox" name="requisitos[0][tipo][]" value="digital"> Digital</label>
                  </div>
                </x-field-help>
                <x-field-help label="Tiempo de obtención">
                  <div class="split-fields split-fields-labeled">
                    <div><label class="split-label">Días háb.</label><input name="requisitos[0][dias]" type="number" min="0" max="365" value="0"></div>
                    <div><label class="split-label">Horas</label><input name="requisitos[0][horas]" type="number" min="0" max="7" value="0"></div>
                    <div><label class="split-label">Minutos</label><input name="requisitos[0][minutos]" type="number" min="0" max="59" value="0"></div>
                  </div>
                </x-field-help>
                <x-field-help label="Observaciones para publicación" class="span-2">
                  <textarea name="requisitos[0][observaciones]" rows="2" placeholder="Vigencia, formato, dependencia emisora..."></textarea>
                </x-field-help>
                {{-- Costo del requisito: sin costo, monto fijo o variable --}}
                <x-field-help label="¿Este requisito tiene costo?">
                  <select name="requisitos[0][costo_modo]" onchange="toggleCostoReq(this)">
                    <option value="sin">Sin costo</option>
                    <option value="fijo">Sí, monto fijo</option>
                    <option value="variable">Sí, costo variable (no cuantificable)</option>
                  </select>
                </x-field-help>
                <div class="req-costo-monto" style="display:none">
                  <x-field-help label="Monto del requisito">
                    <input name="requisitos[0][costo_monto]" type="number" min="0" step="0.01" value="0" placeholder="Ej. 250.00">
                  </x-field-help>
                </div>
                <div class="req-costo-monto" style="display:none">
                  <x-field-help label="Unidad del costo">
                    <select name="requisitos[0][costo_unidad]">
                      <option value="PESOS" {{ old('requisitos.0.costo_unidad', 'PESOS') === 'PESOS' ? 'selected' : '' }}>Pesos</option>
                      <option value="UMA"   {{ old('requisitos.0.costo_unidad') === 'UMA' ? 'selected' : '' }}>UMA</option>
                    </select>
                  </x-field-help>
                </div>
                <div class="field span-2 fj-bloque">
                  <label class="fj-pregunta">¿Este requisito tiene fundamento jurídico? *</label>
                  <div class="fj-radios">
                    <label><input type="radio" name="requisitos[0][fj_tiene]" value="1" required onchange="toggleFjRadio(this)"> Sí</label>
                    <label><input type="radio" name="requisitos[0][fj_tiene]" value="0" required onchange="toggleFjRadio(this)"> No</label>
                  </div>
                  <div class="fj-campos fj-linea" style="display:none">
                    <div><label>Ley o reglamento</label><input name="requisitos[0][fj_norma]" placeholder="Ej. Reglamento de Comercio"></div>
                    <div><label>Capítulo</label><input name="requisitos[0][fj_capitulo]" placeholder="Ej. Cap. III"></div>
                    <div><label>Artículo</label><input name="requisitos[0][fj_articulo]" placeholder="Ej. Art. 12"></div>
                  </div>
                </div>
              </div>
            </article>
          </div>
          <div class="section-actions section-actions-start mt-3">
            <button type="button" class="btn btn-outline btn-sm" onclick="agregarRequisito()">+ Agregar requisito</button>
          </div>
        </div>

        {{-- PASO 5: Fundamento jurídico --}}
        <div class="wizard-content" data-panel="5">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Fundamento jurídico</h3><p>Normativa que da vida al trámite: la que lo crea u obliga. Indique <strong>una sola de origen</strong>, del catálogo o escrita a mano.</p></div></div>
          <div class="wizard-fields">

            {{-- Modo del fundamento de origen: 'catalogo' (vinculado) o 'manual'.
                 El check alterna entre los dos y el servidor respeta uno u otro. --}}
            <input type="hidden" name="fundamento_modo" id="fundamentoModo" value="{{ old('fundamento_modo', 'catalogo') }}">

            <label class="check-inline span-2" style="display:flex;align-items:center;gap:8px;font-size:14px">
              <input type="checkbox" id="fundamentoManualChk" onchange="toggleFundamentoManual()"
                     {{ old('fundamento_modo') === 'manual' ? 'checked' : '' }}>
              Esta normativa <strong>no está en el catálogo</strong> (llenado manual)
            </label>

            {{-- MODO CATÁLOGO: buscar y vincular la regulación de origen. --}}
            <div id="fundamentoCatalogo" class="span-2">
              <x-citar-regulacion :selected="old('regulacion_id')" label="Normativa de origen (del catálogo)" />
            </div>

            {{-- MODO MANUAL: se escribe a mano; se descarta la vinculación. --}}
            <div id="fundamentoManual" class="span-2 hidden">
              <x-field-help label="Normativa de origen" class="span-2">
                <input name="fundamento_normativa" placeholder="Ej. Reglamento de Comercio del Municipio de La Paz" value="{{ old('fundamento_normativa') }}">
                <small class="help-small">Si el trámite se basa en más de una normativa fuera del catálogo, puede escribir una o más aquí.</small>
              </x-field-help>
              <x-field-help label="Tipo de norma">
                <select name="fundamento_tipo">
                  <option value="">Seleccione una opción</option>
                  <option {{ old('fundamento_tipo')==='Reglamento'?'selected':'' }}>Reglamento</option>
                  <option {{ old('fundamento_tipo')==='Lineamiento'?'selected':'' }}>Lineamiento</option>
                  <option {{ old('fundamento_tipo')==='Manual'?'selected':'' }}>Manual</option>
                  <option {{ old('fundamento_tipo')==='Acuerdo'?'selected':'' }}>Acuerdo</option>
                  <option {{ old('fundamento_tipo')==='Ley'?'selected':'' }}>Ley</option>
                </select>
              </x-field-help>
              <x-field-help label="Artículo y fracción">
                <input name="fundamento_articulo" placeholder="Ej. Artículo 45, Fracción II" value="{{ old('fundamento_articulo') }}">
              </x-field-help>
              <x-field-help label="Resumen ciudadano del fundamento" class="span-2">
                <textarea name="fundamento_resumen" rows="3" placeholder="Explique de forma simple por qué existe este trámite...">{{ old('fundamento_resumen') }}</textarea>
              </x-field-help>
            </div>
          </div>
        </div>

        {{-- PASO 6: Portal ciudadano --}}
        <div class="wizard-content" data-panel="6">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Ficha para portal ciudadano</h3><p>Información visible para la ciudadanía.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="Nombre del documento resultado">
              <input name="portal_nombre_ciudadano" placeholder="Nombre ciudadano del trámite" value="{{ old('portal_nombre_ciudadano') }}">
            </x-field-help>
            <x-field-help label="Resultado que se obtiene">
              <input name="portal_resultado" placeholder="Ej. Licencia de funcionamiento, constancia, permiso" value="{{ old('portal_resultado') }}">
            </x-field-help>
            <x-field-help label="Modalidad de atención">
              <select name="portal_modalidad" id="portalModalidad" onchange="toggleModalidadCampos()">
                <option value="Presencial" {{ old('portal_modalidad')==='Presencial'?'selected':'' }}>Presencial</option>
                <option value="En línea"   {{ old('portal_modalidad')==='En línea'?'selected':'' }}>En línea</option>
                <option value="Mixta"      {{ old('portal_modalidad')==='Mixta'?'selected':'' }}>Mixta</option>
              </select>
            </x-field-help>
            <div id="modalidadDireccion" class="field span-2" style="display:none">
              <x-field-help label="Dirección donde se realiza el trámite" data-nat-label="Dirección donde se realiza el trámite|Dirección donde se realiza el servicio">
                <input name="portal_direccion" placeholder="Calle, número, colonia, La Paz, B.C.S." value="{{ old('portal_direccion') }}">
              </x-field-help>
            </div>
            <div id="modalidadUrl" class="field span-2" style="display:none">
              <x-field-help label="URL donde se realiza el trámite en línea" data-nat-label="URL donde se realiza el trámite en línea|URL donde se realiza el servicio en línea">
                <input name="portal_url" type="url" placeholder="https://tramites.lapaz.gob.mx/..." value="{{ old('portal_url') }}">
              </x-field-help>
            </div>
            <x-field-help label="Objetivo del trámite" data-nat-label="Objetivo del trámite|Objetivo del servicio" class="span-2">
              <textarea name="portal_descripcion" rows="3" placeholder="Descripción accesible para la ciudadanía...">{{ old('portal_descripcion') }}</textarea>
            </x-field-help>

            {{-- Costo y derechos: solo lectura. Se capturan en el paso 3
                 (Operación) y aquí se muestran pre-llenados para la ficha. --}}
            <x-field-help label="Costo público (capturado en Operación)" class="span-2">
              <input type="text" id="costoPublicoResumen" readonly
                value="{{ old('costo_tipo') === 'con_costo' ? ('Con costo: $' . old('costo_monto', 0) . ' MXN') : 'Gratuito' }}"
                style="background:var(--surface-low);cursor:not-allowed">
              <small class="campo-nota">Para cambiarlo, regrese al paso de Operación.</small>
            </x-field-help>
            <x-field-help label="Pago de derechos (capturado en Operación)" class="span-2">
              <div id="derechosResumen" class="derechos-lista" style="background:var(--surface-low);border-radius:var(--radius-sm);padding:8px;min-height:34px">
                {{-- filas de resumen generadas por JS, solo lectura --}}
              </div>
            </x-field-help>

            {{-- Horarios de atención estructurados --}}
            <x-field-help label="Horarios de atención">
              <div style="display:flex;gap:8px;align-items:center">
                <input id="horarioResumen" name="portal_horario" readonly
                  placeholder="Haga clic en 'Configurar' para establecer horarios"
                  value="{{ old('portal_horario') }}"
                  style="background:#f9fafb;flex:1;cursor:pointer"
                  onclick="abrirHorarios()">
                <input type="hidden" id="horariosJson" name="horarios_json" value="{{ old('horarios_json') }}">
                <button type="button" class="btn btn-outline btn-sm" onclick="abrirHorarios()" style="white-space:nowrap">Configurar</button>
              </div>
            </x-field-help>
            <x-field-help label="Homoclave pública">
              <input name="portal_homoclave_publica" id="portalHomoclavePublica" readonly
                     placeholder="Se toma de la homoclave generada"
                     value="{{ old('portal_homoclave_publica') }}"
                     style="background:var(--surface-low);cursor:not-allowed">
            </x-field-help>
            <x-field-help label="Documento que obtiene">
              <input name="portal_documento_obtiene" placeholder="Ej. Licencia, constancia, permiso" value="{{ old('portal_documento_obtiene') }}">
            </x-field-help>
            <x-field-help label="Canal principal de atención">
              <select name="portal_canal_principal">
                <option value="">Seleccione una opción</option>
                @foreach(['Presencial', 'En línea', 'Telefónico', 'Mixto'] as $op)
                  <option value="{{ $op }}" {{ old('portal_canal_principal') === $op ? 'selected' : '' }}>{{ $op }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="Medio de entrega">
              <select name="portal_medio_entrega">
                <option value="">Seleccione una opción</option>
                @foreach(['Presencial', 'Correo electrónico', 'Mensajería', 'En línea'] as $op)
                  <option value="{{ $op }}" {{ old('portal_medio_entrega') === $op ? 'selected' : '' }}>{{ $op }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="Forma de pago">
              <select name="portal_forma_pago">
                <option value="">Seleccione una opción</option>
                @foreach(['No aplica', 'Efectivo', 'Tarjeta', 'Transferencia', 'Línea de captura'] as $op)
                  <option value="{{ $op }}" {{ old('portal_forma_pago') === $op ? 'selected' : '' }}>{{ $op }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="Vigencia del resultado">
              <input name="portal_vigencia" placeholder="Ej. 1 año, indefinida" value="{{ old('portal_vigencia') }}">
            </x-field-help>
            <x-field-help label="Oficina de atención">
              <input name="portal_oficina" placeholder="Oficina o ventanilla" value="{{ old('portal_oficina') }}">
            </x-field-help>
            <x-field-help label="Casos en que se realiza" class="span-2">
              <textarea name="portal_casos_realizarse" rows="2" placeholder="¿En qué situaciones se realiza este trámite?">{{ old('portal_casos_realizarse') }}</textarea>
            </x-field-help>
            <x-field-help label="Teléfono">
              <input name="portal_telefono" placeholder="(612) 123-4567" value="{{ old('portal_telefono') }}">
            </x-field-help>
            <x-field-help label="Correo">
              <input name="portal_correo" type="email" placeholder="tramites@lapaz.gob.mx" value="{{ old('portal_correo') }}">
            </x-field-help>
          </div>
        </div>

        {{-- PASO 7: Confirmación --}}
        <div class="wizard-content" data-panel="7">
          <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:32px 16px">
            <div style="width:56px;height:56px;border-radius:999px;background:#16a34a;display:grid;place-items:center;margin:0 0 16px;font-size:22px;font-weight:800;color:white">✓</div>
            <h3 style="margin:0 0 8px">Confirmar trámite</h3>
            <p style="margin:0 0 8px;color:#667085">Revise la información antes de guardar el registro.</p>
            <p style="margin:0 0 20px">
              <button type="button" class="btn btn-outline btn-sm" onclick="vistaPreviaAcuse()">Vista previa / Imprimir</button>
            </p>
            <div class="assist-box" style="max-width:600px;width:100%;text-align:left">
              <strong>Guardar como borrador:</strong> guarda el avance sin enviarlo a revisión. Podrá editarlo y enviarlo después.<br>
              <strong>Guardar y enviar:</strong> <span data-nat="guarda y envía a revisión. El trámite será visible|guarda y envía a revisión. El servicio será visible">guarda y envía a revisión. El trámite será visible</span> para Jurídico, Sujeto Obligado y Revisora.
            </div>
          </div>
        </div>

        {{-- NAVEGACIÓN --}}
        <div class="wizard-foot card-actions">
          <button type="button" class="btn btn-outline hidden" id="btnAtras" onclick="wizardNav(-1)">Atrás</button>
          <div class="form-actions form-actions-end">
            <a href="{{ route('tramites.index') }}" class="btn btn-outline">Cancelar</a>
            <button type="button" class="btn" id="btnSig" onclick="wizardNav(1)">Siguiente</button>
            <button type="submit" class="btn btn-outline hidden" id="btnBorrador" name="accion" value="borrador">Guardar como borrador</button>
            <button type="submit" class="btn btn-success hidden" id="btnGuardar" name="accion" value="enviar">Guardar y enviar</button>
          </div>
        </div>

      </div>
    </div>
  </form>

  @include('partials.calculadora-digitalizacion')

</div>
{{-- Modal de horarios de atención (flujo de 3 pasos) --}}
  <div id="modalHorarios">
    <div class="horario-modal-inner">
      <h3 style="margin:0 0 4px">Horarios de atención</h3>
      <p style="margin:0 0 18px;font-size:13px;color:#6b7280">Configure en tres pasos los días y horarios <span data-nat="en que se atiende el trámite|en que se atiende el servicio">en que se atiende el trámite</span>.</p>

      {{-- PASO 1: horario base --}}
      <div class="horario-paso">
        <div class="horario-paso-num">1</div>
        <div class="horario-paso-cuerpo">
          <h4 class="horario-paso-titulo">Elija el horario base</h4>
          <p class="horario-paso-ayuda">Este horario se aplica a todos los días que marque abajo. Después puede ajustar un día concreto si tiene horario diferente.</p>
          <div class="horario-base-inputs">
            <label>Apertura <input type="time" id="horarioBaseInicio" value="09:00" onchange="setHorarioBase('inicio', this.value)"></label>
            <label>Cierre <input type="time" id="horarioBaseFin" value="15:00" onchange="setHorarioBase('fin', this.value)"></label>
          </div>
        </div>
      </div>

      {{-- PASO 2: días aplicables --}}
      <div class="horario-paso">
        <div class="horario-paso-num">2</div>
        <div class="horario-paso-cuerpo">
          <h4 class="horario-paso-titulo">Marque los días aplicables</h4>
          <div id="horarioChips" class="horario-chips"><!-- chips generados por JS --></div>
          <div class="horario-accesos">
            <span class="horario-accesos-label">Accesos rápidos:</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('lv')">Lun – Vie</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('ls')">Lun – Sáb</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('todos')">Todos</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="horarioLimpiar()">Limpiar</button>
          </div>
        </div>
      </div>

      {{-- PASO 3: vista previa editable --}}
      <div class="horario-paso">
        <div class="horario-paso-num">3</div>
        <div class="horario-paso-cuerpo">
          <h4 class="horario-paso-titulo">Vista previa</h4>
          <p class="horario-paso-ayuda">Revise los días marcados. Puede editar el horario de un día concreto si difiere del base (por ejemplo, sábado reducido).</p>
          <div id="horarioPreview" class="horario-preview"><!-- generado por JS --></div>
        </div>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px">
        <button type="button" class="btn btn-outline" onclick="cerrarHorarios()">Cancelar</button>
        <button type="button" class="btn btn-success" onclick="guardarHorarios()">Aplicar</button>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
{{-- Datos de PHP que el JS necesita. Se inyectan aquí para mantener --}}
{{-- tramites-create.js como JS puro, sin interpolaciones Blade.      --}}
<script>
  window.PUNTA = {
    valorUma: {{ \App\Models\TramiteDerecho::valorUmaVigente() ?: 0 }},
    topes: @json(config('punta.topes_tramite'))
  };
</script>
<script src="{{ asset('js/tramites-create.js') }}?v={{ filemtime(public_path('js/tramites-create.js')) }}"></script>

{{-- Selector de naturaleza Trámite / Servicio.
     Este script se renderiza SIEMPRE (no depende de errores de validación)
     porque las cards del paso 1 lo llaman desde onclick en la carga inicial. --}}
<script>
/**
 * Alterna la UI del paso 1 entre Trámite y Servicio.
 *
 * Cuando el usuario hace clic en una de las dos cards (optTramite / optServicio):
 *   1. Actualiza el hidden input 'naturaleza' para que el servidor sepa qué tipo de registro es.
 *   2. Alterna la visibilidad del select de tipo: si es trámite, muestra tipo_tramite_id
 *      y oculta tipo_servicio; si es servicio, al revés.
 *   3. Actualiza las labels y placeholders para que digan "trámite" o "servicio" según corresponda.
 *   4. Resalta la card seleccionada con la clase 'sel' (borde guinda, fondo guinda, texto blanco).
 *
 * Las clases .wz-opt y .wz-opt.sel vienen del wizard de agenda SyD (mismo patrón visual).
 */
/**
 * Alterna la UI completa del wizard entre Trámite y Servicio.
 *
 * Además de alternar cards, selects y título (como antes), ahora
 * recorre TODOS los textos visibles del wizard (labels, encabezados,
 * párrafos, ayudas) y reemplaza "trámite"↔"servicio" con sus
 * variantes gramaticales. Esto evita tener que marcar 40+ elementos
 * con IDs o atributos data-* individuales.
 *
 * El reemplazo es bidireccional: si el usuario cambia de Servicio
 * a Trámite, los textos vuelven a su forma original.
 */
function elegirNaturaleza(tipo) {
  var hidden = document.getElementById('naturalezaHidden');
  var optT   = document.getElementById('optTramite');
  var optS   = document.getElementById('optServicio');
  var campoT = document.getElementById('campoTipoTramite');
  var campoS = document.getElementById('campoTipoServicio');
  var titulo = document.getElementById('wizardTituloPrincipal');

  hidden.value = tipo;

  optT.classList.toggle('sel', tipo === 'tramite');
  optS.classList.toggle('sel', tipo === 'servicio');

  campoT.style.display = tipo === 'tramite'  ? '' : 'none';
  campoS.style.display = tipo === 'servicio' ? '' : 'none';

  if (tipo === 'tramite')  document.getElementById('tipo_servicio').value = '';
  if (tipo === 'servicio') document.getElementById('tipo_tramite_id').value = '';

  var etiqueta = tipo === 'servicio' ? 'servicio' : 'trámite';
  if (titulo) titulo.textContent = 'Registrar nuevo ' + etiqueta;

  // Reemplazo declarativo: texto directo en <span data-nat="v1|v2">
  var idx = tipo === 'servicio' ? 1 : 0;
  document.querySelectorAll('[data-nat]').forEach(function (el) {
    var partes = el.getAttribute('data-nat').split('|');
    if (partes.length === 2) el.textContent = partes[idx];
  });

  // Reemplazo declarativo: labels de x-field-help con data-nat-label
  document.querySelectorAll('[data-nat-label]').forEach(function (el) {
    var partes = el.getAttribute('data-nat-label').split('|');
    if (partes.length === 2) {
      var label = el.querySelector('label');
      if (label) {
        // Preserve the help button if it exists
        var btn = label.querySelector('.field-help-btn');
        label.childNodes.forEach(function (n) {
          if (n.nodeType === 3) n.nodeValue = '';
        });
        label.insertBefore(document.createTextNode(partes[idx]), label.firstChild);
      }
    }
  });
}

// Si el formulario se recargó con errores y la naturaleza era 'servicio',
// reemplazar los textos inmediatamente para mantener la coherencia.
(function () {
  var nat = document.getElementById('naturalezaHidden');
  if (nat && nat.value === 'servicio') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { elegirNaturaleza('servicio'); });
    } else {
      elegirNaturaleza('servicio');
    }
  }
})();
</script>

@if($errors->any())
<script>
// Bugs 42/43/44: errores de validación inline en el campo correcto.
// Lee los errores de Laravel, los inyecta junto a cada campo, y navega
// al primer paso que tenga errores (en vez de mostrar todo en el banner del paso 1).
(function () {
  var errores = @json($errors->toArray());

  // Mapa: campo → número de paso en el wizard.
  var pasosDeCampo = {
    nombre_oficial:1, dependencia_id:1, unidad_id:1, sector_id:1, subsector_id:1,
    tipo_tramite_id:1, homoclave:1, servidor_publico:1,
    objetivo:2, dirigido_a:2, frecuencia:2, volumen_anual:2, etapa_operacion:2,
    plazo_resolucion_cantidad:3, plazo_resolucion_unidad:3, num_areas:3,
    areas_participantes:3, visitas_requeridas:3, nivel_digitalizacion:3,
    copias_cantidad:3, copias_precio:3, monto_derechos:3,
    tiempo_traslado_horas:3, tiempo_traslado_min:3,
    tiempo_espera_horas:3, tiempo_espera_min:3,
    tiempo_atencion_horas:3, tiempo_atencion_min:3,
    tipo_relacion:4, fundamento_normativa:5, fundamento_tipo:5,
    fundamento_articulo:5, fundamento_resumen:5,
    portal_nombre_ciudadano:6, portal_modalidad:6, portal_descripcion:6,
    portal_correo:6, portal_telefono:6, portal_direccion:6, portal_url:6
  };

  var primerPaso = 99;

  Object.keys(errores).forEach(function (campo) {
    var msgs = errores[campo];
    // Buscar el input/select/textarea por name
    var el = document.querySelector('[name="' + campo + '"]');
    if (!el) return;

    // Inyectar mensaje de error debajo del campo
    var span = document.createElement('span');
    span.className = 'field-error';
    span.style.cssText = 'display:block;color:#b91c1c;font-size:12px;margin-top:4px';
    span.textContent = msgs[0]; // primer mensaje de ese campo
    el.classList.add('input-invalid');

    // Insertar después del input (o del contenedor .field más cercano)
    var contenedor = el.closest('.field') || el.parentElement;
    if (contenedor) contenedor.appendChild(span);

    // Determinar en qué paso está este campo
    var paso = pasosDeCampo[campo];
    if (!paso) {
      // Buscar el data-panel padre del campo
      var panel = el.closest('[data-panel]');
      if (panel) paso = parseInt(panel.dataset.panel);
    }
    if (paso && paso < primerPaso) primerPaso = paso;
  });

  // Navegar al primer paso con error DESPUÉS de que el wizard se inicialice.
  // setTimeout(0) asegura que corra después de DOMContentLoaded (donde go(1) se ejecuta).
  if (primerPaso < 99) {
    setTimeout(function () {
      document.querySelectorAll('[data-panel]').forEach(function(p) {
        p.classList.toggle('active', parseInt(p.dataset.panel) === primerPaso);
      });
      document.querySelectorAll('[data-step]').forEach(function(s) {
        s.classList.toggle('active', parseInt(s.dataset.step) === primerPaso);
      });
      var label = document.getElementById('stepLabel');
      if (label) label.textContent = primerPaso;
      // Hacer scroll al primer error visible
      var primerError = document.querySelector('.field-error');
      if (primerError) primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 50);
  }
})();
</script>
@endif
@endpush