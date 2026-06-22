@extends('layouts.app')
@section('title', 'Detalle de Acción')

@section('content')
<style>
  /* Paquete 3: listado de acciones con explicación en el detalle */
  .acciones-show { list-style:none; padding:0; margin:0; }
  .acciones-show li { padding:8px 0; border-bottom:1px solid var(--surface-high); }
  .acciones-show li:last-child { border-bottom:none; }
  .acciones-show strong { color:var(--text); font-size:14px; }
  .acciones-show p { margin:4px 0 0; color:var(--muted); font-size:13px; }
</style>
<div class="page-default">

  {{-- Botón volver --}}
  <div>
    <a href="{{ route('agenda.index') }}" class="btn btn-outline">Volver a la lista</a>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">

  {{-- HEADER --}}
  <div class="card card-section">
    <div class="row-center mb-4">
      <x-badge-estatus :estatus="$agenda->estatus" mayuscula />
      <span class="text-primary-bold">AGD-{{ str_pad($agenda->id,3,'0',STR_PAD_LEFT) }}</span>
      <div class="actions-right">
        {{-- El enlace edita su propia acción en estados editables; el admin edita cualquiera --}}
        @if(
          auth()->user()->isRol(App\Models\User::ROL_ADMIN) ||
          (
            auth()->user()->isRol(App\Models\User::ROL_ENLACE)
            && $agenda->created_by === auth()->id()
            && in_array($agenda->estatus, ['borrador','en_correccion'])
          )
        )
          <a href="{{ route('agenda.edit',$agenda) }}" class="btn btn-outline btn-sm">Editar</a>
        @endif
        @if(auth()->user()->isRol(App\Models\User::ROL_REVISORA))
          {{-- Observar ahora se hace con los botones "+ Agregar observación" por sección, más abajo en este mismo detalle --}}
        @endif
        @if(auth()->user()->isRol(App\Models\User::ROL_ENLACE) && $agenda->estatus === 'borrador')
          <form method="POST" action="{{ route('agenda.actualizar.estatus',$agenda) }}" class="d-inline">
            @csrf
            <input type="hidden" name="estatus" value="en_observacion">
            <button type="submit" class="btn btn-sm" onclick="return confirmarAccion(this, '¿Enviar a revisión?')">Enviar a revisión</button>
          </form>
        @endif
      </div>
    </div>
    <h1 class="text-primary-lg">{{ $agenda->descripcion }}</h1>
    <p class="text-muted-sm">
      Acción de Agenda · {{ ucfirst($agenda->tipo) }}
      @if($agenda->tramite) · vinculada a {{ $agenda->tramite->nombre_oficial }} @endif
    </p>
  </div>

  {{-- DATOS DE LA ACCIÓN --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Datos de la acción</h3><p>Identificación de la acción que se revisa.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Datos de la acción')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item"><span>Folio</span><strong>AGD-{{ str_pad($agenda->id,3,'0',STR_PAD_LEFT) }}</strong></div>
        <div class="modal-data-item"><span>Trámite vinculado</span><strong>{{ $agenda->tramite->nombre_oficial ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Alcance</span><strong>{{ ['simplificacion' => 'Solo simplificación', 'digitalizacion' => 'Solo digitalización', 'ambas' => 'Simplificación y digitalización'][$agenda->tipo] ?? ucfirst($agenda->tipo) }}</strong></div>
        <div class="modal-data-item"><span>Acción registrada</span><strong>{{ $agenda->descripcion }}</strong></div>
        <div class="modal-data-item"><span>Responsable</span><strong>{{ $agenda->responsable ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Estatus</span><strong>@estatus($agenda->estatus)</strong></div>
        <div class="modal-data-item"><span>Dependencia</span><strong>{{ $agenda->dependencia->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Registrado por</span><strong>{{ $agenda->creador->name ?? '—' }}</strong></div>
      </div>
    </div>
  </div>

  {{-- REQUISITOS HEREDADOS DEL TRÁMITE (solo lectura) --}}
  @if($agenda->tramite && $agenda->tramite->requisitos->isNotEmpty())
  <div class="card">
    <div class="panel-head">
      <div><h3>Requisitos del trámite</h3><p>Heredados del trámite vinculado. Se editan desde el trámite, no aquí.</p></div>
    </div>
    <div class="card-body-padded">
      <ol class="requisitos-heredados">
        @foreach($agenda->tramite->requisitos as $req)
          <li>
            <strong>{{ $req->nombre }}</strong>
            @if($req->tipo_presentacion)
              <span class="requisito-detalle">{{ ucfirst($req->tipo_presentacion) }}</span>
            @endif
          </li>
        @endforeach
      </ol>
    </div>
  </div>
  @endif

  {{-- PASOS DEL TRÁMITE (heredados, solo lectura) --}}
  @if($agenda->tramite && $agenda->tramite->procesosAtencion->isNotEmpty())
  <div class="card">
    <div class="panel-head">
      <div><h3>Pasos para realizar el trámite</h3><p>Heredados del trámite vinculado. Se editan desde el trámite.</p></div>
    </div>
    <div class="card-body-padded">
      <ol class="pasos-heredados">
        @foreach($agenda->tramite->procesosAtencion as $paso)
          <li class="{{ $paso->subpaso > 0 ? 'paso-heredado-sub' : '' }}">
            <span class="paso-heredado-num">{{ $paso->subpaso > 0 ? $paso->paso.'.'.$paso->subpaso : $paso->paso }}</span>
            <div>
              @if($paso->area)<strong>{{ $paso->area }}</strong>@endif
              @if($paso->accion)<p>{{ $paso->accion }}</p>@endif
            </div>
          </li>
        @endforeach
      </ol>
    </div>
  </div>
  @endif

  {{-- COSTO BUROCRÁTICO DEL TRÁMITE (heredado, solo lectura) --}}
  @if($agenda->tramite && $agenda->tramite->cbu_unitario !== null && (float)$agenda->tramite->cbu_unitario > 0)
  <div class="card">
    <div class="panel-head">
      <div><h3>Costo burocrático del trámite</h3><p>Calculado a partir de los datos del trámite (metodología ATDT).</p></div>
    </div>
    <div class="card-body-padded">
      <div class="costo-heredado-grid">
        <div class="costo-item"><span>Costo Directo (CBD)</span><strong>${{ number_format($agenda->tramite->cbd_directo, 2) }}</strong></div>
        <div class="costo-item"><span>Costo Indirecto (CBI)</span><strong>${{ number_format($agenda->tramite->cbi_indirecto, 2) }}</strong></div>
        <div class="costo-item"><span>Costo Unitario (CBU)</span><strong>${{ number_format($agenda->tramite->cbu_unitario, 2) }}</strong></div>
        <div class="costo-item"><span>Costo Total (CBT)</span><strong>${{ number_format($agenda->tramite->cbt_total, 2) }}</strong></div>
        <div class="costo-item"><span>Categoría</span><strong>{{ ucfirst($agenda->tramite->categoriaPorCostoUnitario()) }}</strong></div>
      </div>
    </div>
  </div>
  @endif

  {{-- ALCANCE Y NECESIDAD --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Alcance y necesidad</h3><p>Motivo ciudadano e institucional de la mejora.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Alcance y necesidad')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item"><span>Meta esperada</span><strong>{{ $agenda->meta ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Indicador de cumplimiento (rubro 17)</span><strong>{{ $agenda->indicador ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Indicador de avance (rubro 18)</span><strong>{{ $agenda->indicador_avance ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Fecha de inicio</span><strong>{{ $agenda->fecha_inicio ? \Carbon\Carbon::parse($agenda->fecha_inicio)->format('d/m/Y') : '—' }}</strong></div>
        <div class="modal-data-item"><span>Fecha compromiso</span><strong>{{ $agenda->fecha_compromiso ? \Carbon\Carbon::parse($agenda->fecha_compromiso)->format('d/m/Y') : '—' }}</strong></div>
      </div>
    </div>
  </div>

  {{-- Paquete 3: Acciones y niveles, filtrados por el alcance guardado --}}
  @php
    $esSimp = in_array($agenda->tipo, ['simplificacion', 'ambas']);
    $esDig  = in_array($agenda->tipo, ['digitalizacion', 'ambas']);
    $accSimp = is_array($agenda->acciones_simplificacion) ? $agenda->acciones_simplificacion : [];
    $accDig  = is_array($agenda->acciones_digitalizacion) ? $agenda->acciones_digitalizacion : [];
  @endphp

  @if($esSimp)
  <div class="card">
    <div class="panel-head">
      <div><h3>Acciones de simplificación</h3><p>Catálogo oficial (rubro 14) con su explicación.</p></div>
    </div>
    <div class="card-body-padded">
      @if(count($accSimp))
        <ul class="acciones-show">
          @foreach($accSimp as $accion => $explicacion)
            <li><strong>{{ $accion }}</strong>@if($explicacion)<p>{{ $explicacion }}</p>@endif</li>
          @endforeach
        </ul>
      @else
        <p class="muted">No se registraron acciones de simplificación.</p>
      @endif
    </div>
  </div>
  @endif

  @if($esDig)
  <div class="card">
    <div class="panel-head">
      <div><h3>Digitalización</h3><p>Niveles y acciones del catálogo oficial.</p></div>
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item"><span>Nivel actual</span><strong>{{ $agenda->nivel_actual !== null ? 'Nivel '.$agenda->nivel_actual : '—' }}</strong></div>
        <div class="modal-data-item"><span>Nivel meta</span><strong>{{ $agenda->nivel_meta !== null ? 'Nivel '.$agenda->nivel_meta : '—' }}</strong></div>
      </div>
      @if(count($accDig))
        <ul class="acciones-show" style="margin-top:12px">
          @foreach($accDig as $accion => $explicacion)
            <li><strong>{{ $accion }}</strong>@if($explicacion)<p>{{ $explicacion }}</p>@endif</li>
          @endforeach
        </ul>
      @else
        <p class="muted" style="margin-top:12px">No se registraron acciones de digitalización.</p>
      @endif
    </div>
  </div>
  @endif

  {{-- AVANCE --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Avance y evidencias</h3><p>Seguimiento del cumplimiento de la acción.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Avance y evidencias')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      @include('partials.hitos-agenda', [
        'agenda'      => $agenda,
        'hitos'       => $hitos ?? collect(),
        'porcentaje'  => $porcentaje ?? 0,
        'siguienteId' => $siguienteId ?? null,
        'puedeMarcar' => $puedeMarcarHitos ?? false,
        'puedeAprobar' => $puedeAprobarHitos ?? false,
        'ayudas'      => $ayudas ?? [],
      ])
    </div>
  </div>

  {{-- OBSERVACIONES: el listado completo ahora vive en el panel lateral
       (partials.observaciones-checklist), agrupado por sección. --}}

    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion ?? collect(),
        'campos' => $camposObservables ?? [],
      ])
      @include('partials.timeline', ['tipo' => 'agenda', 'id' => $agenda->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>

@if($puedeObservar ?? false)
  <x-modal-observacion
    tipo="agenda"
    :id="$agenda->id"
    :campos="config('punta.campos_observables_agenda')"
    :revisores="$revisores" />
@endif
@endsection