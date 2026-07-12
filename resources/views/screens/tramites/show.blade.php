@extends('layouts.app')
@section('title', 'Detalle del Trámite')

@section('content')
@php
  // #54: controla si se muestra el botón "Marcar como atendida" en las
  // observaciones inline. Quien puede editar el trámite (normalmente el
  // enlace de la dependencia, o admin) puede atender cualquier observación
  // de la sección; además, cada observación individual también se puede
  // atender por su destinatario específico (revisado dentro del partial).
  $puedeAtender = auth()->user()->puedeEditarTramite($tramite);
@endphp
<div class="page-default">

  {{-- Botón volver --}}
  <div>
    <a href="{{ route('tramites.index') }}" class="btn btn-outline">Volver a la lista</a>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">

  {{-- HEADER --}}
  <div class="card card-pad">
    <div class="row-center mb-4">
      <x-badge-estatus :estatus="$tramite->estatus" mayuscula />
      <span class="badge {{ $tramite->esServicio() ? 'badge-info' : 'badge-default' }}">{{ $tramite->naturalezaLegible() }}</span>
      <span class="text-primary-bold">{{ $tramite->homoclave ?? 'Sin folio' }}</span>
      <div class="actions-right">
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerEditado())
          <a href="{{ route('tramites.edit',$tramite) }}" class="btn btn-outline btn-sm">Editar</a>
        @endif
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerPublicado())
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="publicar">
            <button type="submit" class="btn btn-sm" onclick="return confirmarAccion(this, '¿Enviar a revisión? El trámite será visible para Jurídico, Sujeto Obligado y Revisora.')">Enviar a revisión</button>
          </form>
        @endif
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerRepublicado())
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="republicar">
            <button type="submit" class="btn btn-sm" onclick="return confirmarAccion(this, '¿Republicar para nueva revisión?')">Republicar</button>
          </form>
        @endif
        @if($tramite->estaEnObservacion() && auth()->user()->tienePermiso('tramites.observar'))
          {{-- Observar ahora se hace con los botones "+ Agregar observación" por sección, más abajo en este mismo detalle --}}
        @endif
        @if($tramite->estaEnObservacion() && $tramite->observaciones()->whereIn('estatus', ['pendiente','reabierta'])->exists() && auth()->user()->puedeEditarTramite($tramite))
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="atender_observaciones">
            <button type="submit" class="btn btn-sm" onclick="return confirmarAccion(this, '¿Cerrar el periodo de observaciones y pasar a corrección?')">Atender observaciones</button>
          </form>
        @endif
        @if($tramite->puedeAvanzarAFirma() && auth()->user()->isAnyRol(['revisora','admin']))
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="enviar_firma">
            <button type="submit" class="btn btn-sm" onclick="return confirmarAccion(this, '¿Enviar a firma?')">Enviar a firma</button>
          </form>
        @endif
        @if($tramite->puedeSerFirmado())
          <a href="{{ route('firmas.mostrar',['tipo'=>'tramite','id'=>$tramite->id]) }}" class="btn btn-sm">Firmar</a>
        @endif
        @if($tramite->estaCompletado())
          <a href="{{ route('tramites.acuse',$tramite) }}" class="btn btn-outline btn-sm">Ver acuse</a>
        @endif
      </div>
    </div>
    <h1 class="text-primary-lg">{{ $tramite->nombre_oficial }}</h1>
    <p class="text-muted-sm">
      Trámite — {{ ucfirst($tramite->dirigido_a ?? 'ambas') }} ·
      {{ $tramite->dependencia->nombre ?? '—' }}
      @if($tramite->volumen_anual) · {{ number_format($tramite->volumen_anual) }} solicitudes/año @endif
    </p>
  </div>

  {{-- CATÁLOGOS DESACTUALIZADOS --}}
  {{-- Va JUSTO DEBAJO DEL HEADER, antes que cualquier dato. Quien abre un trámite firmado
       tiene que enterarse de esto antes de leer nada, porque afecta a todo lo que va a leer.

       El mecanismo lleva tiempo funcionando —congelarCatalogos() guarda una foto de los
       nombres al firmar, y tieneCatalogosDesactualizados() detecta si alguno cambió después—
       pero ninguna pantalla lo pintaba. Un trámite firmado cuya dependencia se renombró
       mostraba el nombre viejo y nadie se enteraba de que había discrepancia.

       Ojo con lo que este aviso NO dice: no dice que haya un error. El documento firmado DEBE
       seguir diciendo lo que decía; cambiarlo por su cuenta sería alterar un acto jurídico ya
       firmado. Lo que hace falta es que una PERSONA decida si hay que rehacerlo. --}}
  @if($tramite->catalogosCongelados() && $tramite->tieneCatalogosDesactualizados())
  <div class="assist-box" style="border-color:#fbbf24;background:#fffbeb;color:#92400e;display:flex;align-items:flex-start;gap:12px">
    <strong style="font-size:18px">⚠️</strong>
    <div>
      <strong>Un catálogo cambió de nombre después de que este trámite se firmara.</strong><br>
      El trámite sigue mostrando los nombres que tenía al firmarse, y eso es lo correcto: un
      documento firmado no puede cambiar de contenido por su cuenta. Pero conviene que alguien
      decida si hay que rehacerlo.
      <ul style="margin:8px 0 0;padding-left:18px">
        @foreach($tramite->catalogosDesactualizados() as $catalogo => $cambio)
          <li>
            <strong>{{ ucfirst($catalogo) }}:</strong>
            al firmar decía «{{ $cambio['al_firmar'] }}», ahora se llama «{{ $cambio['ahora'] }}».
          </li>
        @endforeach
      </ul>
    </div>
  </div>
  @endif

  {{-- DATOS GENERALES --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Datos generales</h3><p>Identificación y responsable del trámite.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Datos generales')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item"><span>Homoclave</span><strong>{{ $tramite->homoclave ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Tipo</span><strong>{{ $tramite->tipoLegible() }}</strong></div>
        <div class="modal-data-item"><span>Dependencia</span><strong>{{ $tramite->dependencia->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Unidad administrativa</span><strong>{{ $tramite->unidad->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Servidor público</span><strong>{{ $tramite->servidor_publico ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Dirigido a</span><strong>{{ ucfirst($tramite->dirigido_a ?? '—') }}</strong></div>
        <div class="modal-data-item"><span>Frecuencia</span><strong>{{ $tramite->frecuencia ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Tipo de relación</span><strong>{{ $tramite->tipo_relacion ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Volumen anual</span><strong>{{ number_format($tramite->volumen_anual ?? 0) }} solicitudes</strong></div>
        <div class="modal-data-item"><span>Plazo de resolución</span><strong>@plazo($tramite->plazo_resolucion_cantidad, $tramite->plazo_resolucion_unidad)</strong></div>
        <div class="modal-data-item"><span>Población objetivo</span><strong>{{ $tramite->poblacion_objetivo ?? '—' }}</strong></div>
        <div class="modal-data-item u-span-2"><span>Grupos prioritarios / de atención</span><strong>{{ !empty($tramite->grupos_atencion) ? implode(', ', (array) $tramite->grupos_atencion) : '—' }}</strong></div>
        @if(!empty($tramite->grupo_prioritario_detalle))
          <div class="modal-data-item u-span-2"><span>Detalle de grupo prioritario</span><strong>{{ $tramite->grupo_prioritario_detalle }}</strong></div>
        @endif
      </div>
      {{-- Trámites relacionados del catálogo (rubro 10.2) --}}
      @if($tramite->relacionados->count())
        <div class="section-divided">
          <p class="label-meta">Trámites relacionados</p>
          <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px">
            @foreach($tramite->relacionados as $rel)
              <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--surface-tint);border-radius:var(--radius-sm);border:0.5px solid var(--primary-fixed)">
                <div style="min-width:0;flex:1">
                  <strong style="font-size:13px;display:block">{{ $rel->nombre_oficial }}</strong>
                  <span style="font-size:11px;color:var(--muted)">{{ $rel->homoclave }} · {{ $rel->dependencia->nombre ?? '—' }}</span>
                </div>
                <a href="{{ route('tramites.show', $rel) }}" class="btn btn-outline btn-sm" style="flex-shrink:0">Ver</a>
              </div>
            @endforeach
          </div>
          @if($tramite->relacionados_detalle)
            <p class="text-muted-sm" style="margin-top:8px">{{ $tramite->relacionados_detalle }}</p>
          @endif
        </div>
      @elseif($tramite->relacionados_detalle)
        <div class="section-divided">
          <p class="label-meta">Trámites relacionados</p>
          <p class="text-muted-sm">{{ $tramite->relacionados_detalle }}</p>
        </div>
      @endif
      @if($tramite->objetivo)
        <div class="section-divided">
          <p class="label-meta">Objetivo</p>
          <p class="text-muted-sm">{{ $tramite->objetivo }}</p>
        </div>
      @endif
    </div>
    @include('partials.obs-inline', ['seccion' => 'Datos generales', 'observaciones' => $observacionesPorSeccion ?? collect(), 'puedeAtender' => $puedeAtender])
  </div>

  {{-- OPERACIÓN DEL TRÁMITE: tiempos, áreas, copias y costo variable que antes
       se capturaban pero no se mostraban en el detalle. --}}
  <div class="card">
    <div class="panel-head"><div><h3>Operación del trámite</h3><p>Tiempos, áreas y detalles operativos.</p></div></div>
    <div class="card-body-padded">
      <span class="label-meta">Tiempos del ciudadano</span>
      <div class="modal-grid mt-2">
        <div class="modal-data-item"><span>Traslado</span><strong>{{ (int) ($tramite->tiempo_traslado_horas ?? 0) }} h {{ (int) ($tramite->tiempo_traslado_min ?? 0) }} min</strong></div>
        <div class="modal-data-item"><span>Espera</span><strong>{{ (int) ($tramite->tiempo_espera_horas ?? 0) }} h {{ (int) ($tramite->tiempo_espera_min ?? 0) }} min</strong></div>
        <div class="modal-data-item"><span>Atención</span><strong>{{ (int) ($tramite->tiempo_atencion_horas ?? 0) }} h {{ (int) ($tramite->tiempo_atencion_min ?? 0) }} min</strong></div>
        <div class="modal-data-item"><span>Visitas requeridas</span><strong>{{ $tramite->visitas_requeridas ?? '—' }}</strong></div>
      </div>

      <span class="label-meta mt-3">Áreas y digitalización</span>
      <div class="modal-grid mt-2">
        <div class="modal-data-item"><span>Número de áreas</span><strong>{{ $tramite->num_areas ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Nivel de digitalización</span><strong>{{ $tramite->nivel_digitalizacion ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Etapa de operación</span><strong>{{ $tramite->etapa_operacion ?? '—' }}</strong></div>
        @if(!empty($tramite->areas_participantes))
        <div class="modal-data-item u-span-2"><span>Áreas participantes</span><strong>{{ $tramite->areas_participantes }}</strong></div>
        @endif
      </div>

      <span class="label-meta mt-3">Copias y costo variable</span>
      <div class="modal-grid mt-2">
        <div class="modal-data-item"><span>Copias</span><strong>{{ $tramite->copias_cantidad ?? 0 }} × ${{ number_format($tramite->copias_precio ?? 0, 2) }}</strong></div>
        <div class="modal-data-item"><span>Costo de derechos variable</span><strong>{{ $tramite->monto_derechos_variable ? 'Sí' : 'No' }}</strong></div>
        @if($tramite->monto_derechos_variable)
        <div class="modal-data-item u-span-2"><span>Monto de referencia</span><strong>{{ $tramite->monto_derechos_referencia ?? '—' }}</strong></div>
        @endif
      </div>
    </div>
  </div>

  {{-- COSTO BUROCRÁTICO --}}
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Costo burocrático (ATDT)</h3>
        <p>Cálculo del costo que representa para la ciudadanía y su impacto contra el umbral configurado.</p>
      </div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Costo burocrático')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">

      {{-- Costo directo - desglose --}}
      <span class="label-meta">Costo directo (CBD)</span>
      <div class="modal-grid mt-2">
        <div class="modal-data-item"><span>Derechos</span><strong>${{ number_format($snapshotCosto->monto_derechos ?? $tramite->monto_derechos ?? 0, 2) }}</strong></div>
        <div class="modal-data-item"><span>Copias</span><strong>${{ number_format($snapshotCosto->monto_copias ?? 0, 2) }}</strong></div>
        <div class="modal-data-item"><span>Requisitos con costo</span><strong>${{ number_format($snapshotCosto->monto_requisitos ?? $tramite->monto_requisitos_con_costo ?? 0, 2) }}</strong></div>
        <div class="modal-data-item cost-total"><span><strong>CBD unitario</strong></span><strong>${{ number_format($tramite->cbd_directo ?? 0, 2) }} MXN</strong></div>
      </div>

      @if($tramite->derechos->isNotEmpty())
      {{-- Desglose de los conceptos de derechos que componen el monto --}}
      <span class="label-meta mt-3">Conceptos de pago de derechos</span>
      <div class="modal-grid mt-2">
        @foreach($tramite->derechos as $derecho)
        <div class="modal-data-item"><span>{{ $derecho->concepto }}</span><strong>${{ number_format($derecho->monto, 2) }}</strong></div>
        @php
          // Fundamento jurídico del cobro de este derecho (norma · capítulo · artículo).
          $fjDerecho = collect([$derecho->fj_norma, $derecho->fj_capitulo, $derecho->fj_articulo])
            ->map(fn ($p) => trim((string) $p))->filter()->implode(' · ');
        @endphp
        @if($fjDerecho !== '')
        <div class="modal-data-item u-span-2"><span>Fundamento del cobro</span><strong>{{ $fjDerecho }}</strong></div>
        @endif
        @endforeach
        <div class="modal-data-item"><span><strong>Total derechos</strong></span><strong>${{ number_format($tramite->derechos->sum('monto'), 2) }} MXN</strong></div>
      </div>
      @endif

      {{-- Costo indirecto - desglose --}}
      @php
        // ¿El costo de espera es un número de verdad, o un cero que tapa una laguna?
        //
        // El servicio devuelve CERO cuando no puede calcularlo (faltan el PIB, la población y
        // la tasa libre de riesgo para las personas físicas; o los datos económicos de la
        // actividad para las personas morales) y lo deja anotado en el snapshot.
        //
        // Sin esta comprobación, ese cero se pintaría igual que el de un trámite que de verdad
        // se resuelve en el acto. El usuario leería "esperar no cuesta nada" cuando la verdad
        // es "no lo sabemos".
        $costoDeEsperaCompleto = $tramite->costoDeEsperaCalculable();
      @endphp

      <div class="section-divided">
        <span class="label-meta">Costo indirecto (CBI)</span>
        <div class="modal-grid mt-2">
          <div class="modal-data-item"><span>Tiempo por requisitos</span><strong>${{ number_format($tramite->cbi_requisitos ?? 0, 2) }}</strong></div>
          <div class="modal-data-item">
            <span>Tiempo por resolución</span>
            <strong>
              @if($costoDeEsperaCompleto)
                ${{ number_format($tramite->cbi_resolucion ?? 0, 2) }}
              @else
                {{-- No se pinta $0.00. Un cero es una AFIRMACIÓN ("esperar no cuesta nada"), y
                     eso no es lo que el sistema sabe: lo que sabe es que no lo sabe. --}}
                <span class="chip chip-gray">Sin calcular</span>
              @endif
            </strong>
          </div>
          <div class="modal-data-item cost-total"><span><strong>CBI unitario</strong></span><strong>${{ number_format($tramite->cbi_indirecto ?? 0, 2) }} MXN</strong></div>
        </div>

        @unless($costoDeEsperaCompleto)
          <div class="assist-box mt-2" style="border-color:#fbbf24;background:#fffbeb;color:#92400e">
            <strong>El costo de espera no se pudo calcular.</strong><br>
            {{ $tramite->motivoCostoDeEsperaSinCalcular() }}<br>
            <span class="label-meta">Mientras falten esos datos, el tiempo que la ciudadanía espera la resolución cuenta como cero: el CBI, el CBU y el CBT están subestimados.</span>
          </div>
        @endunless
      </div>

      {{-- Totales --}}
      <div class="section-divided">
        <span class="label-meta">Totales</span>

        @unless($costoDeEsperaCompleto)
          {{-- El aviso va ARRIBA de los números, no debajo. Debajo, la mitad de la gente ya
               habría leído el CBT y sacado su conclusión: un aviso solo sirve si llega antes
               que el dato al que se refiere. --}}
          <div class="assist-box mt-2" style="border-color:#fbbf24;background:#fffbeb;color:#92400e">
            <strong>Estos totales están incompletos.</strong><br>
            No incluyen el costo del tiempo que la ciudadanía espera la resolución. El CBT real
            es <strong>mayor</strong> que el que se muestra, y con él pueden cambiar el
            porcentaje del umbral, el nivel de impacto y el resultado AIR.
          </div>
        @endunless

        <div class="modal-grid mt-2">
          <div class="modal-data-item cost-total">
            <span>CBU — Costo Unitario</span>
            <strong>${{ number_format($tramite->cbu_unitario ?? 0, 2) }} MXN @unless($costoDeEsperaCompleto)<span class="chip chip-amber">Parcial</span>@endunless</strong>
          </div>
          <div class="modal-data-item"><span>Volumen anual</span><strong>{{ number_format($tramite->volumen_anual ?? 0) }}</strong></div>
          <div class="modal-data-item cost-total">
            <span>CBT — Costo Total Anual</span>
            <strong>${{ number_format($tramite->cbt_total ?? 0, 2) }} MXN @unless($costoDeEsperaCompleto)<span class="chip chip-amber">Parcial</span>@endunless</strong>
          </div>
          <div class="modal-data-item"><span>Categoría por costo unitario</span><strong>{{ ucfirst($tramite->categoriaPorCostoUnitario()) }}</strong></div>
        </div>
      </div>

      {{-- Impacto y umbral --}}
      <div class="section-divided">
        <span class="label-meta">Impacto contra umbral configurado</span>

        @if($snapshotCosto && $snapshotCosto->umbral_id)

          @unless($costoDeEsperaCompleto)
            {{-- El impacto sale de dividir el CBT entre el umbral. Con un CBT incompleto, el
                 porcentaje sale bajo, el impacto sale "Bajo", y el sistema concluye que el
                 trámite NO requiere AIR.
                 Un "Bajo" falso es peor que un "No determinado" honesto: el primero cierra la
                 conversación, el segundo la abre. --}}
            <div class="assist-box mt-2" style="border-color:#fbbf24;background:#fffbeb;color:#92400e">
              <strong>Este impacto no es concluyente.</strong><br>
              Se calculó con un CBT incompleto (falta el costo de espera). El impacto real puede
              ser mayor, y el resultado AIR podría cambiar.
            </div>
          @endunless

          <div class="modal-grid mt-2">
            <div class="modal-data-item">
              <span>Nivel de impacto</span>
              <strong>
                @switch($tramite->impacto)
                  @case('critico') <span class="chip chip-red">Crítico</span> @break
                  @case('alto')    <span class="chip chip-amber">Alto</span> @break
                  @case('medio')   <span class="chip chip-amber">Medio</span> @break
                  @case('bajo')    <span class="chip chip-success">Bajo</span> @break
                  @default        <span class="chip chip-gray">No determinado</span>
                @endswitch
              </strong>
            </div>
            <div class="modal-data-item"><span>Porcentaje del umbral</span><strong>{{ number_format($snapshotCosto->porcentaje_umbral ?? 0, 2) }}%</strong></div>
            <div class="modal-data-item"><span>Umbral configurado</span><strong>${{ number_format($snapshotCosto->umbral_monto_pesos ?? 0, 2) }} MXN</strong></div>
            <div class="modal-data-item"><span>Equivalente UMA</span><strong>{{ number_format($snapshotCosto->umbral_monto_uma ?? 0, 2) }} UMA</strong></div>
            <div class="modal-data-item"><span>Equivalente salario mínimo</span><strong>{{ number_format($snapshotCosto->umbral_monto_salario_minimo ?? 0, 2) }}</strong></div>
            <div class="modal-data-item">
              <span>Resultado AIR</span>
              <strong>
                @switch($tramite->resultado_air)
                  @case('puede_requerir_air')      <span class="chip chip-amber">Puede requerir AIR</span> @break
                  @case('no_activa_automaticamente') <span class="chip chip-success">No activa automáticamente</span> @break
                  @default                          <span class="chip chip-gray">No determinado</span>
                @endswitch
              </strong>
            </div>
          </div>
        @else
          <div class="assist-box mt-2">
            <strong>Umbral pendiente de configurar.</strong><br>
            El sistema ya calculó el Costo Burocrático Total Anual, pero aún no existe un umbral activo para comparar el impacto. Configure un umbral desde el módulo administrativo.
          </div>
        @endif
      </div>

    </div>
    @include('partials.obs-inline', ['seccion' => 'Costo burocrático', 'observaciones' => $observacionesPorSeccion ?? collect(), 'puedeAtender' => $puedeAtender])
  </div>

  {{-- REQUISITOS --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Requisitos</h3><p>Documentos necesarios para realizar el trámite.</p></div>
      <div class="panel-head-actions">
        <span class="badge">{{ $tramite->requisitos->count() }} requisito{{ $tramite->requisitos->count() !== 1 ? 's' : '' }}</span>
        @if($puedeObservar ?? false)
          <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Requisitos')">+ Agregar observación</button>
        @endif
      </div>
    </div>
    <div class="card-body-padded d-grid" data-gap="12">
      @forelse($tramite->requisitos as $req)
        <div style="border:1px solid var(--surface-high);border-radius:10px;padding:14px 18px">
          <p style="margin:0 0 8px;font-weight:800;font-size:14px">{{ $req->orden }}. {{ $req->nombre }}</p>
          <div class="modal-grid">
            {{-- Bug #44: mostrar CSV de tipos como lista legible --}}
            <div class="modal-data-item"><span>Tipo de presentación</span><strong>{{ $req->tipo_presentacion ? collect(explode(',', $req->tipo_presentacion))->map(fn($t) => ucfirst(trim($t)))->implode(', ') : '—' }}</strong></div>
            <div class="modal-data-item"><span>Tiempo estimado</span><strong>
              @php
                $partes = [];
                if (($req->dias_estimados ?? 0) > 0)  $partes[] = $req->dias_estimados . ' ' . ($req->dias_estimados == 1 ? 'día' : 'días');
                if (($req->horas_estimadas ?? 0) > 0) $partes[] = $req->horas_estimadas . ' ' . ($req->horas_estimadas == 1 ? 'hora' : 'horas');
                if (($req->minutos_estimados ?? 0) > 0) $partes[] = $req->minutos_estimados . ' min';
              @endphp
              @if(count($partes)) {{ implode(', ', $partes) }} @else <span class="sin-dato">Sin dato</span> @endif
            </strong></div>
          </div>
          @if($req->observaciones)
            <p style="margin:8px 0 0;color:#667085;font-size:13px">{{ $req->observaciones }}</p>
          @endif
          @php
            $fjReq = collect([$req->fj_norma, $req->fj_capitulo, $req->fj_articulo])
              ->map(fn ($p) => trim((string) $p))->filter()->implode(' · ');
          @endphp
          @if($fjReq !== '' || $req->tiene_costo)
          <div class="modal-grid" style="margin-top:8px">
            @if($fjReq !== '')
            <div class="modal-data-item u-span-2"><span>Fundamento del requisito</span><strong>{{ $fjReq }}</strong></div>
            @endif
            @if($req->tiene_costo)
            @php $unidadReq = $req->costo_unidad ?? 'PESOS'; @endphp
            <div class="modal-data-item"><span>Costo del requisito</span><strong>{{ $unidadReq === 'UMA' ? number_format($req->costo_requisito ?? 0, 2) . ' UMA' : '$' . number_format($req->costo_requisito ?? 0, 2) }}{{ $req->costo_variable ? ' (variable)' : '' }}</strong></div>
            @endif
          </div>
          @endif
        </div>
      @empty
        <div class="cal-empty-state">No hay requisitos registrados.</div>
      @endforelse
    </div>
    @include('partials.obs-inline', ['seccion' => 'Requisitos', 'observaciones' => $observacionesPorSeccion ?? collect(), 'puedeAtender' => $puedeAtender])
  </div>

  {{-- FUNDAMENTO JURÍDICO --}}
  @if($tramite->fundamentos->count())
  <div class="card">
    <div class="panel-head">
      <div><h3>Fundamento jurídico</h3><p>Normativa que sustenta el trámite.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Fundamento jurídico')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded d-grid" data-gap="12">
      @foreach($tramite->fundamentos as $f)
        @php
          // Un fundamento puede ser de DOS tipos:
          //  - Cita del catálogo: tiene regulacion_id; el nombre/tipo viven en la
          //    regulación vinculada (normativa_nombre y tipo_normativa van NULL).
          //  - Norma manual: sin regulacion_id; los datos están en los campos
          //    normativa_nombre / tipo_normativa.
          // Antes el detalle solo leía los campos manuales, así que las citas del
          // catálogo aparecían vacías ("—"). Aquí se resuelve cada caso.
          $esCita = !is_null($f->regulacion_id);
        @endphp
        <div class="modal-grid">
          <div class="modal-data-item"><span>Normativa</span><strong>{{ $esCita ? ($f->regulacion?->nombre ?? 'Regulación del catálogo') : ($f->normativa_nombre ?? '—') }}</strong></div>
          <div class="modal-data-item"><span>Tipo</span><strong>{{ $esCita ? 'Citada del catálogo' : ($f->tipo_normativa ?? '—') }}</strong></div>
          <div class="modal-data-item"><span>Artículo</span><strong>{{ $f->articulo_fraccion ?? '—' }}</strong></div>
          @if($f->resumen)
            <div class="modal-data-item u-span-2"><span>Resumen</span><strong>{{ $f->resumen }}</strong></div>
          @endif
          @if($esCita && $f->regulacion)
            <div class="modal-data-item u-span-2" style="display:flex;gap:8px;flex-wrap:wrap">
              @if($f->regulacion->tieneIndice())
                {{-- La regulación tiene articulado: se abre el lector lateral para
                     consultar el artículo citado sin salir del trámite. --}}
                <button type="button" class="btn btn-outline btn-sm"
                        data-lector-reg-id="{{ $f->regulacion->id }}"
                        data-lector-reg-nombre="{{ $f->regulacion->nombre }}">Ver articulado</button>
              @endif
              {{-- Siempre disponible: abrir la ficha completa de la regulación. --}}
              <a href="{{ route('regulaciones.show', $f->regulacion) }}" class="btn btn-outline btn-sm">Ver regulación</a>
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
  {{-- Bug #34: obs-inline faltante para la sección Fundamento jurídico. --}}
  @include('partials.obs-inline', ['seccion' => 'Fundamento jurídico', 'observaciones' => $observacionesPorSeccion ?? collect(), 'puedeAtender' => $puedeAtender])
  @endif

  {{-- OBSERVACIONES: el listado completo ahora vive en el panel lateral
       (partials.observaciones-checklist), agrupado por sección con su
       progreso. Ya no se muestra el bloque plano al final. --}}

  {{-- ACCIONES DE AGENDA VINCULADAS --}}
  @if($tramite->acciones->isNotEmpty())
  <div class="card">
    <div class="panel-head"><div><h3>Acciones de Agenda SyD</h3><p>Acciones de simplificación o digitalización vinculadas a este trámite.</p></div></div>
    <div class="card-body-padded">
      @foreach($tramite->acciones as $accion)
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--surface-high)' : '' }}">
        <div>
          <strong>{{ $accion->descripcion }}</strong>
          <small style="color:var(--muted);display:block">{{ ['simplificacion'=>'Simplificación','digitalizacion'=>'Digitalización','ambas'=>'Simplificación y digitalización'][$accion->tipo] ?? ucfirst($accion->tipo) }} · @estatus($accion->estatus)</small>
        </div>
        <a href="{{ route('agenda.show', $accion) }}" class="btn btn-outline btn-sm">Ver acción</a>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- FICHA DEL PORTAL CIUDADANO: toda la información publicada para la
       ciudadanía; antes no se mostraba en el detalle. --}}
  @if($tramite->fichaPortal)
  @php $fp = $tramite->fichaPortal; @endphp
  <div class="card">
    <div class="panel-head"><div><h3>Ficha del Portal Ciudadano</h3><p>Información publicada para la ciudadanía.</p></div></div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item u-span-2"><span>Nombre ciudadano</span><strong>{{ $fp->nombre_ciudadano ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Homoclave pública</span><strong>{{ $fp->homoclave_publica ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Modalidad</span><strong>{{ $fp->modalidad ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Canal principal</span><strong>{{ $fp->canal_principal ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Requiere cita</span><strong>{{ $fp->requiere_cita ? 'Sí' : 'No' }}</strong></div>
        @if($fp->descripcion)
        <div class="modal-data-item u-span-2"><span>Descripción</span><strong>{{ $fp->descripcion }}</strong></div>
        @endif
        @if($fp->casos_realizarse)
        <div class="modal-data-item u-span-2"><span>Casos en que se realiza</span><strong>{{ $fp->casos_realizarse }}</strong></div>
        @endif
        <div class="modal-data-item"><span>Documento que obtiene</span><strong>{{ $fp->documento_obtiene ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Resultado</span><strong>{{ $fp->resultado ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Medio de entrega</span><strong>{{ $fp->medio_entrega ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Vigencia</span><strong>{{ $fp->vigencia ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Costo al público</span><strong>{{ $fp->costo_publico ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Forma de pago</span><strong>{{ $fp->forma_pago ?? '—' }}</strong></div>
      </div>

      <span class="label-meta mt-3">Contacto y atención</span>
      <div class="modal-grid mt-2">
        <div class="modal-data-item"><span>Oficina</span><strong>{{ $fp->oficina ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Teléfono</span><strong>{{ $fp->telefono ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Correo</span><strong>{{ $fp->correo ?? '—' }}</strong></div>
        @if($fp->direccion)
        <div class="modal-data-item u-span-2"><span>Dirección</span><strong>{{ $fp->direccion }}</strong></div>
        @endif
        @if($fp->url)
        <div class="modal-data-item u-span-2"><span>URL</span><strong>{{ $fp->url }}</strong></div>
        @endif
        @if($fp->horario)
        <div class="modal-data-item u-span-2"><span>Horario</span><strong>{{ $fp->horario }}</strong></div>
        @endif
      </div>

      @if(!empty($fp->horarios_json))
      <span class="label-meta mt-3">Horario de atención (por día)</span>
      <div class="modal-grid mt-2">
        {{-- Bug #50: el horarios_json es {"Lunes":{"activo":true,"inicio":"09:00","fin":"15:00"},...}.
             Antes se iteraba todo el sub-array (incluyendo activo=1) con implode, mostrando
             "1 · 09:00 · 15:00". Ahora solo muestra días activos con formato inicio–fin. --}}
        @foreach((array) $fp->horarios_json as $dia => $config)
          @if(is_array($config) && ($config['activo'] ?? false))
          <div class="modal-data-item">
            <span>{{ ucfirst($dia) }}</span>
            <strong>{{ $config['inicio'] ?? '—' }} – {{ $config['fin'] ?? '—' }}</strong>
          </div>
          @elseif(!is_array($config))
          {{-- Fallback para formato legacy (valor plano) --}}
          <div class="modal-data-item">
            <span>{{ is_numeric($dia) ? 'Horario ' . ($dia + 1) : ucfirst($dia) }}</span>
            <strong>{{ $config }}</strong>
          </div>
          @endif
        @endforeach
      </div>
      @endif
    </div>
  </div>
  @endif

  {{-- PASOS DEL TRÁMITE (proceso de atención paso a paso) --}}
  @if($tramite->procesosAtencion->isNotEmpty())
  <div class="card">
    <div class="panel-head"><div><h3>Pasos del trámite</h3><p>Proceso de atención paso a paso.</p></div></div>
    <div class="card-body-padded d-grid gap-2">
      @foreach($tramite->procesosAtencion as $p)
        <div class="modal-data-item u-span-2">
          <span>{{ trim('Paso ' . ($p->paso ?? '') . ($p->subpaso ? '.' . $p->subpaso : '')) }}{{ $p->area ? ' · ' . $p->area : '' }}</span>
          <strong>{{ $p->accion ?: ($p->detalle ?: '—') }}{{ $p->accion && $p->detalle ? ' — ' . $p->detalle : '' }}</strong>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- FIRMAS --}}
  {{-- MÓDULO FIRMAS --}}
  @if($tramite->firmas->where('estatus', 'activa')->count())
  <div class="card">
    <div class="panel-head"><div><h3>Firmas y validaciones</h3><p>Registro de aceptaciones y firmas.</p></div></div>
    <div class="card-body-padded d-grid gap-2">
      @foreach($tramite->firmas as $firma)
        <div class="modal-grid">
          <div class="modal-data-item"><span>Tipo</span><strong>{{ ucfirst(str_replace('_',' ',$firma->tipo)) }}</strong></div>
          <div class="modal-data-item"><span>Firmante</span><strong>{{ $firma->firmante->name ?? '—' }}</strong></div>
          <div class="modal-data-item"><span>Fecha</span><strong>{{ $firma->fecha ? \Carbon\Carbon::parse($firma->fecha)->format('d/m/Y H:i') : '—' }}</strong></div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion ?? collect(),
        'campos' => $camposObservables ?? [],
      ])
      @include('partials.timeline', ['tipo' => 'tramite', 'id' => $tramite->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>

@if($puedeObservar ?? false)
  <x-modal-observacion
    tipo="tramite"
    :id="$tramite->id"
    :campos="config('punta.campos_observables_tramite')"
    :revisores="$revisores" />
@endif

{{-- Forms invisibles para "Marcar como atendida" del checklist lateral. --}}
@if(isset($observacionesPorSeccion))
  @foreach(collect($observacionesPorSeccion)->flatten() as $obs)
    @if($obs->destinatario_id === auth()->id() && !in_array($obs->estatus ?? 'pendiente', ['atendida','validada']))
      <form method="POST" action="{{ route('revision.atendida', $obs) }}"
        id="obs-atend-{{ $obs->id }}" class="hidden">@csrf</form>
    @endif
  @endforeach
@endif
@endsection