@extends('layouts.app')
@section('title', 'Detalle del Trámite')

@section('content')
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
      <span class="badge {{ match($tramite->estatus) {
        'completado','en_firma' => 'success-b',
        'en_correccion' => 'warning-b',
        'en_observacion' => '',
        default => ''
      } }}" style="text-transform:uppercase">@estatus($tramite->estatus)</span>
      <span class="text-primary-bold">{{ $tramite->homoclave ?? 'Sin folio' }}</span>
      <div class="actions-right">
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerEditado())
          <a href="{{ route('tramites.edit',$tramite) }}" class="btn btn-outline btn-sm">Editar</a>
        @endif
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerPublicado())
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="publicar">
            <button type="submit" class="btn btn-sm" onclick="return confirm('¿Enviar a revisión? El trámite será visible para Jurídico, Sujeto Obligado y Revisora.')">Enviar a revisión</button>
          </form>
        @endif
        @if(auth()->user()->puedeEditarTramite($tramite) && $tramite->puedeSerRepublicado())
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="republicar">
            <button type="submit" class="btn btn-sm" onclick="return confirm('¿Republicar para nueva revisión?')">Republicar</button>
          </form>
        @endif
        @if($tramite->estaEnObservacion() && auth()->user()->tienePermiso('tramites.observar'))
          {{-- Observar ahora se hace con los botones "+ Agregar observación" por sección, más abajo en este mismo detalle --}}
        @endif
        @if($tramite->estaEnObservacion() && $tramite->tieneObservacionesPendientes() && auth()->user()->puedeEditarTramite($tramite))
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="atender_observaciones">
            <button type="submit" class="btn btn-sm" onclick="return confirm('¿Cerrar el periodo de observaciones y pasar a corrección?')">Atender observaciones</button>
          </form>
        @endif
        @if($tramite->puedeAvanzarAFirma() && auth()->user()->isAnyRol(['revisora','admin']))
          <form method="POST" action="{{ route('tramites.actualizar.estatus',$tramite) }}" class="d-inline">
            @csrf <input type="hidden" name="accion" value="enviar_firma">
            <button type="submit" class="btn btn-sm" onclick="return confirm('¿Enviar a firma?')">Enviar a firma</button>
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
        <div class="modal-data-item"><span>Dependencia</span><strong>{{ $tramite->dependencia->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Unidad administrativa</span><strong>{{ $tramite->unidad->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Servidor público</span><strong>{{ $tramite->servidor_publico ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Dirigido a</span><strong>{{ ucfirst($tramite->dirigido_a ?? '—') }}</strong></div>
        <div class="modal-data-item"><span>Frecuencia</span><strong>{{ $tramite->frecuencia ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Tipo de relación</span><strong>{{ $tramite->tipo_relacion ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Volumen anual</span><strong>{{ number_format($tramite->volumen_anual ?? 0) }} solicitudes</strong></div>
        <div class="modal-data-item"><span>Plazo de resolución</span><strong>@plazo($tramite->plazo_resolucion_cantidad, $tramite->plazo_resolucion_unidad)</strong></div>
      </div>
      @if($tramite->objetivo)
        <div class="section-divided">
          <p class="label-meta">Objetivo</p>
          <p class="text-muted-sm">{{ $tramite->objetivo }}</p>
        </div>
      @endif
    </div>
    @include('partials.obs-inline', ['seccion' => 'Datos generales', 'observaciones' => $observacionesPorSeccion ?? collect()])
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
        <div class="modal-data-item"><span><strong>CBD unitario</strong></span><strong>${{ number_format($tramite->cbd_directo ?? 0, 2) }} MXN</strong></div>
      </div>

      @if($tramite->derechos->isNotEmpty())
      {{-- Desglose de los conceptos de derechos que componen el monto --}}
      <span class="label-meta mt-3">Conceptos de pago de derechos</span>
      <div class="modal-grid mt-2">
        @foreach($tramite->derechos as $derecho)
        <div class="modal-data-item"><span>{{ $derecho->concepto }}</span><strong>${{ number_format($derecho->monto, 2) }}</strong></div>
        @endforeach
        <div class="modal-data-item"><span><strong>Total derechos</strong></span><strong>${{ number_format($tramite->derechos->sum('monto'), 2) }} MXN</strong></div>
      </div>
      @endif

      {{-- Costo indirecto - desglose --}}
      <div class="section-divided">
        <span class="label-meta">Costo indirecto (CBI)</span>
        <div class="modal-grid mt-2">
          <div class="modal-data-item"><span>Tiempo por requisitos</span><strong>${{ number_format($tramite->cbi_requisitos ?? 0, 2) }}</strong></div>
          <div class="modal-data-item"><span>Tiempo por resolución</span><strong>${{ number_format($tramite->cbi_resolucion ?? 0, 2) }}</strong></div>
          <div class="modal-data-item"><span><strong>CBI unitario</strong></span><strong>${{ number_format($tramite->cbi_indirecto ?? 0, 2) }} MXN</strong></div>
        </div>
      </div>

      {{-- Totales --}}
      <div class="section-divided">
        <span class="label-meta">Totales</span>
        <div class="modal-grid mt-2">
          <div class="modal-data-item"><span>CBU — Costo Unitario</span><strong>${{ number_format($tramite->cbu_unitario ?? 0, 2) }} MXN</strong></div>
          <div class="modal-data-item"><span>Volumen anual</span><strong>{{ number_format($tramite->volumen_anual ?? 0) }}</strong></div>
          <div class="modal-data-item"><span>CBT — Costo Total Anual</span><strong>${{ number_format($tramite->cbt_total ?? 0, 2) }} MXN</strong></div>
          <div class="modal-data-item"><span>Categoría por costo unitario</span><strong>{{ ucfirst($tramite->categoriaPorCostoUnitario()) }}</strong></div>
        </div>
      </div>

      {{-- Impacto y umbral --}}
      <div class="section-divided">
        <span class="label-meta">Impacto contra umbral configurado</span>

        @if($snapshotCosto && $snapshotCosto->umbral_id)
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
    @include('partials.obs-inline', ['seccion' => 'Costo burocrático', 'observaciones' => $observacionesPorSeccion ?? collect()])
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
            <div class="modal-data-item"><span>Original</span><strong>{{ $req->original ? 'Sí' : 'No' }}</strong></div>
            <div class="modal-data-item"><span>Copia</span><strong>{{ $req->copia ? 'Sí' : 'No' }}</strong></div>
            <div class="modal-data-item"><span>Tiempo estimado</span><strong>
              @php
                $partes = [];
                if (($req->dias_estimados ?? 0) > 0)  $partes[] = $req->dias_estimados . ' ' . ($req->dias_estimados == 1 ? 'día' : 'días');
                if (($req->horas_estimadas ?? 0) > 0) $partes[] = $req->horas_estimadas . ' ' . ($req->horas_estimadas == 1 ? 'hora' : 'horas');
                if (($req->minutos_estimados ?? 0) > 0) $partes[] = $req->minutos_estimados . ' min';
              @endphp
              @if(count($partes)) {{ implode(', ', $partes) }} @else <span class="sin-dato">Sin dato</span> @endif
            </strong></div>
            <div class="modal-data-item"><span>Tipo</span><strong>{{ ucfirst($req->tipo_presentacion ?? '—') }}</strong></div>
          </div>
          @if($req->observaciones)
            <p style="margin:8px 0 0;color:#667085;font-size:13px">{{ $req->observaciones }}</p>
          @endif
        </div>
      @empty
        <div class="cal-empty-state">No hay requisitos registrados.</div>
      @endforelse
    </div>
    @include('partials.obs-inline', ['seccion' => 'Requisitos', 'observaciones' => $observacionesPorSeccion ?? collect()])
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
        <div class="modal-grid">
          <div class="modal-data-item"><span>Normativa</span><strong>{{ $f->normativa_nombre ?? '—' }}</strong></div>
          <div class="modal-data-item"><span>Tipo</span><strong>{{ $f->tipo_normativa ?? '—' }}</strong></div>
          <div class="modal-data-item"><span>Artículo</span><strong>{{ $f->articulo_fraccion ?? '—' }}</strong></div>
          @if($f->resumen)
            <div class="modal-data-item u-span-2"><span>Resumen</span><strong>{{ $f->resumen }}</strong></div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- OBSERVACIONES: el listado completo ahora vive en el panel lateral
       (partials.observaciones-checklist), agrupado por sección con su
       progreso. Ya no se muestra el bloque plano al final. --}}

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
@endsection