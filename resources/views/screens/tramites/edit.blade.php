@extends('layouts.app')
@section('title', 'Editar Trámite')

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
  .acc-final { margin:16px 0; }
</style>
<div class="page-body">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar Trámite</h2>
      <p class="nowrap">{{ $tramite->homoclave ?? 'Sin folio' }} — {{ $tramite->nombre_oficial }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($tramite->estatus === 'completado' || $tramite->estatus === 'en_firma')
    <div class="assist-box">
      <strong>Este trámite está {{ str_replace('_', ' ', $tramite->estatus) }}.</strong> Solo se pueden editar trámites en borrador o en corrección.
    </div>
  @else

  <div class="detalle-con-timeline">
    <div class="detalle-main">
  <form method="POST" action="{{ route('tramites.update', $tramite) }}" id="editForm" novalidate>
    @csrf
    @method('PUT')

    <div class="acordeon-tramite">

        {{-- SECCIÓN 1: Identificación --}}
        <section class="acc-seccion abierta" data-acc="1">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Identificación del trámite</span>
            <span class="acc-sub">Nombre, unidad responsable y clave</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          {{-- Observaciones de esta sección (#18) --}}
          @include('partials.observaciones-seccion', [
            'seccion' => 'Datos generales',
            'items'   => $observacionesPorSeccion['Datos generales'] ?? collect(),
            'campos'  => $camposObservables['Datos generales'] ?? [],
          ])


          <div class="wizard-fields">

            {{-- Selector de naturaleza pre-poblado --}}
            @php $natActual = old('naturaleza', $tramite->naturaleza ?? 'tramite'); @endphp
            <input type="hidden" name="naturaleza" id="naturalezaHidden" value="{{ $natActual }}">

            <div class="field span-2">
              <label>¿Qué tipo de registro es? *</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px">
                <div class="wz-opt {{ $natActual === 'tramite' ? 'sel' : '' }}"
                     onclick="elegirNaturaleza('tramite')" id="optTramite" data-nat-fijo style="cursor:pointer">
                  Trámite
                  <small>Solicitud o entrega de información que la persona realiza ante la autoridad.</small>
                </div>
                <div class="wz-opt {{ $natActual === 'servicio' ? 'sel' : '' }}"
                     onclick="elegirNaturaleza('servicio')" id="optServicio" data-nat-fijo style="cursor:pointer">
                  Servicio
                  <small>Beneficio, programa o actividad que la autoridad brinda a las personas.</small>
                </div>
              </div>
            </div>

            <div class="field span-2">
              <label for="nombre_oficial" id="labelNombreOficial">Nombre oficial {{ $natActual === 'servicio' ? 'del servicio' : 'del trámite' }} *</label>
              <input id="nombre_oficial" required name="nombre_oficial" type="text"
                     maxlength="500"
                     value="{{ old('nombre_oficial', $tramite->nombre_oficial) }}"
                     placeholder="Nombre oficial">
            </div>

            {{-- Tipo de trámite (visible solo cuando naturaleza=tramite) --}}
            <div class="field" id="campoTipoTramite" style="{{ $natActual === 'servicio' ? 'display:none' : '' }}">
              <label for="tipo_tramite_id">Tipo de trámite</label>
              <select id="tipo_tramite_id" name="tipo_tramite_id">
                <option value="">— Seleccione tipo —</option>
                @foreach(\App\Models\TipoTramite::activos()->get() as $tt)
                  <option value="{{ $tt->id }}"
                    {{ old('tipo_tramite_id', $tramite->tipo_tramite_id) == $tt->id ? 'selected' : '' }}>
                    {{ $tt->nombre }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Tipo de servicio (visible solo cuando naturaleza=servicio) --}}
            <div class="field" id="campoTipoServicio" style="{{ $natActual !== 'servicio' ? 'display:none' : '' }}">
              <label for="tipo_servicio">Tipo de servicio</label>
              <select id="tipo_servicio" name="tipo_servicio">
                <option value="">— Seleccione tipo de servicio —</option>
                @foreach($tiposServicio as $ts)
                  <option value="{{ $ts }}" {{ old('tipo_servicio', $tramite->tipo_servicio) === $ts ? 'selected' : '' }}>
                    {{ $ts }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Dependencia: la del trámite (no se puede cambiar desde aquí) --}}
            <input type="hidden" name="dependencia_id" value="{{ $tramite->dependencia_id }}">
            <x-field-help label="Dependencia">
              <input type="text" value="{{ $tramite->dependencia->nombre ?? '—' }}" disabled class="u-input-disabled">
              <small class="help-small">Asignada al momento de crear el trámite.</small>
            </x-field-help>

            {{-- Fase F.1: Unidad administrativa con auto-selección --}}
            <div class="field">
              <label for="unidad_id">Unidad administrativa</label>
              @php
                $unidades = $unidadesDependencia ?? collect();
                $unidadGuardada = old('unidad_id', $tramite->unidad_id);
              @endphp
              @if($unidades->count() === 1)
                {{-- Auto-selección: una sola unidad --}}
                <input type="hidden" name="unidad_id" value="{{ $unidades->first()->id }}">
                <input type="text" value="{{ $unidades->first()->nombre }}" disabled class="u-input-readonly">
                <small class="help-small">Seleccionada automáticamente (única unidad disponible).</small>
              @elseif($unidades->count() > 1)
                <select id="unidad_id" name="unidad_id">
                  <option value="">— Seleccione unidad —</option>
                  @foreach($unidades as $uni)
                    <option value="{{ $uni->id }}" {{ $unidadGuardada == $uni->id ? 'selected' : '' }}>
                      {{ $uni->codigo }} — {{ $uni->nombre }}
                    </option>
                  @endforeach
                </select>
                @error('unidad_id')<span class="field-error">{{ $message }}</span>@enderror
              @else
                <input type="text" value="Sin unidades registradas" disabled style="background:#f3f4f6;color:#9ca3af">
                <small class="help-small">No hay unidades activas para esta dependencia. Contacta al administrador.</small>
              @endif
            </div>

            {{-- La homoclave se genera de dependencia + unidad administrativa.
                 La previsualización en vivo está en el script al final. --}}

            <x-input-validado
              tipo="solo_texto"
              name="servidor_publico"
              id="servidor_publico"
              label="Persona servidora pública responsable"
              :value="$tramite->servidor_publico"
              placeholder="Nombre completo del responsable"
              maxlength="255" />

            <div class="field">
              <label for="homoclave_input">Homoclave</label>
              <input id="homoclave_input" name="homoclave" type="text"
                     maxlength="50"
                     value="{{ old('homoclave', $tramite->homoclave) }}"
                     placeholder="Se regenerará al cambiar la Unidad Responsable"
                     readonly
                     class="u-input-readonly">
              <small class="help-small">Se genera automáticamente con el código de la Unidad Responsable + correlativo. Cambie la UR para regenerar.</small>
            </div>

            {{-- #18: Sujeto Obligado editable con precarga del actual.
                 Antes el campo era un input disabled — si la titularidad
                 de la dependencia cambiaba, el trámite quedaba con un
                 titular obsoleto y no había forma de corregirlo desde la UI.
                 $sujetoActual, $sujetosDisponibles y $enlaceTramite vienen
                 del controlador (edit()). --}}
            <x-field-help label="Sujeto Obligado">
              <select name="sujeto_obligado_id">
                <option value="">— Sin titular asignado —</option>
                @foreach($sujetosDisponibles as $so)
                  <option value="{{ $so->id }}"
                    {{ old('sujeto_obligado_id', $sujetoActual?->id) == $so->id ? 'selected' : '' }}>
                    {{ $so->nombre }}@if($so->cargo) — {{ $so->cargo }}@endif
                  </option>
                @endforeach
              </select>
              @if($sujetosDisponibles->isEmpty())
                <small class="help-small">No hay sujetos obligados registrados para esta dependencia. Pídele al administrador que los agregue en Catálogos → Sujetos obligados.</small>
              @endif
            </x-field-help>

            <x-field-help label="Enlace">
              <input type="text" value="{{ $enlaceTramite?->name ?? auth()->user()->name }}" disabled class="u-input-disabled">
              <input type="hidden" name="enlace_id" value="{{ old('enlace_id', $tramite->enlace_id ?? auth()->id()) }}">
              <small class="help-small">Persona que registra el trámite.</small>
            </x-field-help>

          </div>
          </div>
        </section>

        {{-- SECCIÓN 2: Información general --}}
        <section class="acc-seccion" data-acc="2">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Información general</span>
            <span class="acc-sub">Objetivo, población y plazos</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          {{-- Observaciones de Información general se muestran en el aviso de "Datos generales" --}}

          <div class="wizard-fields">
            <x-field-help label="Objetivo del trámite" :required="true" class="span-2">
              <textarea required name="objetivo" rows="4" placeholder="Describa qué resuelve o qué beneficio otorga...">{{ old('objetivo', $tramite->objetivo) }}</textarea>
            </x-field-help>
            <div class="field">
              <x-field-help label="¿A quién va dirigido el trámite?">
                <select name="dirigido_a" onchange="toggleEtapaOperacion()">
                  <option value="ambas"  {{ old('dirigido_a', $tramite->dirigido_a) === 'ambas'  ? 'selected' : '' }}>Personas físicas y morales</option>
                  <option value="fisica" {{ old('dirigido_a', $tramite->dirigido_a) === 'fisica' ? 'selected' : '' }}>Solo personas físicas</option>
                  <option value="moral"  {{ old('dirigido_a', $tramite->dirigido_a) === 'moral'  ? 'selected' : '' }}>Solo personas morales</option>
                </select>
              </x-field-help>
              {{-- Etapa de operación: vive junto a "¿a quién va dirigido?" porque
                   depende lógicamente de él. Aparece solo cuando va dirigido a
                   personas morales o a ambas. --}}
              @php $dirigidoActual = old('dirigido_a', $tramite->dirigido_a ?? 'ambas'); @endphp
              <div id="etapaOperacionWrap" style="display:{{ in_array($dirigidoActual, ['moral','ambas']) ? '' : 'none' }}; margin-top:12px">
                <x-field-help label="Etapa de operación de la persona moral">
                  <select name="etapa_operacion">
                    <option value="">— No aplica / sin especificar —</option>
                    <option value="APERTURA"  {{ old('etapa_operacion', $tramite->etapa_operacion) === 'APERTURA'  ? 'selected' : '' }}>Apertura — la empresa va a iniciar operaciones</option>
                    <option value="OPERACIÓN" {{ old('etapa_operacion', $tramite->etapa_operacion) === 'OPERACIÓN' ? 'selected' : '' }}>Operación — la empresa ya está operando</option>
                    <option value="CIERRE"    {{ old('etapa_operacion', $tramite->etapa_operacion) === 'CIERRE'    ? 'selected' : '' }}>Cierre — la empresa va a cerrar operaciones</option>
                  </select>
                </x-field-help>
              </div>
            </div>
            <x-field-help label="Frecuencia">
              <select name="frecuencia">
                @foreach(['Alta','Media','Baja','Eventual'] as $f)
                  <option {{ $tramite->frecuencia === $f ? 'selected' : '' }}>{{ $f }}</option>
                @endforeach
              </select>
            </x-field-help>

            <x-input-validado tipo="numero_entero" name="volumen_anual" label="Volumen anual estimado" min="0" :max="config('punta.topes_tramite.volumen_anual')" placeholder="Ej. 1250" :value="old('volumen_anual', $tramite->volumen_anual)" help="Máximo permitido: {{ number_format(config('punta.topes_tramite.volumen_anual')) }} trámites al año." />

            {{-- Sector y subsector económico SCIAN --}}
            <x-selector-scian :sector="old('sector_id', $tramite->sector_id)" :subsector="old('subsector_id', $tramite->subsector_id)" />
          </div>
          </div>
        </section>

        {{-- SECCIÓN 3: Operación y costos ATDT --}}
        <section class="acc-seccion" data-acc="3">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Operación y costos burocráticos</span>
            <span class="acc-sub">Datos para el cálculo ATDT</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          {{-- Observaciones de esta sección (#18) --}}
          @include('partials.observaciones-seccion', [
            'seccion' => 'Costo burocrático',
            'items'   => $observacionesPorSeccion['Costo burocrático'] ?? collect(),
            'campos'  => $camposObservables['Costo burocrático'] ?? [],
          ])

          {{-- Sub-tarjeta 1: Esfuerzo administrativo --}}
          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Esfuerzo administrativo</h4>
              <p>Cuántas áreas tocan el expediente, cuántas visitas hace la persona y plazo legal de resolución.</p>
            </div>
            <div class="wizard-fields">
              <div class="field">
                <label>Número de áreas que participan</label>
                <input name="num_areas" id="numAreas" type="number" min="0" value="{{ old('num_areas', $tramite->num_areas) }}">
              </div>
              <div id="areasParticipantesWrap" style="display:none">
                <x-field-help label="Áreas que participan">
                  <input name="areas_participantes" value="{{ old('areas_participantes', $tramite->areas_participantes) }}" placeholder="Ej. Ventanilla, Tesorería">
                </x-field-help>
              </div>
              <div class="field">
                <label>Visitas requeridas</label>
                <input name="visitas_requeridas" type="number" min="0" value="{{ old('visitas_requeridas', $tramite->visitas_requeridas) }}">
              </div>

              {{-- Ítem C: Plazo de resolución --}}
              <div class="field">
                <label>Plazo máximo de resolución</label>
                <div class="split-fields">
                  <input name="plazo_resolucion_cantidad" type="number" min="0" inputmode="numeric" value="{{ old('plazo_resolucion_cantidad', $tramite->plazo_resolucion_cantidad) }}" placeholder="Cantidad">
                  <select name="plazo_resolucion_unidad">
                    <option value="habiles"   {{ ($tramite->plazo_resolucion_unidad ?? 'habiles') === 'habiles'   ? 'selected' : '' }}>Días hábiles</option>
                    <option value="naturales" {{ $tramite->plazo_resolucion_unidad === 'naturales' ? 'selected' : '' }}>Días naturales</option>
                    <option value="meses"     {{ $tramite->plazo_resolucion_unidad === 'meses'     ? 'selected' : '' }}>Meses</option>
                    <option value="anios"     {{ $tramite->plazo_resolucion_unidad === 'anios'     ? 'selected' : '' }}>Años</option>
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
                  <input name="tiempo_traslado_horas" type="number" min="0" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_traslado_horas', $tramite->tiempo_traslado_horas) }}">
                  <input name="tiempo_traslado_min" type="number" min="0" max="59" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_traslado_min', $tramite->tiempo_traslado_min) }}">
                </div>
              </x-field-help>
              <x-field-help label="Tiempo de espera en la oficina">
                <div class="split-fields">
                  <input name="tiempo_espera_horas" type="number" min="0" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_espera_horas', $tramite->tiempo_espera_horas) }}">
                  <input name="tiempo_espera_min" type="number" min="0" max="59" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_espera_min', $tramite->tiempo_espera_min) }}">
                </div>
              </x-field-help>
              <x-field-help label="Tiempo de atención (duración del trámite)">
                <div class="split-fields">
                  <input name="tiempo_atencion_horas" type="number" min="0" inputmode="numeric" placeholder="Horas" value="{{ old('tiempo_atencion_horas', $tramite->tiempo_atencion_horas) }}">
                  <input name="tiempo_atencion_min" type="number" min="0" max="59" inputmode="numeric" placeholder="Minutos" value="{{ old('tiempo_atencion_min', $tramite->tiempo_atencion_min) }}">
                </div>
              </x-field-help>
              <div class="field">
                <label>Salario promedio por hora (W)</label>
                <input name="salario_hora_w" type="number" min="0" step="0.01" value="{{ old('salario_hora_w', $tramite->salario_hora_w ?? 68.20) }}">
                <small class="campo-nota">Multiplicador para convertir los tiempos en pesos en el CBI.</small>
              </div>
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
                  @php
                    $nivelesDig = [
                      0 => 'Nivel 0 — Sin digitalización',
                      1 => 'Nivel 1 — Eficiencia administrativa básica',
                      2 => 'Nivel 2 — Productividad y reducción de costos',
                      3 => 'Nivel 3 — Acceso electrónico transaccional',
                      4 => 'Nivel 4 — Experiencia ciudadana unificada',
                      5 => 'Nivel 5 — Innovación, transparencia y participación',
                    ];
                  @endphp
                  @foreach($nivelesDig as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ (string) $tramite->nivel_digitalizacion === (string) $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                  @endforeach
                </select>
                <input type="hidden" name="nivel_digitalizacion" id="nivelDigHidden" value="{{ old('nivel_digitalizacion', $tramite->nivel_digitalizacion ?? 1) }}">
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
                    @php $costoActual = old('portal_costo_publico', $ficha->costo_publico ?? 'Gratuito'); $esConCosto = $costoActual !== 'Gratuito' && $costoActual !== ''; @endphp
                    <option value="gratuito" {{ $esConCosto ? '' : 'selected' }}>Gratuito</option>
                    <option value="con_costo" {{ $esConCosto ? 'selected' : '' }}>Con precio</option>
                  </select>
                  <input type="number" id="costoMonto" min="0" step="0.01" placeholder="0.00"
                    value="{{ $esConCosto ? preg_replace('/[^0-9.]/', '', $costoActual) : 0 }}"
                    oninput="actualizarCosto()" style="display:none">
                  <select id="costoUnidad" onchange="actualizarCosto()" style="display:none">
                    <option value="pesos" selected>Pesos</option>
                    <option value="UMA">UMA</option>
                  </select>
                  <span class="costo-moneda" id="costoEquiv"></span>
                </div>
                <input type="hidden" name="portal_costo_publico" id="costoTexto" value="{{ $costoActual }}">
                <input type="hidden" name="costo_tipo" id="costoTipoHidden" value="{{ $esConCosto ? 'con_costo' : 'gratuito' }}">
                <input type="hidden" name="costo_monto" id="costoMontoHidden" value="0">
                <input type="hidden" name="costo_unidad" id="costoUnidadHidden" value="pesos">
              </x-field-help>
              @php $costoTieneFj = $tramite->fj_norma || $tramite->fj_capitulo || $tramite->fj_articulo; @endphp
              <div class="field span-2 fj-bloque">
                <label class="fj-pregunta">¿El costo del trámite tiene fundamento jurídico? *</label>
                <div class="fj-radios">
                  <label><input type="radio" name="costo_fj_tiene" value="1" required onchange="toggleFjRadio(this)" {{ $costoTieneFj ? 'checked' : '' }}> Sí</label>
                  <label><input type="radio" name="costo_fj_tiene" value="0" required onchange="toggleFjRadio(this)" {{ $costoTieneFj ? '' : 'checked' }}> No</label>
                </div>
                <div class="fj-campos fj-linea" style="display:{{ $costoTieneFj ? '' : 'none' }}">
                  <div>
                    @php $hLey = config('helpTexts')['Ley o reglamento'] ?? null; @endphp
                    <label>Ley o reglamento @if($hLey)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Ley o reglamento">?</button>@endif</label>
                    @if($hLey)<div class="field-help-box">{{ $hLey }}</div>@endif
                    <input name="costo_fj_norma" placeholder="Ej. Ley de Hacienda Municipal" value="{{ $tramite->fj_norma }}">
                  </div>
                  <div>
                    @php $hCap = config('helpTexts')['Capítulo'] ?? null; @endphp
                    <label>Capítulo @if($hCap)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Capítulo">?</button>@endif</label>
                    @if($hCap)<div class="field-help-box">{{ $hCap }}</div>@endif
                    <input name="costo_fj_capitulo" placeholder="Ej. Cap. II" value="{{ $tramite->fj_capitulo }}">
                  </div>
                  <div>
                    @php $hArt = config('helpTexts')['Artículo'] ?? null; @endphp
                    <label>Artículo @if($hArt)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Artículo">?</button>@endif</label>
                    @if($hArt)<div class="field-help-box">{{ $hArt }}</div>@endif
                    <input name="costo_fj_articulo" placeholder="Ej. Art. 45" value="{{ $tramite->fj_articulo }}">
                  </div>
                </div>
              </div>
              <x-field-help label="Pago de derechos" class="span-2">
                <div id="derechosLista" class="derechos-lista">
                  {{-- filas generadas por JS --}}
                </div>
                <div class="derechos-pie">
                  <button type="button" class="btn btn-outline btn-sm" onclick="agregarDerecho()">+ Agregar derecho</button>
                  <span class="derechos-total">Total derechos: <strong id="derechosTotal">$0.00 MXN</strong></span>
                </div>
                <input type="hidden" name="derechos_json" id="derechosJson"
                  value="{{ old('derechos_json', $tramite->derechos->map(fn($d) => ['concepto' => $d->concepto, 'monto' => (float) $d->monto, 'unidad' => $d->unidad ?? 'pesos', 'es_variable' => (bool) ($d->es_variable ?? false), 'fj_norma' => $d->fj_norma, 'fj_capitulo' => $d->fj_capitulo, 'fj_articulo' => $d->fj_articulo])->toJson()) }}">
              </x-field-help>
              {{-- Bug #B11: Monto de derechos. Se oculta cuando "es variable"
                   está marcado, porque mostrar $0.00 confunde al enlace.
                   En su lugar aparece el aviso #montoDerechosVariableAviso. --}}
              <div id="montoDerechosFijoWrap" class="span-2" style="display:{{ old('monto_derechos_variable', $tramite->monto_derechos_variable) ? 'none' : '' }}">
                <div class="field span-2">
                  <label>Monto de derechos (MD) en pesos</label>
                  <input id="montoDerechosCalc" name="monto_derechos_display" type="number" readonly
                    value="{{ old('monto_derechos', $tramite->monto_derechos) }}"
                    style="background:var(--surface-low);cursor:not-allowed">
                  <small class="campo-nota">Resultado del total de "Pago de derechos" (convertido a pesos).</small>
                </div>
              </div>
              {{-- Aviso cuando "es variable": explica al enlace por qué no se calcula. --}}
              <div id="montoDerechosVariableAviso" class="assist-box span-2" style="display:{{ old('monto_derechos_variable', $tramite->monto_derechos_variable) ? '' : 'none' }}">
                <strong>Costo variable.</strong> El monto de derechos no se incluye en el cálculo del CBD porque depende del caso (ej. el predial varía según el valor catastral). El costo total del trámite seguirá considerando el tiempo del ciudadano (CBI) y las copias.
              </div>
              {{-- Ítem E: pago de derechos variable. Se auto-detecta cuando
                   algún derecho se marca como "Variable" (checkbox por derecho). --}}
              <input type="hidden" name="monto_derechos_variable" id="montoVariableChk" value="{{ old('monto_derechos_variable', $tramite->monto_derechos_variable) ? '1' : '0' }}">
              <div id="montoReferenciaWrap" style="display:{{ old('monto_derechos_variable', $tramite->monto_derechos_variable) ? '' : 'none' }}">
                <x-field-help label="Base de cálculo del monto (referencia)" class="span-2">
                  <input name="monto_derechos_referencia" placeholder="Ej. tarifa mínima de la tabla municipal" value="{{ old('monto_derechos_referencia', $tramite->monto_derechos_referencia) }}">
                  <small class="campo-nota">El monto capturado se usa como estimación; esta nota explica de dónde sale.</small>
                </x-field-help>
              </div>
              <div class="field">
                <label>Copias simples solicitadas</label>
                <input name="copias_cantidad" type="number" min="0" max="{{ config('punta.topes_tramite.copias') }}" value="{{ old('copias_cantidad', $tramite->copias_cantidad) }}">
              </div>
              <div class="field">
                <label>Precio por copia (pesos)</label>
                <input name="copias_precio" type="number" min="0" step="0.01" value="{{ old('copias_precio', $tramite->copias_precio ?? 1.50) }}">
              </div>
            </div>
          </div>

          {{-- Sub-tarjeta 5: Grupos de atención prioritaria (Art. 19 LNETB) --}}
          <div class="wizard-section">
            <div class="wizard-fields">
              <div class="field span-2"><label>Población objetivo</label>
                <input name="poblacion_objetivo" value="{{ old('poblacion_objetivo', $tramite->poblacion_objetivo ?? '') }}" placeholder="Ej. Comerciantes establecidos del municipio"></div>
            </div>
          </div>

          <div class="wizard-section">
            <div class="wizard-section-head">
              <h4>Grupos de atención prioritaria</h4>
              <p>Art. 19 LNETB. La agenda los lee para priorizar simplificaciones y digitalizaciones.</p>
            </div>
            <div class="wizard-fields">
              @include('partials.catalogos-tramite', [
                'gruposSel' => old('grupos_atencion', $tramite->grupos_atencion ?? []),
              ])
            </div>
          </div>

          <div class="assist-box mt-4">
            <strong>Costos actuales (ATDT):</strong>
            CBD ${{ number_format($tramite->cbd_directo ?? 0, 2) }} +
            CBI ${{ number_format($tramite->cbi_indirecto ?? 0, 2) }} =
            CBU ${{ number_format($tramite->cbu_unitario ?? 0, 2) }} ×
            {{ number_format($tramite->volumen_anual ?? 0) }} =
            CBT ${{ number_format($tramite->cbt_total ?? 0, 2) }} MXN.
            Se recalcula al guardar.
          </div>
          </div>
        </section>

        {{-- SECCIÓN 4: Requisitos (reordenada: antes era 5) --}}
        <section class="acc-seccion" data-acc="4">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Requisitos</span>
            <span class="acc-sub">Documentos que solicita el trámite</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          {{-- Observaciones de esta sección (#18) --}}
          @include('partials.observaciones-seccion', [
            'seccion' => 'Requisitos',
            'items'   => $observacionesPorSeccion['Requisitos'] ?? collect(),
            'campos'  => $camposObservables['Requisitos'] ?? [],
          ])
          {{-- Ítem A #21: ¿Guarda relación? Radio Sí/No con detalle condicional.
               Antes era un select con 4 opciones que confundía al enlace
               (las distinciones eran sutiles). Ahora es una pregunta binaria
               y el subtipo se describe en el campo de detalle. --}}
          <div class="wizard-fields" style="margin-bottom:1.2rem">
            <x-field-help label="¿Guarda relación con otros trámites? (Art. 29-VI LNETB)" class="span-2">
              @php
                $rel = old('tipo_relacion', $tramite->tipo_relacion ?? 'Ninguna');
                // Migración del dato viejo: "Sí" era el valor binario antes de
                // implementar las opciones del Art. 29 LNETB. Lo mapeamos a
                // "Naturaleza" (primer tipo real) para que el select funcione.
                $tiposValidos = ['Ninguna','Naturaleza','Secuencia','Dependencia funcional'];
                if (!in_array($rel, $tiposValidos)) $rel = ($rel !== '' && $rel !== null) ? 'Naturaleza' : 'Ninguna';
              @endphp
              <select name="tipo_relacion" onchange="toggleRelacionados()">
                <option value="Ninguna" {{ $rel === 'Ninguna' ? 'selected' : '' }}>Ninguna</option>
                <option value="Naturaleza" {{ $rel === 'Naturaleza' ? 'selected' : '' }}>Naturaleza — comparten el mismo tema o materia</option>
                <option value="Secuencia" {{ $rel === 'Secuencia' ? 'selected' : '' }}>Secuencia — uno debe completarse antes que otro</option>
                <option value="Dependencia funcional" {{ $rel === 'Dependencia funcional' ? 'selected' : '' }}>Dependencia funcional — el resultado de uno es requisito del otro</option>
              </select>
            </x-field-help>
            <div id="relacionadosDetalleWrap" style="display:{{ ($rel !== 'Ninguna' && $rel !== '') ? '' : 'none' }}">
              <x-citar-tramite
                :relacionados="$tramite->relacionados"
                :tramite_actual_id="$tramite->id"
              />
            </div>
          </div>

          <div id="reqContainer">
            @forelse($tramite->requisitos as $i => $req)
              <article class="requirement-card">
                <strong>Requisito {{ $i + 1 }}: {{ $req->nombre }}</strong>
                <div class="wizard-fields">
                  <div class="field"><label>Nombre *</label><input name="requisitos[{{ $i }}][nombre]" value="{{ $req->nombre }}" required></div>
                  {{-- Bug #44: multiselección de tipo de presentación --}}
                  @php $tiposActivos = array_map('trim', explode(',', $req->tipo_presentacion ?? '')); @endphp
                  <div class="field"><label>Tipo de presentación</label>
                    <div class="tipo-pres-checks">
                      @foreach(['original' => 'Original', 'copia' => 'Copia', 'digital' => 'Digital'] as $val => $lbl)
                        <label><input type="checkbox" name="requisitos[{{ $i }}][tipo][]" value="{{ $val }}" {{ in_array($val, $tiposActivos) ? 'checked' : '' }}> {{ $lbl }}</label>
                      @endforeach
                    </div>
                  </div>
                  <div class="field"><label>Días estimados</label><input name="requisitos[{{ $i }}][dias]" type="number" min="0" value="{{ $req->dias_estimados }}"></div>
                  <div class="field"><label>Horas estimadas</label><input name="requisitos[{{ $i }}][horas]" type="number" min="0" value="{{ $req->horas_estimadas }}"></div>
                  <div class="field"><label>Minutos estimados</label><input name="requisitos[{{ $i }}][minutos]" type="number" min="0" max="59" value="{{ $req->minutos_estimados }}"></div>
                  <div class="field span-2"><label>Observaciones</label><textarea name="requisitos[{{ $i }}][observaciones]" rows="2">{{ $req->observaciones }}</textarea></div>
                  {{-- Ítem E: costo del requisito (pre-cargado según lo guardado) --}}
                  @php
                    $reqModoCosto = $req->costo_variable ? 'variable' : ($req->tiene_costo ? 'fijo' : 'sin');
                  @endphp
                  <x-field-help label="¿Este requisito tiene costo?">
                    <select name="requisitos[{{ $i }}][costo_modo]" onchange="toggleCostoReq(this)">
                      <option value="sin"      {{ $reqModoCosto === 'sin'      ? 'selected' : '' }}>Sin costo</option>
                      <option value="fijo"     {{ $reqModoCosto === 'fijo'     ? 'selected' : '' }}>Sí, monto fijo</option>
                      <option value="variable" {{ $reqModoCosto === 'variable' ? 'selected' : '' }}>Sí, costo variable (no cuantificable)</option>
                    </select>
                  </x-field-help>
                  <div class="req-costo-monto" style="display:{{ $reqModoCosto === 'fijo' ? '' : 'none' }}">
                    <x-field-help label="Monto del requisito">
                      <input name="requisitos[{{ $i }}][costo_monto]" type="number" min="0" step="0.01" value="{{ $req->costo_requisito ?? 0 }}" placeholder="Ej. 250.00">
                    </x-field-help>
                  </div>
                  <div class="req-costo-monto" style="display:{{ $reqModoCosto === 'fijo' ? '' : 'none' }}">
                    <x-field-help label="Unidad del costo">
                      <select name="requisitos[{{ $i }}][costo_unidad]">
                        <option value="PESOS" {{ ($req->costo_unidad ?? 'PESOS') === 'PESOS' ? 'selected' : '' }}>Pesos</option>
                        <option value="UMA"   {{ ($req->costo_unidad ?? '') === 'UMA' ? 'selected' : '' }}>UMA</option>
                      </select>
                    </x-field-help>
                  </div>
                  @php $reqTieneFj = $req->fj_norma || $req->fj_capitulo || $req->fj_articulo; @endphp
                  <div class="field span-2 fj-bloque">
                    <label class="fj-pregunta">¿Este requisito tiene fundamento jurídico? *</label>
                    <div class="fj-radios">
                      <label><input type="radio" name="requisitos[{{ $i }}][fj_tiene]" value="1" required onchange="toggleFjRadio(this)" {{ $reqTieneFj ? 'checked' : '' }}> Sí</label>
                      <label><input type="radio" name="requisitos[{{ $i }}][fj_tiene]" value="0" required onchange="toggleFjRadio(this)" {{ $reqTieneFj ? '' : 'checked' }}> No</label>
                    </div>
                    <div class="fj-campos fj-linea" style="display:{{ $reqTieneFj ? '' : 'none' }}">
                      <div>
                        @php $hReqLey = config('helpTexts')['Ley o reglamento'] ?? null; @endphp
                        <label>Ley o reglamento @if($hReqLey)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Ley o reglamento">?</button>@endif</label>
                        @if($hReqLey)<div class="field-help-box">{{ $hReqLey }}</div>@endif
                        <input name="requisitos[{{ $i }}][fj_norma]" placeholder="Ej. Reglamento de Comercio" value="{{ $req->fj_norma }}">
                      </div>
                      <div>
                        @php $hReqCap = config('helpTexts')['Capítulo'] ?? null; @endphp
                        <label>Capítulo @if($hReqCap)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Capítulo">?</button>@endif</label>
                        @if($hReqCap)<div class="field-help-box">{{ $hReqCap }}</div>@endif
                        <input name="requisitos[{{ $i }}][fj_capitulo]" placeholder="Ej. Cap. III" value="{{ $req->fj_capitulo }}">
                      </div>
                      <div>
                        @php $hReqArt = config('helpTexts')['Artículo'] ?? null; @endphp
                        <label>Artículo @if($hReqArt)<button type="button" class="field-help-btn" onclick="toggleHelp(this)" aria-label="Ayuda para Artículo">?</button>@endif</label>
                        @if($hReqArt)<div class="field-help-box">{{ $hReqArt }}</div>@endif
                        <input name="requisitos[{{ $i }}][fj_articulo]" placeholder="Ej. Art. 12" value="{{ $req->fj_articulo }}">
                      </div>
                    </div>
                  </div>
                  <input type="hidden" name="requisitos[{{ $i }}][id]" value="{{ $req->id }}">
                </div>
              </article>
            @empty
              <div class="cal-empty-state">No hay requisitos registrados.</div>
            @endforelse
          </div>
          <div class="section-actions section-actions-start mt-3">
            <button type="button" class="btn btn-outline btn-sm" onclick="addReq()">+ Agregar requisito</button>
          </div>
          </div>
        </section>
        {{-- SECCIÓN 5: Fundamento jurídico (reordenada: antes era 4) --}}
        <section class="acc-seccion" data-acc="5">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Fundamento jurídico</span>
            <span class="acc-sub">Normativa que da origen al trámite</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          {{-- Observaciones de esta sección (#18) --}}
          @include('partials.observaciones-seccion', [
            'seccion' => 'Fundamento jurídico',
            'items'   => $observacionesPorSeccion['Fundamento jurídico'] ?? collect(),
            'campos'  => $camposObservables['Fundamento jurídico'] ?? [],
          ])

          @php
            // Separar los fundamentos en: citas del catálogo (con regulacion_id)
            // y manual (sin regulacion_id). El modo se infiere del dato.
            $citasPrevias = $tramite->fundamentos->whereNotNull('regulacion_id')->values();
            $manualPrevio = $tramite->fundamentos->whereNull('regulacion_id')->first();
            $modoFund = $manualPrevio ? 'manual' : 'catalogo';
          @endphp

          <div class="wizard-fields">
            {{-- Modo: catálogo (vinculado) o manual (llenado libre) --}}
            <input type="hidden" name="fundamento_modo" id="fundamentoModo"
                   value="{{ old('fundamento_modo', $modoFund) }}">

            <label class="check-inline span-2" style="display:flex;align-items:center;gap:8px;font-size:14px">
              <input type="checkbox" id="fundamentoManualChk" onchange="toggleFundamentoManual()"
                     {{ old('fundamento_modo', $modoFund) === 'manual' ? 'checked' : '' }}>
              Esta normativa <strong>no está en el catálogo</strong> (llenado manual)
            </label>

            {{-- MODO CATÁLOGO: buscar y vincular la regulación de origen --}}
            <div id="fundamentoCatalogo" class="span-2"
                 style="{{ old('fundamento_modo', $modoFund) === 'manual' ? 'display:none' : '' }}">
              <x-citar-regulacion
                :citas="$citasPrevias"
                label="Normativa de origen (del catálogo)" />
            </div>

            {{-- MODO MANUAL: se escribe a mano; se descarta la vinculación --}}
            <div id="fundamentoManual" class="span-2"
                 style="{{ old('fundamento_modo', $modoFund) !== 'manual' ? 'display:none' : '' }}">
              <div class="field span-2"><label>Normativa de origen</label>
                <input name="fundamento_normativa"
                       value="{{ old('fundamento_normativa', $manualPrevio->normativa_nombre ?? '') }}"
                       placeholder="Ej. Reglamento de Comercio del Municipio de La Paz">
              </div>
              <div class="field"><label>Tipo de norma</label>
                <select name="fundamento_tipo">
                  <option value="">Seleccione...</option>
                  @foreach(['Reglamento','Lineamiento','Manual','Acuerdo','Ley'] as $tipo)
                    <option value="{{ $tipo }}"
                      {{ old('fundamento_tipo', $manualPrevio->tipo_normativa ?? '') === $tipo ? 'selected' : '' }}>
                      {{ $tipo }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="field"><label>Artículo y fracción</label>
                <input name="fundamento_articulo"
                       value="{{ old('fundamento_articulo', $manualPrevio->articulo_fraccion ?? '') }}"
                       placeholder="Ej. Artículo 45, Fracción II">
              </div>
              <div class="field span-2"><label>Resumen ciudadano del fundamento</label>
                <textarea name="fundamento_resumen" rows="3"
                  placeholder="Explique de forma simple por qué existe este trámite...">{{ old('fundamento_resumen', $manualPrevio->resumen ?? '') }}</textarea>
              </div>
            </div>
          </div>
          </div>
        </section>


        {{-- SECCIÓN: Procesos del trámite (atención y resolución por pasos) --}}
        <section class="acc-seccion" data-acc="proc">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Procesos del trámite</span>
            <span class="acc-sub">Atención y resolución, paso a paso</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
            <p class="u-muted" style="font-size:13px;margin-bottom:8px">Enumere el proceso paso a paso: quién lo realiza y en qué consiste. Use subpasos (1.1, 1.2) para detallar dentro de un paso.</p>
            <div id="pasosLista" class="pasos-lista">
              {{-- filas generadas por JS --}}
            </div>
            <div class="section-actions section-actions-start mt-3">
              <button type="button" class="btn btn-outline btn-sm" onclick="agregarPaso(false)">+ Agregar paso</button>
              <button type="button" class="btn btn-outline btn-sm" id="btnAgregarSubpaso" onclick="agregarPaso(true)">+ Agregar subpaso</button>
            </div>
            <input type="hidden" name="pasos_json" id="pasosJson"
              value="{{ old('pasos_json', $tramite->procesosAtencion->sortBy([['paso','asc'],['subpaso','asc']])->map(fn($p) => ['es_subpaso' => $p->subpaso > 0, 'area' => $p->area, 'accion' => $p->accion])->values()->toJson()) }}">
          </div>
        </section>

        {{-- SECCIÓN: Ficha para portal ciudadano --}}
        {{-- $ficha ahora llega del controlador (eager-loaded), ya no se
             resuelve aquí vía lazy-load implícito. --}}
        <section class="acc-seccion" data-acc="portal">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Ficha para portal ciudadano</span>
            <span class="acc-sub">Información visible para la ciudadanía</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
            <div class="wizard-fields">
              <div class="field"><label>Nombre ciudadano del trámite</label>
                <input name="portal_nombre_ciudadano" value="{{ old('portal_nombre_ciudadano', $ficha->nombre_ciudadano ?? '') }}" placeholder="Nombre ciudadano del trámite"></div>
              <div class="field"><label>Resultado que se obtiene</label>
                <input name="portal_resultado" value="{{ old('portal_resultado', $ficha->resultado ?? '') }}" placeholder="Ej. Licencia, constancia, permiso"></div>
              <div class="field"><label>Modalidad de atención</label>
                <select name="portal_modalidad" id="portalModalidad" onchange="toggleModalidadCampos()">
                  @php $modAct = old('portal_modalidad', $ficha->modalidad ?? 'Presencial'); @endphp
                  <option value="Presencial" {{ $modAct==='Presencial'?'selected':'' }}>Presencial</option>
                  <option value="En línea"   {{ $modAct==='En línea'?'selected':'' }}>En línea</option>
                  <option value="Mixta"      {{ $modAct==='Mixta'?'selected':'' }}>Mixta</option>
                </select>
              </div>
              <div id="modalidadDireccion" class="field span-2" style="display:none"><label>Dirección donde se realiza el trámite</label>
                <input name="portal_direccion" value="{{ old('portal_direccion', $ficha->direccion ?? '') }}" placeholder="Calle, número, colonia, La Paz, B.C.S."></div>
              <div id="modalidadUrl" class="field span-2" style="display:none"><label>URL donde se realiza el trámite en línea</label>
                <input name="portal_url" type="url" value="{{ old('portal_url', $ficha->url ?? '') }}" placeholder="https://tramites.lapaz.gob.mx/..."></div>
              <div class="field span-2"><label>Costo público (capturado en Operación)</label>
                <input type="text" id="costoPublicoResumen" readonly
                  value="{{ old('portal_costo_publico', $ficha->costo_publico ?? 'Gratuito') }}"
                  style="background:var(--surface-low);cursor:not-allowed">
                <small class="campo-nota">Para cambiarlo, regrese al paso de Operación.</small></div>
              <div class="field span-2"><label>Descripción accesible</label>
                <textarea name="portal_descripcion" rows="3" placeholder="Descripción accesible para la ciudadanía...">{{ old('portal_descripcion', $ficha->descripcion ?? '') }}</textarea></div>
              <div class="field span-2"><label>Horario de atención</label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input id="horarioResumen" name="portal_horario" readonly
                    placeholder="Haga clic en 'Configurar' para establecer horarios"
                    value="{{ old('portal_horario', $ficha->horario ?? '') }}"
                    style="background:#f9fafb;flex:1;cursor:pointer"
                    onclick="abrirHorarios()">
                  <input type="hidden" id="horariosJson" name="horarios_json" value="{{ old('horarios_json', $ficha?->horarios_json ? json_encode($ficha->horarios_json) : '') }}">
                  <button type="button" class="btn btn-outline btn-sm" onclick="abrirHorarios()" style="white-space:nowrap">Configurar</button>
                </div></div>
              <div class="field"><label>Homoclave pública</label>
                <input name="portal_homoclave_publica" value="{{ old('portal_homoclave_publica', $ficha->homoclave_publica ?? '') }}" placeholder="Homoclave visible al ciudadano"></div>
              <div class="field"><label>Documento que obtiene</label>
                <input name="portal_documento_obtiene" value="{{ old('portal_documento_obtiene', $ficha->documento_obtiene ?? '') }}" placeholder="Ej. Licencia, constancia, permiso"></div>
              <div class="field"><label>Canal principal de atención</label>
                <select name="portal_canal_principal">
                  <option value="">—</option>
                  @foreach(['Presencial', 'En línea', 'Telefónico', 'Mixto'] as $op)
                    <option value="{{ $op }}" {{ old('portal_canal_principal', $ficha->canal_principal ?? '') === $op ? 'selected' : '' }}>{{ $op }}</option>
                  @endforeach
                </select></div>
              <div class="field"><label>Medio de entrega</label>
                <select name="portal_medio_entrega">
                  <option value="">—</option>
                  @foreach(['Presencial', 'Correo electrónico', 'Mensajería', 'En línea'] as $op)
                    <option value="{{ $op }}" {{ old('portal_medio_entrega', $ficha->medio_entrega ?? '') === $op ? 'selected' : '' }}>{{ $op }}</option>
                  @endforeach
                </select></div>
              <div class="field"><label>Forma de pago</label>
                <select name="portal_forma_pago">
                  <option value="">—</option>
                  @foreach(['No aplica', 'Efectivo', 'Tarjeta', 'Transferencia', 'Línea de captura'] as $op)
                    <option value="{{ $op }}" {{ old('portal_forma_pago', $ficha->forma_pago ?? '') === $op ? 'selected' : '' }}>{{ $op }}</option>
                  @endforeach
                </select></div>
              <div class="field"><label>Vigencia del resultado</label>
                <input name="portal_vigencia" value="{{ old('portal_vigencia', $ficha->vigencia ?? '') }}" placeholder="Ej. 1 año, indefinida"></div>
              <div class="field"><label>Oficina de atención</label>
                <input name="portal_oficina" value="{{ old('portal_oficina', $ficha->oficina ?? '') }}" placeholder="Oficina o ventanilla"></div>
              <div class="field span-2"><label>Casos en que se realiza</label>
                <textarea name="portal_casos_realizarse" rows="2" placeholder="¿En qué situaciones se realiza este trámite?">{{ old('portal_casos_realizarse', $ficha->casos_realizarse ?? '') }}</textarea></div>
              <div class="field"><label>Teléfono</label>
                <input name="portal_telefono" value="{{ old('portal_telefono', $ficha->telefono ?? '') }}" placeholder="(612) 123-4567"></div>
              <div class="field"><label>Correo</label>
                <input name="portal_correo" type="email" value="{{ old('portal_correo', $ficha->correo ?? '') }}" placeholder="tramites@lapaz.gob.mx"></div>
            </div>
          </div>
        </section>

        {{-- BLOQUE FINAL: resumen y guardar (siempre visible, sin acordeón) --}}
        <div class="acc-final">
          @if($errors->any())
            <div class="toast toast-error u-toast-inline-top">
              <strong>Corrija:</strong>
              <ul style="margin:8px 0 0 16px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif
          <div class="info-card">
            <div class="modal-grid">
              <div class="modal-data-item"><span>Trámite</span><strong>{{ $tramite->nombre_oficial }}</strong></div>
              <div class="modal-data-item"><span>Estatus actual</span><strong>@estatus($tramite->estatus)</strong></div>
              <div class="modal-data-item"><span>Dependencia</span><strong>{{ $tramite->dependencia->nombre ?? '—' }}</strong></div>
              <div class="modal-data-item"><span>Última actualización</span><strong>{{ $tramite->updated_at->format('d/m/Y H:i') }}</strong></div>
            </div>
            <p class="u-muted" style="margin-top:12px">Al guardar se actualiza el registro y se registra en bitácora.</p>
          </div>
        </div>

        {{-- ACCIONES --}}
        <div class="card-actions card-actions-end">
          <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-outline">Cancelar</a>
          <button type="submit" class="btn btn-success">Guardar cambios</button>
        </div>

    </div>{{-- /acordeon-tramite --}}
  </form>

  {{-- Formularios de "Marcar como atendida", uno por observación viva.
       Van AQUÍ, fuera del <form> de edición, porque los forms anidados son
       inválidos en HTML. Cada botón de la sección los referencia por su id
       (form="obs-atendida-{id}"). --}}
  @foreach(($observacionesPorSeccion ?? collect())->flatten() as $obs)
    @unless($obs->estaResuelta())
      <form method="POST" action="{{ route('revision.atendida', $obs) }}"
        id="obs-atendida-{{ $obs->id }}" class="hidden">@csrf</form>
    @endunless
  @endforeach

  @include('partials.calculadora-digitalizacion')
    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion,
        'campos'                  => $camposObservables,
      ])
      @include('partials.timeline', ['tipo' => 'tramite', 'id' => $tramite->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}
  @endif

</div>
{{-- Fase F.4: Modal de horarios de atención --}}
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
<script>
// ─── Pasos para realizar el trámite (proceso único con subpasos 1.1) ────
var _pasos = [];
try { _pasos = JSON.parse(document.getElementById('pasosJson').value || '[]'); } catch (e) { _pasos = []; }

function numeroDePaso(indice) {
  var principal = 0, sub = 0;
  for (var k = 0; k <= indice; k++) {
    if (_pasos[k].es_subpaso) { sub++; } else { principal++; sub = 0; }
  }
  var p = _pasos[indice];
  return p.es_subpaso ? (principal + '.' + sub) : ('' + principal);
}

function renderPasos() {
  var cont = document.getElementById('pasosLista');
  if (!cont) return;
  cont.innerHTML = '';
  if (_pasos.length === 0) {
    cont.innerHTML = '<p class="derechos-vacio">Sin pasos. Use "Agregar paso" para enumerar el proceso.</p>';
  }
  _pasos.forEach(function (p, i) {
    var num = numeroDePaso(i);
    var etiqueta = p.es_subpaso ? ('Subpaso ' + num) : ('Paso ' + num);
    var art = document.createElement('article');
    art.className = 'requirement-card';
    // Sangría leve en subpasos para conservar la jerarquía visual (1.1 dentro de 1).
    if (p.es_subpaso) art.style.marginLeft = '28px';
    art.innerHTML =
      '<strong>' + etiqueta + '</strong>' +
      '<div class="wizard-fields">' +
        '<div class="field span-2"><label>¿Quién lo realiza? (área o responsable)</label>' +
          '<input type="text" placeholder="Ej. Ventanilla de Comercio" value="' + (p.area || '').replace(/"/g, '&quot;') + '" oninput="setPaso(' + i + ', \'area\', this.value)"></div>' +
        '<div class="field span-2"><label>¿En qué consiste este paso?</label>' +
          '<textarea rows="2" placeholder="Ej. Recibe la solicitud y verifica los documentos" oninput="setPaso(' + i + ', \'accion\', this.value)">' + (p.accion || '') + '</textarea></div>' +
      '</div>' +
      '<div class="section-actions mt-2">' +
        '<button type="button" class="btn btn-outline btn-sm danger" onclick="quitarPaso(' + i + ')">Quitar</button>' +
      '</div>';
    cont.appendChild(art);
  });
  document.getElementById('pasosJson').value = JSON.stringify(_pasos);
  actualizarBotonSubpaso();
}

// Habilita o deshabilita el botón "+ Agregar subpaso": un subpaso (1.1, 1.2)
// solo tiene sentido dentro de un paso principal ya existente.
function actualizarBotonSubpaso() {
  var btn = document.getElementById('btnAgregarSubpaso');
  if (!btn) return;
  var hayPasoPrincipal = _pasos.some(function (p) { return !p.es_subpaso; });
  btn.disabled = !hayPasoPrincipal;
  btn.title = hayPasoPrincipal ? '' : 'Primero agregue un paso';
}

function agregarPaso(esSubpaso) {
  // No se permite un subpaso si todavía no hay ningún paso principal.
  if (esSubpaso && !_pasos.some(function (p) { return !p.es_subpaso; })) {
    return;
  }
  _pasos.push({ es_subpaso: !!esSubpaso, area: '', accion: '' });
  renderPasos();
}
function setPaso(i, campo, valor) {
  if (_pasos[i]) { _pasos[i][campo] = valor; document.getElementById('pasosJson').value = JSON.stringify(_pasos); }
}
function quitarPaso(i) {
  _pasos.splice(i, 1);
  renderPasos();
}
document.addEventListener('DOMContentLoaded', renderPasos);

// Muestra el campo de dirección y/o URL según la modalidad elegida (igual que en el alta).
function toggleModalidadCampos() {
  var sel = document.getElementById('portalModalidad');
  var dir = document.getElementById('modalidadDireccion');
  var url = document.getElementById('modalidadUrl');
  if (!sel) return;
  var v = sel.value;
  if (dir) dir.style.display = (v === 'Presencial' || v === 'Mixta') ? '' : 'none';
  if (url) url.style.display = (v === 'En línea'   || v === 'Mixta') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleModalidadCampos);

// Ítem A: mostrar campo de trámites relacionados solo cuando se elige "Sí"
// (rubro 10.2 del instrumento ATDT). El campo cambió de un <select> a radios
// Art. 29, fracción VI LNETB: si el tipo de relación es distinto de
// "Ninguna", mostrar el detalle de trámites relacionados.
function toggleRelacionados() {
  var sel = document.querySelector('select[name="tipo_relacion"]');
  var wrap = document.getElementById('relacionadosDetalleWrap');
  if (!wrap) return;
  wrap.style.display = (sel && sel.value !== 'Ninguna' && sel.value !== '') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  toggleRelacionados();
});

// Alterna entre modo catálogo (citas vinculadas) y modo manual (texto libre)
// para el fundamento jurídico del trámite. Mismo comportamiento que en create.
function toggleFundamentoManual() {
  var chk = document.getElementById('fundamentoManualChk');
  var modo = document.getElementById('fundamentoModo');
  var cat = document.getElementById('fundamentoCatalogo');
  var man = document.getElementById('fundamentoManual');
  if (!chk || !modo) return;
  var esManual = chk.checked;
  modo.value = esManual ? 'manual' : 'catalogo';
  if (cat) cat.style.display = esManual ? 'none' : '';
  if (man) man.style.display = esManual ? '' : 'none';
}

function toggleAreasParticipantes() {
  var n = document.getElementById('numAreas');
  var wrap = document.getElementById('areasParticipantesWrap');
  if (!n || !wrap) return;
  wrap.style.display = (parseInt(n.value, 10) > 0) ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  var n = document.getElementById('numAreas');
  if (n) n.addEventListener('input', toggleAreasParticipantes);
  toggleAreasParticipantes();
});
</script>
@endpush

@push('scripts')
<script>
// Pago de derechos: lista dinámica (idéntica a la de creación).
var _derechos = [];

var VALOR_UMA = {{ \App\Models\TramiteDerecho::valorUmaVigente() ?: 0 }};

// Costo público: muestra monto y selector UMA/Pesos solo si es "Con precio".
// El monto que se guarda (costo_monto / portal_costo) va siempre en PESOS.
function actualizarCosto() {
  var tipo   = document.getElementById('costoTipo');
  var monto  = document.getElementById('costoMonto');
  var unidad = document.getElementById('costoUnidad');
  var equiv  = document.getElementById('costoEquiv');
  if (!tipo || !monto || !unidad) return;

  var esGratuito = tipo.value === 'gratuito';
  monto.style.display  = esGratuito ? 'none' : '';
  unidad.style.display = esGratuito ? 'none' : '';
  if (esGratuito) { monto.value = 0; if (equiv) equiv.textContent = ''; }

  var valorCapturado = parseFloat(monto.value) || 0;
  var esUma = unidad.value === 'UMA';
  var valorPesos = esUma ? (valorCapturado * VALOR_UMA) : valorCapturado;

  if (equiv) equiv.textContent = esGratuito ? '' : (esUma ? ('≈ $' + valorPesos.toFixed(2) + ' MXN') : 'MXN');

  document.getElementById('costoTexto').value        = esGratuito ? 'Gratuito' : ('$' + valorPesos.toFixed(2) + ' MXN');
  document.getElementById('costoTipoHidden').value   = tipo.value;
  document.getElementById('costoMontoHidden').value  = valorPesos;
  document.getElementById('costoUnidadHidden').value = esGratuito ? 'pesos' : unidad.value;
}
document.addEventListener('DOMContentLoaded', actualizarCosto);

// Monto de un derecho en pesos (convierte si está en UMA).
function derechoEnPesos(d) {
  var m = parseFloat(d.monto) || 0;
  return (d.unidad === 'UMA') ? m * VALOR_UMA : m;
}

function renderDerechos() {
  var cont = document.getElementById('derechosLista');
  if (!cont) return;
  cont.innerHTML = '';

  if (_derechos.length === 0) {
    cont.innerHTML = '<p class="derechos-vacio">Sin conceptos de derechos. Si el trámite no cobra derechos, déjalo vacío.</p>';
  }

  _derechos.forEach(function (d, i) {
    var wrap = document.createElement('div');
    wrap.className = 'derecho-wrap';
    var esUma = d.unidad === 'UMA';
    var equiv = esUma ? (' ≈ $' + derechoEnPesos(d).toFixed(2)) : '';
    var tieneFj = !!(d.fj_norma || d.fj_capitulo || d.fj_articulo);
    wrap.innerHTML =
      '<div class="derecho-fila">' +
        '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)">' +
        '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)">' +
        '<select onchange="setDerecho(' + i + ', \'unidad\', this.value)">' +
          '<option value="pesos"' + (esUma ? '' : ' selected') + '>Pesos</option>' +
          '<option value="UMA"' + (esUma ? ' selected' : '') + '>UMA</option>' +
        '</select>' +
        '<label><input type="checkbox"' + (d.es_variable ? ' checked' : '') + ' onchange="setDerecho(' + i + ', \'es_variable\', this.checked)"> Variable</label>' +
        '<span class="derecho-equiv">' + equiv + '</span>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>' +
      '</div>' +
      '<div class="fj-bloque" style="margin-top:6px">' +
        '<label class="fj-pregunta">¿Este derecho tiene fundamento jurídico? *</label>' +
        '<div class="fj-radios">' +
          '<label><input type="radio" name="der_fj_' + i + '" value="1"' + (tieneFj ? ' checked' : '') + ' onchange="setDerechoFj(' + i + ', true); toggleFjRadio(this)"> Sí</label>' +
          '<label><input type="radio" name="der_fj_' + i + '" value="0"' + (tieneFj ? '' : ' checked') + ' onchange="setDerechoFj(' + i + ', false); toggleFjRadio(this)"> No</label>' +
        '</div>' +
        '<div class="fj-campos fj-linea" style="display:' + (tieneFj ? '' : 'none') + '">' +
          '<div><label>Ley o reglamento</label><input value="' + (d.fj_norma || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_norma\', this.value)" placeholder="Ej. Ley de Hacienda"></div>' +
          '<div><label>Capítulo</label><input value="' + (d.fj_capitulo || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_capitulo\', this.value)" placeholder="Ej. Cap. II"></div>' +
          '<div><label>Artículo</label><input value="' + (d.fj_articulo || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_articulo\', this.value)" placeholder="Ej. Art. 45"></div>' +
        '</div>' +
      '</div>';
    cont.appendChild(wrap);
  });

  var total = _derechos.reduce(function (s, d) { return s + derechoEnPesos(d); }, 0);
  document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
  document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  sincronizarMontoDerechos(total);
}

function sincronizarMontoDerechos(total) {
  var campo = document.getElementById('montoDerechosCalc');
  if (campo) campo.value = total.toFixed(2);
}

function agregarDerecho() {
  _derechos.push({ concepto: '', monto: 0 });
  renderDerechos();
}

function quitarDerecho(i) {
  _derechos.splice(i, 1);
  renderDerechos();
  toggleMontoReferencia();
}

function setDerecho(i, campo, valor) {
  if (_derechos[i]) {
    _derechos[i][campo] = campo === 'monto' ? (parseFloat(valor) || 0) : valor;
    // Cambiar la unidad re-renderiza para refrescar la equivalencia en pesos.
    if (campo === 'unidad') {
      renderDerechos();
      return;
    }
    var total = _derechos.reduce(function (s, d) { return s + derechoEnPesos(d); }, 0);
    document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
    sincronizarMontoDerechos(total);
    if (campo === 'es_variable') toggleMontoReferencia();
  }
}

// Cuando el derecho elige "No tiene fundamento", limpia los 3 campos del JSON.
function setDerechoFj(i, tiene) {
  if (_derechos[i] && !tiene) {
    _derechos[i].fj_norma = '';
    _derechos[i].fj_capitulo = '';
    _derechos[i].fj_articulo = '';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  }
}

// Muestra u oculta los campos de fundamento de un bloque según el radio Sí/No.
// La usan el costo, los requisitos y los derechos (misma estructura .fj-bloque).
// Ítem F: la etapa de operación solo aplica a personas morales.
function toggleEtapaOperacion() {
  var sel  = document.querySelector('select[name="dirigido_a"]');
  var wrap = document.getElementById('etapaOperacionWrap');
  if (!sel || !wrap) return;
  wrap.style.display = (sel.value === 'moral' || sel.value === 'ambas') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  var sel = document.querySelector('select[name="dirigido_a"]');
  if (sel) sel.addEventListener('change', toggleEtapaOperacion);
  toggleEtapaOperacion();
});

// Ítem E: muestra el campo de monto solo cuando el requisito tiene costo fijo.
function toggleCostoReq(sel) {
  var card = sel.closest('article.requirement-card');
  if (!card) return;
  var montoWrap = card.querySelector('.req-costo-monto');
  if (montoWrap) montoWrap.style.display = (sel.value === 'fijo') ? '' : 'none';
}
// Ítem E: muestra la base de cálculo solo cuando el pago de derechos es variable.
// Bug #B11: también oculta el campo "Monto de derechos" y muestra un aviso
// explicativo cuando es variable (para evitar el confuso $0.00).
function toggleMontoReferencia() {
  var hayVariable = _derechos.some(function (d) { return d.es_variable; });
  var hidden   = document.getElementById('montoVariableChk');
  var wrap     = document.getElementById('montoReferenciaWrap');
  var fijo     = document.getElementById('montoDerechosFijoWrap');
  var aviso    = document.getElementById('montoDerechosVariableAviso');
  if (hidden) hidden.value = hayVariable ? '1' : '0';
  if (wrap)   wrap.style.display  = hayVariable ? '' : 'none';
  if (fijo)   fijo.style.display  = hayVariable ? 'none' : '';
  if (aviso)  aviso.style.display = hayVariable ? '' : 'none';
}

function toggleFjRadio(radio) {
  var bloque = radio.closest('.fj-bloque');
  if (!bloque) return;
  var campos = bloque.querySelector('.fj-campos');
  if (campos) campos.style.display = (radio.value === '1') ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
  try {
    var inicial = JSON.parse(document.getElementById('derechosJson').value || '[]');
    if (Array.isArray(inicial)) _derechos = inicial;
  } catch (e) { _derechos = []; }
  renderDerechos();
  // Bug 51: reflejar al cargar el estado del costo variable (checkbox
  // monto_derechos_variable). Sin esto, al editar un trámite que ya tiene
  // costo variable, el campo de monto/UMA queda oculto hasta tocar el
  // checkbox. La función ya existía; solo faltaba invocarla en la carga.
  toggleMontoReferencia();
});
</script>
@endpush

@push('scripts')
<script>
(function () {

  /**
   * Previsualiza la homoclave en vivo a partir de la dependencia (fija
   * del perfil) y la unidad administrativa seleccionada.
   */
  (function previsualizarHomoclave() {
    var depInput   = document.querySelector('input[name="dependencia_id"]');
    var unidadEl   = document.querySelector('[name="unidad_id"]');
    var homoclave  = document.getElementById('homoclave_input');
    if (!depInput || !unidadEl || !homoclave) return;

    function actualizar() {
      var depId = depInput.value;
      var uniId = unidadEl.value;
      if (!depId || !uniId) return;
      fetch('/api/homoclave/previsualizar?dependencia_id=' + depId + '&unidad_id=' + uniId, {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          // Solo rellena si el campo está vacío, para no pisar una homoclave ya asignada.
          if (data && data.homoclave && !homoclave.value) {
            homoclave.value = data.homoclave;
          }
        })
        .catch(function () { /* silencioso */ });
    }

    if (unidadEl.tagName === 'SELECT') {
      unidadEl.addEventListener('change', actualizar);
    }
    actualizar();
  })();

  window.toggleAcc = function (boton) {
    var seccion = boton.closest('.acc-seccion');
    if (seccion) seccion.classList.toggle('abierta');
  };

  var reqIdx = {{ $tramite->requisitos->count() }};
  window.addReq = function () {
    var i = reqIdx++;
    var a = document.createElement('article');
    a.className = 'requirement-card';
    a.innerHTML = '<strong>Requisito '+(i+1)+'</strong><div class="wizard-fields">'
      +'<div class="field"><label>Nombre</label><input name="requisitos['+i+'][nombre]" placeholder="Nombre del requisito"></div>'
      +'<div class="field"><label>Tipo de presentación</label><div class="tipo-pres-checks">'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="original"> Original</label>'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="copia"> Copia</label>'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="digital"> Digital</label>'
      +'</div></div>'
      +'<div class="field"><label>Días</label><input name="requisitos['+i+'][dias]" type="number" min="0" value="0"></div>'
      +'<div class="field"><label>Horas</label><input name="requisitos['+i+'][horas]" type="number" min="0" value="0"></div>'
      +'<div class="field"><label>Minutos</label><input name="requisitos['+i+'][minutos]" type="number" min="0" max="59" value="0"></div>'
      +'<div class="field span-2"><label>Observaciones</label><textarea name="requisitos['+i+'][observaciones]" rows="2"></textarea></div>'
      +'<div class="field"><label>¿Este requisito tiene costo?</label>'
      +'<select name="requisitos['+i+'][costo_modo]" onchange="toggleCostoReq(this)">'
      +'<option value="sin">Sin costo</option>'
      +'<option value="fijo">Sí, monto fijo</option>'
      +'<option value="variable">Sí, costo variable (no cuantificable)</option>'
      +'</select></div>'
      +'<div class="field req-costo-monto" style="display:none"><label>Monto del requisito (pesos)</label>'
      +'<input name="requisitos['+i+'][costo_monto]" type="number" min="0" step="0.01" value="0" placeholder="Ej. 250.00"></div>'
      +'<div class="field span-2 fj-bloque">'
      +'<label class="fj-pregunta">¿Este requisito tiene fundamento jurídico? *</label>'
      +'<div class="fj-radios">'
      +'<label><input type="radio" name="requisitos['+i+'][fj_tiene]" value="1" required onchange="toggleFjRadio(this)"> Sí</label>'
      +'<label><input type="radio" name="requisitos['+i+'][fj_tiene]" value="0" required onchange="toggleFjRadio(this)"> No</label>'
      +'</div>'
      +'<div class="fj-campos fj-linea" style="display:none">'
      +'<div><label>Ley o reglamento</label><input name="requisitos['+i+'][fj_norma]" placeholder="Ej. Reglamento de Comercio"></div>'
      +'<div><label>Capítulo</label><input name="requisitos['+i+'][fj_capitulo]" placeholder="Ej. Cap. III"></div>'
      +'<div><label>Artículo</label><input name="requisitos['+i+'][fj_articulo]" placeholder="Ej. Art. 12"></div>'
      +'</div></div>'
      +'</div>'
      +'<div class="section-actions mt-2"><button type="button" class="btn btn-outline btn-sm danger" onclick="this.closest(\'article\').remove()">Eliminar</button></div>';
    document.getElementById('reqContainer').appendChild(a);
  };

  // ─── Fase F.4: Horarios de atención ────────────────────────────
  var DIAS_H = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  var H_INI = '09:00', H_FIN = '15:00';
  var horariosData = (function () {
    var el = document.getElementById('horariosJson');
    if (!el || !el.value) return {};
    try { return JSON.parse(el.value); } catch(e) { return {}; }
  })();

  // Horario base: si ya hay días guardados, toma el del primero; si no, el default.
  var horarioBase = (function () {
    var primero = DIAS_H.find(function (d) { return horariosData[d] && horariosData[d].activo; });
    if (primero) return { inicio: horariosData[primero].inicio, fin: horariosData[primero].fin };
    return { inicio: H_INI, fin: H_FIN };
  })();

  function setHorarioBase(campo, valor) { horarioBase[campo] = valor; }

  function toggleDiaChip(dia) {
    if (horariosData[dia] && horariosData[dia].activo) {
      horariosData[dia].activo = false;
    } else {
      horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    }
    renderHorariosUI();
  }

  function horarioPreset(t) {
    var dias = t==='lv' ? DIAS_H.slice(0,5) : t==='ls' ? DIAS_H.slice(0,6) : DIAS_H;
    DIAS_H.forEach(function (d) {
      if (dias.includes(d)) {
        horariosData[d] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
      } else if (horariosData[d]) {
        horariosData[d].activo = false;
      }
    });
    renderHorariosUI();
  }

  function horarioLimpiar() { horariosData = {}; renderHorariosUI(); }

  function updateHoraDia(dia, campo, val) {
    if (!horariosData[dia]) horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    horariosData[dia][campo] = val;
  }

  function renderHorariosUI() {
    var baseIni = document.getElementById('horarioBaseInicio');
    var baseFin = document.getElementById('horarioBaseFin');
    if (baseIni) baseIni.value = horarioBase.inicio;
    if (baseFin) baseFin.value = horarioBase.fin;

    var chips = document.getElementById('horarioChips');
    if (chips) {
      chips.innerHTML = DIAS_H.map(function (dia) {
        var act = horariosData[dia] && horariosData[dia].activo;
        return '<button type="button" class="horario-chip' + (act ? ' activo' : '') + '" ' +
               'onclick="toggleDiaChip(\'' + dia + '\')">' + dia.substring(0,3) + '</button>';
      }).join('');
    }

    var preview = document.getElementById('horarioPreview');
    if (preview) {
      var activos = DIAS_H.filter(function (d) { return horariosData[d] && horariosData[d].activo; });
      if (!activos.length) {
        preview.innerHTML = '<p class="horario-preview-vacio">Marque al menos un día arriba para ver la vista previa.</p>';
      } else {
        preview.innerHTML = activos.map(function (dia) {
          var h = horariosData[dia];
          return '<div class="horario-preview-row">' +
                   '<span class="horario-preview-dia">' + dia + '</span>' +
                   '<input type="time" value="' + h.inicio + '" class="u-text-sm" ' +
                     'onchange="updateHoraDia(\'' + dia + '\',\'inicio\',this.value)">' +
                   '<span class="horario-preview-sep">–</span>' +
                   '<input type="time" value="' + h.fin + '" class="u-text-sm" ' +
                     'onchange="updateHoraDia(\'' + dia + '\',\'fin\',this.value)">' +
                 '</div>';
        }).join('');
      }
    }
  }

  function guardarHorarios() {
    var activos = DIAS_H.filter(function (d) { return horariosData[d] && horariosData[d].activo; });
    var jsonEl = document.getElementById('horariosJson');
    var resEl  = document.getElementById('horarioResumen');
    if (jsonEl) jsonEl.value = JSON.stringify(horariosData);
    if (resEl) {
      var res = '';
      if (activos.length === 7) {
        res = 'Lun–Dom ' + horariosData[activos[0]].inicio + '–' + horariosData[activos[0]].fin + ' hrs';
      } else if (activos.length === 5 && JSON.stringify(activos) === JSON.stringify(DIAS_H.slice(0,5))) {
        res = 'Lun–Vie ' + horariosData['Lunes'].inicio + '–' + horariosData['Lunes'].fin + ' hrs';
      } else if (activos.length > 0) {
        res = activos.map(function(d){ return d.substring(0,3)+' '+horariosData[d].inicio+'–'+horariosData[d].fin; }).join(', ');
      }
      resEl.value = res;
    }
    cerrarHorarios();
  }
  function abrirHorarios()  { renderHorariosUI(); document.getElementById('modalHorarios').classList.add('open'); }
  function cerrarHorarios() { document.getElementById('modalHorarios').classList.remove('open'); }

  // Bug 65: auto-abrir secciones del acordeón que tienen observaciones.
  // Busca los bloques .obs-aviso (renderizados por el partial) y abre
  // su sección padre para que el enlace vea qué debe corregir sin tener
  // que abrir cada acordeón manualmente.
  document.querySelectorAll('.obs-aviso').forEach(function (aviso) {
    var seccion = aviso.closest('.acc-seccion');
    if (seccion && !seccion.classList.contains('abierta')) {
      seccion.classList.add('abierta');
    }
  });

})();
</script>

{{-- Selector de naturaleza Trámite / Servicio.
     Fuera del IIFE anterior para que los onclick="elegirNaturaleza(...)"
     de las cards del paso 1 puedan encontrar la función en scope global.
     Si estuviera dentro del IIFE, el scope cerrado la haría inaccesible
     y el navegador lanzaría ReferenceError. --}}
<script>
function elegirNaturaleza(tipo) {
  var hidden = document.getElementById('naturalezaHidden');
  var optT   = document.getElementById('optTramite');
  var optS   = document.getElementById('optServicio');
  var campoT = document.getElementById('campoTipoTramite');
  var campoS = document.getElementById('campoTipoServicio');

  hidden.value = tipo;
  optT.classList.toggle('sel', tipo === 'tramite');
  optS.classList.toggle('sel', tipo === 'servicio');
  campoT.style.display = tipo === 'tramite'  ? '' : 'none';
  campoS.style.display = tipo === 'servicio' ? '' : 'none';
  if (tipo === 'tramite')  document.getElementById('tipo_servicio').value = '';
  if (tipo === 'servicio') document.getElementById('tipo_tramite_id').value = '';

  // Reemplazo masivo: misma lógica que en create.blade.php, con exclusión
  // de las tarjetas selectoras (bug #46: sin esta exclusión, la tarjeta NO
  // seleccionada terminaba mostrando el título de la seleccionada, ej.
  // ambas tarjetas decían "Servicio").
  var pares = [
    ['del trámite',  'del servicio'],
    ['Del trámite',  'Del servicio'],
    ['al trámite',   'al servicio'],
    ['el trámite',   'el servicio'],
    ['El trámite',   'El servicio'],
    ['un trámite',   'un servicio'],
    ['trámites',     'servicios'],
    ['Trámites',     'Servicios'],
    ['trámite',      'servicio'],
    ['Trámite',      'Servicio'],
  ];
  var wizard = document.querySelector('.wizard-shell') || document.querySelector('.page-body') || document.body;
  var walker = document.createTreeWalker(wizard, NodeFilter.SHOW_TEXT, {
    acceptNode: function (nodo) {
      if (nodo.parentElement && nodo.parentElement.closest('[data-nat-fijo]')) {
        return NodeFilter.FILTER_REJECT;
      }
      return NodeFilter.FILTER_ACCEPT;
    }
  }, false);
  var nodo;
  while (nodo = walker.nextNode()) {
    var original = nodo.nodeValue;
    if (!original || !original.trim()) continue;
    if (!/tr[aá]mite|servicio/i.test(original)) continue;
    var nuevo = original;
    pares.forEach(function (par) {
      var desde = tipo === 'servicio' ? par[0] : par[1];
      var hacia = tipo === 'servicio' ? par[1] : par[0];
      nuevo = nuevo.split(desde).join(hacia);
    });
    if (nuevo !== original) nodo.nodeValue = nuevo;
  }
}

// Si al cargar la página el registro es un servicio, reemplazar los textos
// inmediatamente para que el enlace no vea "Objetivo del trámite" cuando
// está editando un servicio.
(function () {
  var nat = document.getElementById('naturalezaHidden');
  if (nat && nat.value === 'servicio') {
    // Esperar a que el DOM esté completo para que el TreeWalker encuentre todos los nodos
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { elegirNaturaleza('servicio'); });
    } else {
      elegirNaturaleza('servicio');
    }
  }
})();
</script>
@endpush