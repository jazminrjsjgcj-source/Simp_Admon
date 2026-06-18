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
  <form method="POST" action="{{ route('tramites.update', $tramite) }}" id="editForm">
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

            <div class="field span-2">
              <label for="nombre_oficial">Nombre oficial *</label>
              <input id="nombre_oficial" required name="nombre_oficial" type="text"
                     maxlength="500"
                     value="{{ old('nombre_oficial', $tramite->nombre_oficial) }}"
                     placeholder="Nombre oficial del trámite">
            </div>

            {{-- Tipo de trámite desde catálogo --}}
            <div class="field">
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

            {{-- Dependencia: la del trámite (no se puede cambiar desde aquí) --}}
            <input type="hidden" name="dependencia_id" value="{{ $tramite->dependencia_id }}">
            <div class="field">
              <label>Dependencia</label>
              <input type="text" value="{{ $tramite->dependencia->nombre ?? '—' }}" disabled class="u-input-disabled">
              <small class="help-small">Asignada al momento de crear el trámite.</small>
            </div>

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

            {{-- Sujeto Obligado y Enlace del trámite (se conservan los del registro) --}}
            @php
              $sujetoObligado = $tramite->sujeto_obligado_id
                  ? \App\Models\SujetoObligado::find($tramite->sujeto_obligado_id)
                  : \App\Models\SujetoObligado::vigenteDe($tramite->dependencia_id);
              $enlaceTramite = $tramite->enlace_id ? \App\Models\User::find($tramite->enlace_id) : null;
            @endphp
            <div class="field">
              <label>Sujeto Obligado</label>
              <input type="text" value="{{ $sujetoObligado?->nombre ?? 'Sin titular registrado' }}" disabled class="u-input-disabled">
              <input type="hidden" name="sujeto_obligado_id" value="{{ old('sujeto_obligado_id', $tramite->sujeto_obligado_id ?? $sujetoObligado?->id) }}">
            </div>

            <div class="field">
              <label>Enlace</label>
              <input type="text" value="{{ $enlaceTramite?->name ?? auth()->user()->name }}" disabled class="u-input-disabled">
              <input type="hidden" name="enlace_id" value="{{ old('enlace_id', $tramite->enlace_id ?? auth()->id()) }}">
              <small class="help-small">Persona que registra el trámite.</small>
            </div>

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
            <div class="field span-2">
              <label>Objetivo del trámite *</label>
              <textarea required name="objetivo" rows="4" placeholder="Describa qué resuelve o qué beneficio otorga...">{{ old('objetivo', $tramite->objetivo) }}</textarea>
            </div>
            <div class="field">
              <label>Dirigido a</label>
              <select name="dirigido_a">
                <option value="ambas"  {{ $tramite->dirigido_a === 'ambas'  ? 'selected' : '' }}>Ambas</option>
                <option value="fisica" {{ $tramite->dirigido_a === 'fisica' ? 'selected' : '' }}>Personas físicas</option>
                <option value="moral"  {{ $tramite->dirigido_a === 'moral'  ? 'selected' : '' }}>Personas morales</option>
              </select>
            </div>
            <div class="field">
              <label>Frecuencia</label>
              <select name="frecuencia">
                @foreach(['Alta','Media','Baja','Eventual'] as $f)
                  <option {{ $tramite->frecuencia === $f ? 'selected' : '' }}>{{ $f }}</option>
                @endforeach
              </select>
            </div>

            <div class="field">
              <label>Tipo de relación con otros trámites</label>
              <select name="tipo_relacion">
                @foreach(['' => '— Sin relación / no aplica —', 'Naturaleza' => 'Naturaleza', 'Secuencia' => 'Secuencia', 'Dependencia funcional' => 'Dependencia funcional'] as $valor => $etiqueta)
                  <option value="{{ $valor }}" {{ old('tipo_relacion', $tramite->tipo_relacion) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
              </select>
              <small class="help-small">Rubro 10.1: cómo se relaciona este trámite con otros, si aplica.</small>
            </div>
            {{-- Sector y subsector económico SCIAN --}}
            <x-selector-scian :sector="old('sector_id', $tramite->sector_id)" :subsector="old('subsector_id', $tramite->subsector_id)" />

            <x-input-validado tipo="numero_entero" name="volumen_anual" label="Volumen anual estimado" min="0" placeholder="Ej. 1250" :value="old('volumen_anual', $tramite->volumen_anual)" />
            <div class="field">
              <label>Plazo máximo de resolución</label>
              <div class="split-fields">
                <input name="plazo_resolucion_cantidad" type="number" min="0" inputmode="numeric" value="{{ old('plazo_resolucion_cantidad', $tramite->plazo_resolucion_cantidad) }}" placeholder="Cantidad">
                <select name="plazo_resolucion_unidad">
                  <option value="habiles"   {{ ($tramite->plazo_resolucion_unidad ?? 'habiles') === 'habiles'   ? 'selected' : '' }}>Días hábiles</option>
                  <option value="naturales" {{ $tramite->plazo_resolucion_unidad === 'naturales' ? 'selected' : '' }}>Días naturales</option>
                  <option value="meses"     {{ $tramite->plazo_resolucion_unidad === 'meses'     ? 'selected' : '' }}>Meses</option>
                </select>
              </div>
            </div>
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

          <div class="wizard-fields">
            <div class="field">
              <label>Número de áreas que participan</label>
              <input name="num_areas" type="number" min="0" value="{{ old('num_areas', $tramite->num_areas) }}">
            </div>
            <div class="field">
              <label>Áreas que participan</label>
              <input name="areas_participantes" value="{{ old('areas_participantes', $tramite->areas_participantes) }}" placeholder="Ej. Ventanilla, Tesorería">
            </div>
            <div class="field">
              <label>Visitas requeridas</label>
              <input name="visitas_requeridas" type="number" min="0" value="{{ old('visitas_requeridas', $tramite->visitas_requeridas) }}">
            </div>
            <x-field-help label="Costo público">
              <div class="costo-grupo">
                <select id="costoTipo" onchange="actualizarCosto()">
                  @php $costoActual = old('portal_costo', $ficha->costo_publico ?? 'Gratuito'); $esConCosto = $costoActual !== 'Gratuito' && $costoActual !== ''; @endphp
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
              <input type="hidden" name="portal_costo" id="costoTexto" value="{{ $costoActual }}">
              <input type="hidden" name="costo_tipo" id="costoTipoHidden" value="{{ $esConCosto ? 'con_costo' : 'gratuito' }}">
              <input type="hidden" name="costo_monto" id="costoMontoHidden" value="0">
              <input type="hidden" name="costo_unidad" id="costoUnidadHidden" value="pesos">
            </x-field-help>
            <x-field-help label="Pago de derechos" class="span-2">
              <div id="derechosLista" class="derechos-lista">
                {{-- filas generadas por JS --}}
              </div>
              <div class="derechos-pie">
                <button type="button" class="btn btn-outline btn-sm" onclick="agregarDerecho()">+ Agregar derecho</button>
                <span class="derechos-total">Total derechos: <strong id="derechosTotal">$0.00 MXN</strong></span>
              </div>
              <input type="hidden" name="derechos_json" id="derechosJson"
                value="{{ old('derechos_json', $tramite->derechos->map(fn($d) => ['concepto' => $d->concepto, 'monto' => (float) $d->monto, 'unidad' => $d->unidad ?? 'pesos', 'es_variable' => (bool) ($d->es_variable ?? false)])->toJson()) }}">
            </x-field-help>
            <div class="field span-2">
              <label>Monto de derechos (MD) en pesos</label>
              <input id="montoDerechosCalc" name="monto_derechos_display" type="number" readonly
                value="{{ old('monto_derechos', $tramite->monto_derechos) }}"
                style="background:var(--surface-low);cursor:not-allowed">
              <small class="campo-nota">Resultado del total de "Pago de derechos" (convertido a pesos).</small>
            </div>
            <div class="field">
              <label>Copias simples solicitadas</label>
              <input name="copias_cantidad" type="number" min="0" value="{{ old('copias_cantidad', $tramite->copias_cantidad) }}">
            </div>
            <div class="field">
              <label>Precio por copia (pesos)</label>
              <input name="copias_precio" type="number" min="0" step="0.01" value="{{ old('copias_precio', $tramite->copias_precio ?? 1.50) }}">
            </div>
            <div class="field">
              <label>Salario promedio por hora (W)</label>
              <input name="salario_hora_w" type="number" min="0" step="0.01" value="{{ old('salario_hora_w', $tramite->salario_hora_w ?? 68.20) }}">
            </div>
            <div class="field">
              <label>Nivel de digitalización</label>
              <select name="nivel_digitalizacion">
                @for($i = 1; $i <= 5; $i++)
                  <option value="{{ $i }}" {{ $tramite->nivel_digitalizacion == $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
              </select>
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

        {{-- SECCIÓN 4: Requisitos --}}
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
          <div id="reqContainer">
            @forelse($tramite->requisitos as $i => $req)
              <article class="requirement-card">
                <strong>Requisito {{ $i + 1 }}: {{ $req->nombre }}</strong>
                <div class="wizard-fields">
                  <div class="field"><label>Nombre *</label><input name="requisitos[{{ $i }}][nombre]" value="{{ $req->nombre }}" required></div>
                  <div class="field"><label>¿Original?</label>
                    <select name="requisitos[{{ $i }}][original]">
                      <option value="1" {{ $req->original ? 'selected' : '' }}>Sí</option>
                      <option value="0" {{ !$req->original ? 'selected' : '' }}>No</option>
                    </select>
                  </div>
                  <div class="field"><label>¿Copia?</label>
                    <select name="requisitos[{{ $i }}][copia]">
                      <option value="1" {{ $req->copia ? 'selected' : '' }}>Sí</option>
                      <option value="0" {{ !$req->copia ? 'selected' : '' }}>No</option>
                    </select>
                  </div>
                  <div class="field"><label>Días estimados</label><input name="requisitos[{{ $i }}][dias]" type="number" min="0" value="{{ $req->dias_estimados }}"></div>
                  <div class="field"><label>Horas estimadas</label><input name="requisitos[{{ $i }}][horas]" type="number" min="0" value="{{ $req->horas_estimadas }}"></div>
                  <div class="field span-2"><label>Observaciones</label><textarea name="requisitos[{{ $i }}][observaciones]" rows="2">{{ $req->observaciones }}</textarea></div>
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
              <button type="button" class="btn btn-outline btn-sm" onclick="agregarPaso(true)">+ Agregar subpaso</button>
            </div>
            <input type="hidden" name="pasos_json" id="pasosJson"
              value="{{ old('pasos_json', $tramite->procesosAtencion->sortBy([['paso','asc'],['subpaso','asc']])->map(fn($p) => ['es_subpaso' => $p->subpaso > 0, 'area' => $p->area, 'accion' => $p->accion])->values()->toJson()) }}">
          </div>
        </section>

        {{-- SECCIÓN 5: Fundamento jurídico --}}
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
          @forelse($tramite->fundamentos as $f)
            <div class="wizard-fields">
              <div class="field span-2"><label>Normativa</label><input name="fundamento_normativa" value="{{ $f->normativa_nombre }}"></div>
              <div class="field"><label>Tipo</label>
                <select name="fundamento_tipo">
                  @foreach(['Reglamento','Lineamiento','Manual','Acuerdo','Ley'] as $tipo)
                    <option {{ $f->tipo_normativa === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field"><label>Artículo / fracción</label><input name="fundamento_articulo" value="{{ $f->articulo_fraccion }}"></div>
              <div class="field span-2"><label>Resumen</label><textarea name="fundamento_resumen" rows="3">{{ $f->resumen }}</textarea></div>
            </div>
          @empty
            <div class="wizard-fields">
              <div class="field span-2"><label>Normativa vinculada</label><input name="fundamento_normativa" placeholder="Buscar regulación..."></div>
              <div class="field"><label>Tipo</label><select name="fundamento_tipo"><option>Reglamento</option><option>Lineamiento</option><option>Manual</option><option>Acuerdo</option><option>Ley</option></select></div>
              <div class="field"><label>Artículo / fracción</label><input name="fundamento_articulo" placeholder="Ej. Artículo 45, Fracción II"></div>
              <div class="field span-2"><label>Resumen</label><textarea name="fundamento_resumen" rows="3" placeholder="Explique la disposición aplicable..."></textarea></div>
            </div>
          @endforelse
          </div>
        </section>

        {{-- SECCIÓN: Ficha para portal ciudadano --}}
        @php $ficha = $tramite->fichaPortal; @endphp
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
                <select name="portal_modalidad">
                  @php $modAct = old('portal_modalidad', $ficha->modalidad ?? 'Presencial'); @endphp
                  <option value="Presencial" {{ $modAct==='Presencial'?'selected':'' }}>Presencial</option>
                  <option value="En línea"   {{ $modAct==='En línea'?'selected':'' }}>En línea</option>
                  <option value="Mixta"      {{ $modAct==='Mixta'?'selected':'' }}>Mixta</option>
                </select>
              </div>
              <div class="field span-2"><label>Costo público (capturado en Operación)</label>
                <input type="text" id="costoPublicoResumen" readonly
                  value="{{ old('portal_costo', $ficha->costo_publico ?? 'Gratuito') }}"
                  style="background:var(--surface-low);cursor:not-allowed">
                <small class="campo-nota">Para cambiarlo, regrese al paso de Operación.</small></div>
              <div class="field span-2"><label>Descripción accesible</label>
                <textarea name="portal_descripcion" rows="3" placeholder="Descripción accesible para la ciudadanía...">{{ old('portal_descripcion', $ficha->descripcion ?? '') }}</textarea></div>
              <div class="field"><label>Horario de atención</label>
                <input name="portal_horario" value="{{ old('portal_horario', $ficha->horario ?? '') }}" placeholder="Ej. Lun a Vie 8:00-15:00"></div>
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
    <p style="margin:0 0 16px;font-size:13px;color:#6b7280">Configure los días y horarios en que se atiende el trámite.</p>
    <div class="horario-accesos">
      <span style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;align-self:center">Accesos:</span>
      <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('lv')">Lun–Vie</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('ls')">Lun–Sáb</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('todos')">Todos</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="horarioLimpiar()">Limpiar</button>
    </div>
    <div id="horariosGrid"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
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
    var art = document.createElement('article');
    art.className = 'paso-card' + (p.es_subpaso ? ' paso-sub' : '');
    art.innerHTML =
      '<div class="paso-num">' + numeroDePaso(i) + '</div>' +
      '<div class="paso-campos">' +
        '<input type="text" placeholder="¿Quién lo realiza? (área o responsable)" value="' + (p.area || '').replace(/"/g, '&quot;') + '" oninput="setPaso(' + i + ', \'area\', this.value)">' +
        '<textarea rows="2" placeholder="¿En qué consiste este paso?" oninput="setPaso(' + i + ', \'accion\', this.value)">' + (p.accion || '') + '</textarea>' +
      '</div>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="quitarPaso(' + i + ')">Quitar</button>';
    cont.appendChild(art);
  });
  document.getElementById('pasosJson').value = JSON.stringify(_pasos);
}

function agregarPaso(esSubpaso) {
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
    var fila = document.createElement('div');
    fila.className = 'derecho-fila';
    var esUma = d.unidad === 'UMA';
    var equiv = esUma ? (' ≈ $' + derechoEnPesos(d).toFixed(2)) : '';
    fila.innerHTML =
      '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)">' +
      '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)">' +
      '<select onchange="setDerecho(' + i + ', \'unidad\', this.value)">' +
        '<option value="pesos"' + (esUma ? '' : ' selected') + '>Pesos</option>' +
        '<option value="UMA"' + (esUma ? ' selected' : '') + '>UMA</option>' +
      '</select>' +
      '<label><input type="checkbox"' + (d.es_variable ? ' checked' : '') + ' onchange="setDerecho(' + i + ', \'es_variable\', this.checked)"> Variable</label>' +
      '<span class="derecho-equiv">' + equiv + '</span>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>';
    cont.appendChild(fila);
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
  }
}

document.addEventListener('DOMContentLoaded', function () {
  try {
    var inicial = JSON.parse(document.getElementById('derechosJson').value || '[]');
    if (Array.isArray(inicial)) _derechos = inicial;
  } catch (e) { _derechos = []; }
  renderDerechos();
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
      +'<div class="field"><label>¿Original?</label><select name="requisitos['+i+'][original]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>'
      +'<div class="field"><label>¿Copia?</label><select name="requisitos['+i+'][copia]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>'
      +'<div class="field"><label>Días</label><input name="requisitos['+i+'][dias]" type="number" min="0" value="0"></div>'
      +'<div class="field"><label>Horas</label><input name="requisitos['+i+'][horas]" type="number" min="0" value="0"></div>'
      +'<div class="field span-2"><label>Observaciones</label><textarea name="requisitos['+i+'][observaciones]" rows="2"></textarea></div>'
      +'</div>'
      +'<div class="section-actions mt-2"><button type="button" class="btn btn-outline btn-sm danger" onclick="this.closest(\'article\').remove()">Eliminar</button></div>';
    document.getElementById('reqContainer').appendChild(a);
  };

  go(1);
})();

  // ─── Fase F.4: Horarios de atención ────────────────────────────
  var DIAS_H = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  var H_INI = '09:00', H_FIN = '15:00';
  var horariosData = (function () {
    var el = document.getElementById('horariosJson');
    if (!el || !el.value) return {};
    try { return JSON.parse(el.value); } catch(e) { return {}; }
  })();

  function renderHorariosGrid() {
    var g = document.getElementById('horariosGrid');
    if (!g) return;
    g.innerHTML = '';
    DIAS_H.forEach(function (dia, i) {
      var r = document.createElement('div');
      r.className = 'horario-row';
      var act = horariosData[dia]?.activo || false;
      var ini = horariosData[dia]?.inicio || H_INI;
      var fin = horariosData[dia]?.fin    || H_FIN;
      r.innerHTML = '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;width:110px">'
        + '<input type="checkbox" onchange="hToggle(\''+dia+'\')" '+(act?'checked':'')+'>'
        + '<span style="font-size:13px;font-weight:'+(i<5?'700':'400')+'">'+dia+'</span></label>'
        + '<div style="display:flex;flex-direction:column;gap:2px"><label class="u-meta-label">Apertura</label>'
        + '<input type="time" value="'+ini+'" '+(act?'':'disabled')+' class="u-text-sm" onchange="hHora(\''+dia+'\',\'inicio\',this.value)"></div>'
        + '<div style="display:flex;flex-direction:column;gap:2px"><label class="u-meta-label">Cierre</label>'
        + '<input type="time" value="'+fin+'" '+(act?'':'disabled')+' class="u-text-sm" onchange="hHora(\''+dia+'\',\'fin\',this.value)"></div>';
      g.appendChild(r);
    });
  }

  function hToggle(dia) {
    if (!horariosData[dia]) horariosData[dia] = { inicio: H_INI, fin: H_FIN };
    horariosData[dia].activo = !horariosData[dia].activo;
    renderHorariosGrid();
  }
  function hHora(dia, campo, val) {
    if (!horariosData[dia]) horariosData[dia] = { activo: true, inicio: H_INI, fin: H_FIN };
    horariosData[dia][campo] = val;
  }
  function horarioPreset(t) {
    var dias = t==='lv' ? DIAS_H.slice(0,5) : t==='ls' ? DIAS_H.slice(0,6) : DIAS_H;
    DIAS_H.forEach(function(d){ horariosData[d]={activo:dias.includes(d),inicio:H_INI,fin:H_FIN}; });
    renderHorariosGrid();
  }
  function horarioLimpiar() { horariosData = {}; renderHorariosGrid(); }
  function guardarHorarios() {
    var activos = DIAS_H.filter(function(d){ return horariosData[d]?.activo; });
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
  function abrirHorarios()  { renderHorariosGrid(); document.getElementById('modalHorarios').classList.add('open'); }
  function cerrarHorarios() { document.getElementById('modalHorarios').classList.remove('open'); }


</script>
@endpush
