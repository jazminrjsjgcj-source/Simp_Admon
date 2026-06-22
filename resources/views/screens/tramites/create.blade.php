@extends('layouts.app')
@section('title', 'Nuevo Trámite')

@section('content')
<div class="page-body">

  <div class="screen-head">
    <div><h2 class="nowrap">Registrar Nuevo Trámite</h2><p class="nowrap">Paso <span id="stepLabel">1</span> de 7</p></div>
    <div class="head-actions"><x-btn-ejemplo tipo="tramite" /></div>
  </div>

  <form method="POST" action="{{ route('tramites.store') }}" id="tramiteForm" novalidate>
    @csrf
    <div class="wizard-shell">

      <div class="wizard-stepper" id="tramiteStepper">
        <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Identificación</strong><small>Datos base</small></div>
        <div class="wizard-step"        data-step="2"><span class="wizard-dot"></span><strong>Información</strong><small>Objetivo</small></div>
        <div class="wizard-step"        data-step="3"><span class="wizard-dot"></span><strong>Operación</strong><small>ATDT</small></div>
        <div class="wizard-step"        data-step="4"><span class="wizard-dot"></span><strong>Fundamento</strong><small>De dónde sale</small></div>
        <div class="wizard-step"        data-step="5"><span class="wizard-dot"></span><strong>Requisitos</strong><small>Documentos</small></div>
        <div class="wizard-step"        data-step="6"><span class="wizard-dot"></span><strong>Portal</strong><small>Ciudadanía</small></div>
        <div class="wizard-step"        data-step="7"><span class="wizard-dot"></span><strong>Confirmar</strong><small>Guardar</small></div>
      </div>

      <div class="wizard-panel">

        {{-- PASO 1: Identificación --}}
        <div class="wizard-content active" data-panel="1">
          <div class="wizard-panel-head">
            <span class="wizard-panel-icon"></span>
            <div>
              <h3>Identificación del trámite</h3>
              <p>Nombre, unidad responsable y clave del trámite.</p>
            </div>
          </div>

          <div class="wizard-fields">

            {{-- Nombre oficial: texto libre con longitud máxima --}}
            <div class="field span-2">
              <label for="nombre_oficial">Nombre oficial del trámite *</label>
              <input id="nombre_oficial" name="nombre_oficial" type="text" required
                     maxlength="500"
                     placeholder="Ej. Licencia de Funcionamiento Comercial Tipo A"
                     value="{{ old('nombre_oficial') }}">
              <small class="help-small">Nombre completo según la disposición que lo crea.</small>
            </div>

            {{-- Tipo de trámite desde catálogo --}}
            <div class="field">
              <label for="tipo_tramite_id">Tipo de trámite</label>
              <select id="tipo_tramite_id" name="tipo_tramite_id">
                <option value="">— Seleccione tipo —</option>
                @foreach(\App\Models\TipoTramite::activos()->get() as $tt)
                  <option value="{{ $tt->id }}"
                    {{ old('tipo_tramite_id') == $tt->id ? 'selected' : '' }}
                    title="{{ $tt->descripcion }}">
                    {{ $tt->nombre }}
                  </option>
                @endforeach
              </select>
              <small class="help-small">Clasifica el trámite (Licencia, Permiso, Registro…).</small>
            </div>

            {{-- Dependencia: se toma del perfil del usuario, no se puede cambiar --}}
            @php
              $miDependencia   = auth()->user()->dependencia;
              $misUnidades     = \App\Models\UnidadAdministrativa::where('dependencia_id', auth()->user()->dependencia_id)
                                    ->orderBy('nombre')->get();
              $unidadAutoId    = $misUnidades->count() === 1 ? $misUnidades->first()->id : null;
            @endphp
            <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">

            <x-field-help label="Dependencia">
              <input type="text" value="{{ $miDependencia?->nombre ?? 'Sin dependencia asignada' }}" disabled class="u-input-disabled">
              <small class="help-small">Asignada desde tu perfil de usuario. Contacta al administrador para cambiarla.</small>
            </x-field-help>

            {{-- Fase F.1: Unidad administrativa auto-selección --}}
            <div class="field">
              <label for="unidad_id">Unidad administrativa</label>
              @if($unidadAutoId)
                <input type="hidden" name="unidad_id" value="{{ $unidadAutoId }}">
                <input type="text" value="{{ $misUnidades->first()->nombre }}" disabled class="u-input-readonly">
                <small class="help-small">Seleccionada automáticamente.</small>
              @elseif($misUnidades->count() > 1)
                <select id="unidad_id" name="unidad_id">
                  <option value="">— Seleccione unidad —</option>
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
              <small class="help-small">Persona que registra el trámite.</small>
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
            <x-field-help label="Objetivo del trámite" :required="true" class="span-2">
              <textarea required name="objetivo" rows="4" placeholder="Describa qué resuelve o qué beneficio otorga...">{{ old('objetivo') }}</textarea>
            </x-field-help>
            <div class="field">
              <x-field-help label="¿A quién va dirigido el trámite?">
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
                    <option value="">— No aplica / sin especificar —</option>
                    <option value="APERTURA"  {{ old('etapa_operacion')==='APERTURA'  ? 'selected' : '' }}>Apertura — la empresa va a iniciar operaciones</option>
                    <option value="OPERACIÓN" {{ old('etapa_operacion')==='OPERACIÓN' ? 'selected' : '' }}>Operación — la empresa ya está operando</option>
                    <option value="CIERRE"    {{ old('etapa_operacion')==='CIERRE'    ? 'selected' : '' }}>Cierre — la empresa va a cerrar operaciones</option>
                  </select>
                </x-field-help>
              </div>
            </div>
            <x-field-help label="Frecuencia">
              <select name="frecuencia">
                <option {{ old('frecuencia')==='Alta'?'selected':'' }}>Alta</option>
                <option {{ old('frecuencia')==='Media'?'selected':'' }}>Media</option>
                <option {{ old('frecuencia')==='Baja'?'selected':'' }}>Baja</option>
                <option {{ old('frecuencia')==='Eventual'?'selected':'' }}>Eventual</option>
              </select>
            </x-field-help>

            <x-input-validado tipo="numero_entero" name="volumen_anual" label="Volumen anual estimado *" min="0" placeholder="Ej. 1250" :value="old('volumen_anual')" />

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
              <x-input-validado tipo="numero_entero" name="num_areas" id="numAreas" label="Número de áreas que participan" min="0" placeholder="Ej. 3" :value="old('num_areas')" />
              <div id="areasParticipantesWrap" style="display:none">
                <x-field-help label="Áreas que participan">
                  <input name="areas_participantes" placeholder="Ej. Ventanilla, Tesorería, Protección Civil" value="{{ old('areas_participantes') }}">
                </x-field-help>
              </div>
              <x-input-validado tipo="numero_entero" name="visitas_requeridas" label="Visitas requeridas" min="0" placeholder="0" :value="old('visitas_requeridas')" />

              {{-- Ítem C: Plazo de resolución --}}
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
              <x-field-help label="Tiempo de atención (duración del trámite)">
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
              <x-field-help label="Nivel de digitalización">
                {{-- Bug #B10: el select queda BLOQUEADO. El valor sólo se establece
                     vía calculadora oficial (35 criterios ATDT). El hidden input
                     lleva el valor real al backend porque los <select disabled>
                     no se envían con el form. --}}
                <select name="nivel_digitalizacion_display" id="nivelDigSelect" disabled style="background:var(--surface-low);cursor:not-allowed">
                  <option value="0" {{ old('nivel_digitalizacion')==='0' || old('nivel_digitalizacion')===0 ? 'selected' : '' }}>Nivel 0 — Sin digitalización</option>
                  <option value="1" {{ old('nivel_digitalizacion',1)==1?'selected':'' }}>Nivel 1 — Eficiencia administrativa básica</option>
                  <option value="2" {{ old('nivel_digitalizacion')==2?'selected':'' }}>Nivel 2 — Productividad y reducción de costos</option>
                  <option value="3" {{ old('nivel_digitalizacion')==3?'selected':'' }}>Nivel 3 — Acceso electrónico transaccional</option>
                  <option value="4" {{ old('nivel_digitalizacion')==4?'selected':'' }}>Nivel 4 — Experiencia ciudadana unificada</option>
                  <option value="5" {{ old('nivel_digitalizacion')==5?'selected':'' }}>Nivel 5 — Innovación, transparencia y participación</option>
                </select>
                <input type="hidden" name="nivel_digitalizacion" id="nivelDigHidden" value="{{ old('nivel_digitalizacion', 1) }}">
                <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px" onclick="abrirCalcDig()">Calcular nivel con el cuestionario oficial</button>
                <small class="campo-nota">Use la calculadora oficial para establecer el nivel. No se puede editar a mano.</small>
              </x-field-help>
            </div>
          </div>

          {{-- Sub-tarjeta 4: Costos del trámite (CBD) --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Costos del trámite (CBD)</h4>
              <p>Costo público, derechos, copias y fundamento jurídico del cobro. Entran al Costo Burocrático Directo.</p>
            </div>
            <div class="wizard-fields">
              <x-field-help label="Costo público">
                <div class="costo-grupo">
                  <select id="costoTipo" onchange="actualizarCosto()">
                    <option value="gratuito" {{ old('costo_tipo') === 'con_costo' ? '' : 'selected' }}>Gratuito</option>
                    <option value="con_costo" {{ old('costo_tipo') === 'con_costo' ? 'selected' : '' }}>Con precio</option>
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
                <input type="hidden" name="portal_costo" id="costoTexto" value="{{ old('portal_costo', 'Gratuito') }}">
                <input type="hidden" name="costo_tipo" id="costoTipoHidden" value="{{ old('costo_tipo', 'gratuito') }}">
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
              {{-- Bug #B11: Monto de derechos. Se oculta cuando "es variable"
                   está marcado, porque mostrar $0.00 confunde al enlace.
                   En su lugar aparece el aviso #montoDerechosVariableAviso. --}}
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
              {{-- Ítem E: pago de derechos variable (ej. predial). Checkbox fuera
                   de <div class="field"> para que no herede el estilo uppercase
                   de los labels de los campos. --}}
              <label class="checkbox-opcion span-2">
                <input type="checkbox" name="monto_derechos_variable" value="1" id="montoVariableChk"
                  onchange="toggleMontoReferencia()" {{ old('monto_derechos_variable') ? 'checked' : '' }}>
                <span>El pago de derechos es variable (depende del caso, ej. predial)</span>
              </label>
              <div id="montoReferenciaWrap" style="display:none">
                <x-field-help label="Base de cálculo del monto (referencia)" class="span-2">
                  <input name="monto_derechos_referencia" placeholder="Ej. tarifa mínima de la tabla municipal" value="{{ old('monto_derechos_referencia') }}">
                  <small class="campo-nota">El monto capturado se usa como estimación; esta nota explica de dónde sale.</small>
                </x-field-help>
              </div>
              <x-input-validado tipo="numero_entero" name="copias_cantidad" label="Número de copias requeridas" min="0" placeholder="0" :value="old('copias_cantidad', 0)" />
              <x-input-validado tipo="numero_decimal" name="copias_precio" label="Precio por copia (pesos)" min="0" step="0.01" placeholder="1.50" :value="old('copias_precio', 1.50)" />
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

          <div class="assist-box">
            <strong>Fórmula ATDT:</strong> CBD = Derechos + Copias + Requisitos con costo · CBI = Tiempo requisitos + Tiempo resolución · CBU = CBD + CBI · CBT = CBU × Volumen anual.
          </div>

          {{-- Sub-tarjeta 6: Pasos para realizar el trámite --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Pasos para realizar el trámite</h4>
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

        {{-- PASO 5: Requisitos --}}
        <div class="wizard-content" data-panel="5">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Requisitos</h3><p>Registre cada documento necesario de forma clara.</p></div></div>

          {{-- Ítem A #21: ¿Guarda relación? Radio Sí/No con detalle condicional.
               Antes era un select con 4 opciones (Naturaleza, Secuencia,
               Dependencia funcional, Ninguna) que confundía al enlace porque
               las distinciones eran sutiles. Ahora es una pregunta binaria
               y el subtipo se describe en el campo de detalle. --}}
          <div class="wizard-fields" style="margin-bottom:1.2rem">
            <x-field-help label="¿Guarda relación con otros trámites?" class="span-2">
              @php
                // Migración suave: si el dato viejo era cualquier cosa distinta
                // a "Ninguna", lo tratamos como "Sí" para no perder el indicador.
                $rel = old('tipo_relacion', 'Ninguna');
                $relSi = ($rel !== 'Ninguna' && $rel !== '');
              @endphp
              <div class="radio-group" style="display:flex; gap:24px; padding:8px 0;">
                <label class="radio-item">
                  <input type="radio" name="tipo_relacion" value="Ninguna"
                    {{ !$relSi ? 'checked' : '' }}
                    onchange="toggleRelacionados()">
                  <span>No</span>
                </label>
                <label class="radio-item">
                  <input type="radio" name="tipo_relacion" value="Sí"
                    {{ $relSi ? 'checked' : '' }}
                    onchange="toggleRelacionados()">
                  <span>Sí</span>
                </label>
              </div>
            </x-field-help>
            <div id="relacionadosDetalleWrap" style="display:{{ $relSi ? '' : 'none' }}">
              <x-field-help label="Trámites relacionados" class="span-2">
                <textarea name="relacionados_detalle" rows="3" placeholder="Ej. Licencia de uso de suelo (la requiere antes), Visto bueno de Protección Civil (lo habilita)">{{ old('relacionados_detalle') }}</textarea>
              </x-field-help>
            </div>
          </div>

          <div id="reqContainer">
            <article class="requirement-card">
              <strong>Requisito 1</strong>
              <div class="wizard-fields">
                <x-field-help label="Nombre del requisito" class="span-2">
                  <input name="requisitos[0][nombre]" placeholder="Ej. Identificación oficial vigente">
                </x-field-help>
                <x-field-help label="¿Se presenta en original?">
                  <select name="requisitos[0][original]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select>
                </x-field-help>
                <x-field-help label="¿Se presenta en copia?">
                  <select name="requisitos[0][copia]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select>
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
                {{-- Ítem E: costo del requisito (sin costo / monto fijo / variable) --}}
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

        {{-- PASO 4: Fundamento jurídico --}}
        <div class="wizard-content" data-panel="4">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Fundamento jurídico</h3><p>De dónde sale el trámite: la normativa que lo crea u obliga. Puede citar del catálogo de regulaciones o escribir manualmente.</p></div></div>
          <div class="wizard-fields">

            {{-- Citar desde el catálogo de regulaciones (si hay regulaciones convertidas) --}}
            <x-citar-regulacion :selected="old('regulacion_id')" label="Citar regulación del catálogo (opcional)" />

            <x-field-help label="Nombre de la norma (si no está en el catálogo)" class="span-2">
              <input name="fundamento_normativa" placeholder="Ej. Reglamento de Comercio del Municipio de La Paz" value="{{ old('fundamento_normativa') }}">
              <small class="help-small">Si ya citó del catálogo, puede dejar este campo vacío.</small>
            </x-field-help>
            <x-field-help label="Tipo de norma">
              <select name="fundamento_tipo">
                <option value="">Seleccione...</option>
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
              <x-field-help label="Dirección donde se realiza el trámite">
                <input name="portal_direccion" placeholder="Calle, número, colonia, La Paz, B.C.S." value="{{ old('portal_direccion') }}">
              </x-field-help>
            </div>
            <div id="modalidadUrl" class="field span-2" style="display:none">
              <x-field-help label="URL donde se realiza el trámite en línea">
                <input name="portal_url" type="url" placeholder="https://tramites.lapaz.gob.mx/..." value="{{ old('portal_url') }}">
              </x-field-help>
            </div>
            <x-field-help label="Objetivo del trámite" class="span-2">
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

            {{-- Fase F.4: Horarios de atención estructurados --}}
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
            <p style="margin:0 0 20px;color:#667085">Revise la información antes de guardar el registro.</p>
            <div class="assist-box" style="max-width:600px;width:100%;text-align:left">
              <strong>Guardar como borrador:</strong> guarda el avance sin enviarlo a revisión. Podrá editarlo y enviarlo después.<br>
              <strong>Guardar y enviar:</strong> guarda y envía a revisión. El trámite será visible para Jurídico, Sujeto Obligado y Revisora.
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
{{-- Bug #B14: Modal de horarios de atención, flujo de 3 pasos --}}
  <div id="modalHorarios">
    <div class="horario-modal-inner">
      <h3 style="margin:0 0 4px">Horarios de atención</h3>
      <p style="margin:0 0 18px;font-size:13px;color:#6b7280">Configure en tres pasos los días y horarios en que se atiende el trámite.</p>

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
    valorUma: {{ \App\Models\TramiteDerecho::valorUmaVigente() ?: 0 }}
  };
</script>
<script src="{{ asset('js/tramites-create.js') }}?v={{ filemtime(public_path('js/tramites-create.js')) }}"></script>
@endpush