@extends('layouts.app')
@section('title', 'Detalle de Propuesta')

@section('content')
@php $d = $detalles ?? []; @endphp
<div class="page-default">

  <div>
    <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">← Volver a la lista</a>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">

  {{-- HEADER --}}
  <div class="card card-pad">
    <div class="row-center mb-4">

      {{--
        Badge de estatus: usa los 5 estados reales del flujo de vida.
        borrador → en_observacion → en_correccion → en_firma → completado
      --}}
      <span class="badge {{ match($propuesta->estatus ?? 'borrador') {
        'completado'                    => 'success-b',
        'en_firma'                      => 'info-b',
        'en_observacion','en_correccion'=> 'warning-b',
        default                         => ''
      } }}" style="text-transform:uppercase">
        {{ str_replace('_', ' ', $propuesta->estatus ?? 'borrador') }}
      </span>

      <span class="text-primary-bold">
        @dato($propuesta->folio)
      </span>

      <div class="actions-right">
        {{--
          Botón Editar: el enlace solo puede editar su propia propuesta.
          El admin puede editar cualquiera.
          Antes: la condición de admin estaba anidada dentro de la de enlace,
          lo que hacía imposible que admin llegara al botón.
        --}}
        @if(
          auth()->user()->isRol(App\Models\User::ROL_ADMIN) ||
          (
            auth()->user()->isRol(App\Models\User::ROL_ENLACE) &&
            $propuesta->created_by === auth()->id()
          )
        )
          <a href="{{ route('propuestas.edit', $propuesta) }}" class="btn btn-outline btn-sm">Editar</a>
        @endif

        @if(auth()->user()->isRol(App\Models\User::ROL_REVISORA))
          {{-- Observar ahora se hace con los botones "+ Agregar observación" por sección, más abajo en este mismo detalle --}}
        @endif
      </div>
    </div>

    <h1 class="title-primary">{{ $propuesta->nombre ?: 'Sin nombre' }}</h1>
    <p class="text-muted-sm">
      {{ $propuesta->tipo_regulacion ?? '—' }} ·
      {{ $propuesta->dependencia->nombre ?? '—' }} ·
      @if($propuesta->fecha_tentativa)
        Presentación tentativa: {{ \Carbon\Carbon::parse($propuesta->fecha_tentativa)->format('d/m/Y') }}
      @endif
    </p>
  </div>

  {{-- RUBRO 1-4: Datos generales --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Datos generales</h3><p>Campos 1 al 4 — Responsable e identificación.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Datos generales')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item"><span>1. Responsable</span><strong>{{ $d['responsable_nombre'] ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>1.1 Cargo</span><strong>{{ $d['responsable_cargo'] ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>2. Tipo de regulación</span><strong>{{ $propuesta->tipo_regulacion ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>3. Materia</span><strong>{{ $d['materia'] ?? '—' }}</strong></div>
        {{--
          Bug 3 corregido: el HTML no acepta dos atributos class en el mismo elemento.
          La clase u-span-2 se une al class existente en vez de escribir class="... ..."
        --}}
        <div class="modal-data-item u-span-2"><span>4. Nombre preliminar</span><strong>{{ $propuesta->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Dependencia responsable</span><strong>{{ $propuesta->dependencia->nombre ?? '—' }}</strong></div>
        <div class="modal-data-item"><span>Sujeto Obligado</span><strong>{{ $d['sujeto_obligado_nombre'] ?? \App\Models\SujetoObligado::vigenteDe($propuesta->dependencia_id)?->nombre ?? '—' }}</strong></div>
      </div>
    </div>
  </div>

  {{-- RUBRO 5-9: Justificación --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Justificación y problemática</h3><p>Campos 5 al 9.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Justificación y problemática')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded d-grid gap-4">
      @foreach([
        '5. Sectores o grupos impactados'          => $d['sectores_impactados'] ?? null,
        '6. Fecha tentativa de presentación'        => $propuesta->fecha_tentativa
                                                         ? \Carbon\Carbon::parse($propuesta->fecha_tentativa)->format('d/m/Y')
                                                         : null,
        '7. Justificación para emitir'             => $d['justificacion'] ?? null,
        '8. Problemática que se pretende resolver'  => $d['problematica'] ?? null,
        '9. Alternativas consideradas'             => $d['alternativas'] ?? null,
      ] as $label => $value)
        @if($value)
          <div>
            <p class="label-meta">{{ $label }}</p>
            <p class="text-muted-sm">{{ $value }}</p>
          </div>
        @endif
      @endforeach
    </div>
  </div>

  {{-- RUBRO 10-16: Beneficios e impactos --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Beneficios, costos e impactos</h3><p>Campos 10 al 16.</p></div>
      @if($puedeObservar ?? false)
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Beneficios, costos e impactos')">+ Agregar observación</button>
      @endif
    </div>
    <div class="card-body-padded d-grid gap-4">
      @foreach([
        '10. Posibles beneficios'                       => $d['beneficios'] ?? null,
        '11. Posibles costos burocráticos'              => $d['costos_burocraticos'] ?? null,
        '12. Trámites y servicios en los que impacta'   => $d['tramites_impacta'] ?? null,
        '13. Acciones de simplificación asociadas'      => $d['acciones_simplificacion'] ?? null,
        '14. Acciones de digitalización asociadas'      => $d['acciones_digitalizacion'] ?? null,
        '15. Fundamento jurídico'                       => $d['fundamento_juridico'] ?? null,
        '16. Impacto en comercio o inversión'           => $d['impacto_comercio'] ?? null,
      ] as $label => $value)
        @if($value)
          <div>
            <p class="label-meta">{{ $label }}</p>
            <p class="text-muted-sm">{{ $value }}</p>
          </div>
        @endif
      @endforeach
    </div>
  </div>

  {{-- RUBRO 17: Anexos --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Anexos y observaciones</h3><p>Campo 17.</p></div>
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item">
          <span>¿Presenta proyecto?</span>
          <strong>{{ ucfirst($d['presenta_proyecto'] ?? 'No') }}</strong>
        </div>
        <div class="modal-data-item">
          <span>Determinación AIR</span>
          <strong>{{ ucfirst(str_replace('_', ' ', $propuesta->determinacion_air ?? 'Pendiente')) }}</strong>
        </div>
      </div>
      @if(!empty($d['observaciones']))
        <div class="section-divided">
          <p class="label-meta">Observaciones</p>
          <p class="u-text-sm">{{ $d['observaciones'] }}</p>
        </div>
      @endif
    </div>
  </div>

  {{-- PANEL AIR --}}
  <div class="card">
    @php $air = $propuesta->air; $exencion = $propuesta->exencion; @endphp
    <div class="panel-head">
      <div><h3>Determinación AIR</h3><p>Análisis de Impacto Regulatorio — Art. 36–38 LNETB.</p></div>
      <div class="panel-head-actions">
        @if($air)
          <span class="badge {{ $air->estadoBadge() }}">AIR {{ ucfirst(str_replace('_', ' ', $air->estatus)) }}</span>
        @else
          <span class="badge">Sin AIR</span>
        @endif
        @if($puedeObservar ?? false)
          <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalObservacion('Determinación AIR')">+ Agregar observación</button>
        @endif
      </div>
    </div>

    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item">
          <span>Determinación</span>
          <strong>{{ ucfirst(str_replace('_', ' ', $propuesta->determinacion_air ?? 'Pendiente')) }}</strong>
        </div>
        @if($air)
          <div class="modal-data-item">
            <span>Folio AIR</span>
            <strong>{{ $air->folio ?? '—' }}</strong>
          </div>
          <div class="modal-data-item">
            <span>Estatus AIR</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $air->estatus)) }}</strong>
          </div>
          @if($air->estatus === 'dictaminado')
            <div class="modal-data-item">
              <span>Dictamen</span>
              <span class="badge {{ $air->dictamen === 'favorable' ? 'success-b' : 'danger-b' }}">
                {{ ucfirst(str_replace('_', ' ', $air->dictamen)) }}
              </span>
            </div>
            @if($air->dictamen_observaciones)
              <div class="modal-data-item u-span-2">
                <span>Observaciones del dictamen</span>
                <p class="u-text-sm">{{ $air->dictamen_observaciones }}</p>
              </div>
            @endif
          @endif
        @endif
        @if($exencion)
          <div class="modal-data-item">
            <span>Exención</span>
            <span class="badge {{ $exencion->estadoBadge() }}">{{ ucfirst($exencion->estatus) }}</span>
          </div>
          <div class="modal-data-item u-span-2">
            <span>Fracciones invocadas</span>
            <strong>{{ $exencion->fraccionesTexto() }}</strong>
          </div>
        @endif
      </div>
    </div>

    <div class="card-foot">
      @php $user = auth()->user(); @endphp

      {{-- Enlace o admin de la misma dependencia: registrar o editar AIR --}}
      @if($user->isAnyRol([App\Models\User::ROL_ENLACE, App\Models\User::ROL_ADMIN]) && $user->esDeSuDependencia($propuesta))
        @if(!$air && $propuesta->determinacion_air !== 'exento')
          <a href="{{ route('air.formulario', $propuesta) }}" class="btn btn-sm">Registrar AIR</a>
        @elseif($air && in_array($air->estatus, ['borrador', 'enviado']))
          <a href="{{ route('air.formulario', $propuesta) }}" class="btn btn-outline btn-sm">Editar AIR</a>
        @endif
        @if(!$exencion && $propuesta->determinacion_air !== 'requiere_air')
          <a href="{{ route('air.exencion.formulario', $propuesta) }}" class="btn btn-outline btn-sm">Solicitar exención</a>
        @endif
      @endif

      {{--
        Revisora: botón para dictaminar.
        Bug 1 corregido: el permiso era 'revision.propuestas.aprobar' (inexistente).
        El correcto es 'agenda_regulatoria.aprobar' que sí está en config/acl.php.
      --}}
      @if($user->tienePermiso('agenda_regulatoria.aprobar') && $air && $air->estatus === 'enviado')
        <button type="button" class="btn btn-sm btn-success" onclick="abrirDictamen()">
          Emitir dictamen
        </button>
      @endif
    </div>
  </div>

  {{-- Modal de dictamen — solo visible para la revisora cuando hay AIR enviado --}}
  @if(auth()->user()->tienePermiso('agenda_regulatoria.aprobar') && $propuesta->air?->estatus === 'enviado')
  <div class="confirm-modal-backdrop" id="modalDictamen">
    <div class="confirm-modal">
      <h3 style="margin:0">Emitir dictamen del AIR</h3>
      <p class="u-text-muted u-text-sm">Folio: {{ $propuesta->air?->folio }}</p>
      <form method="POST" action="{{ route('air.dictaminar', $propuesta) }}">
        @csrf
        <div class="wizard-fields" style="margin-top:16px">
          <div class="field span-2">
            <label>Resolución *</label>
            <div style="display:flex;gap:12px;margin-top:8px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="radio" name="dictamen" value="favorable" required> Favorable
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="radio" name="dictamen" value="no_favorable"> No favorable
              </label>
            </div>
          </div>
          <div class="field span-2">
            <label for="dictamen_obs">Observaciones</label>
            <textarea id="dictamen_obs" name="dictamen_observaciones" rows="4"
              placeholder="Fundamento del dictamen..."></textarea>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
          <button type="button" class="btn btn-outline" onclick="cerrarDictamen()">Cancelar</button>
          <button type="submit" class="btn btn-success">Emitir dictamen</button>
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- Registro --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Registro</h3><p>Trazabilidad del registro.</p></div>
    </div>
    <div class="card-body-padded">
      <div class="modal-grid">
        <div class="modal-data-item">
          <span>Folio</span>
          <strong>REG-{{ str_pad($propuesta->id, 3, '0', STR_PAD_LEFT) }}</strong>
        </div>
        <div class="modal-data-item">
          <span>Estatus</span>
          <strong>{{ ucfirst(str_replace('_', ' ', $propuesta->estatus ?? 'borrador')) }}</strong>
        </div>
        <div class="modal-data-item">
          <span>Registrado por</span>
          <strong>{{ $propuesta->creador->name ?? '—' }}</strong>
        </div>
        <div class="modal-data-item">
          <span>Fecha de registro</span>
          <strong>{{ $propuesta->created_at->format('d/m/Y H:i') }}</strong>
        </div>
      </div>
    </div>
  </div>

    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion ?? collect(),
        'campos' => $camposObservables ?? [],
      ])
      @include('partials.timeline', ['tipo' => 'propuesta', 'id' => $propuesta->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>

@if($puedeObservar ?? false)
  <x-modal-observacion
    tipo="propuesta_regulatoria"
    :id="$propuesta->id"
    :campos="config('punta.campos_observables_propuesta')"
    :revisores="$revisores" />
@endif
@endsection

@push('scripts')
<script>
function abrirDictamen()  { document.getElementById('modalDictamen').classList.add('open'); }
function cerrarDictamen() { document.getElementById('modalDictamen').classList.remove('open'); }
</script>
@endpush
