@extends('layouts.app')
@section('title', 'Detalle de Acción')

@section('content')
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
      <span class="badge {{ match($agenda->estatus) {
        'completado' => 'success-b',
        'en_observacion','en_correccion' => 'warning-b',
        'en_firma' => 'info-b',
        default => ''
      } }}" style="text-transform:uppercase">@estatus($agenda->estatus)</span>
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
            <button type="submit" class="btn btn-sm" onclick="return confirm('¿Enviar a revisión?')">Enviar a revisión</button>
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
        <div class="modal-data-item"><span>Tipo de acción</span><strong>{{ ucfirst($agenda->tipo) }}</strong></div>
        <div class="modal-data-item"><span>Acción registrada</span><strong>{{ $agenda->descripcion }}</strong></div>
        <div class="modal-data-item"><span>Responsable</span><strong>{{ $agenda->responsable ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Estatus</span><strong>@estatus($agenda->estatus)</strong></div>
        <div class="modal-data-item"><span>Dependencia</span><strong>{{ $agenda->dependencia->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Registrado por</span><strong>{{ $agenda->creador->name ?? '—' }}</strong></div>
      </div>
    </div>
  </div>

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
        <div class="modal-data-item"><span>Indicador</span><strong>{{ $agenda->indicador ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Fecha de inicio</span><strong>{{ $agenda->fecha_inicio ? \Carbon\Carbon::parse($agenda->fecha_inicio)->format('d/m/Y') : '—' }}</strong></div>
        <div class="modal-data-item"><span>Fecha compromiso</span><strong>{{ $agenda->fecha_compromiso ? \Carbon\Carbon::parse($agenda->fecha_compromiso)->format('d/m/Y') : '—' }}</strong></div>
      </div>
    </div>
  </div>

  {{-- AVANCE --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Avance y evidencias</h3><p>Seguimiento del cumplimiento de la acción.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Avance y evidencias')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;font-weight:600">
        <span>Avance general</span><span>0%</span>
      </div>
      <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-bottom:16px">
        <div style="height:100%;width:0%;background:var(--accent);border-radius:4px"></div>
      </div>
      <div class="cal-empty-state">No hay evidencias registradas aún.</div>
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
