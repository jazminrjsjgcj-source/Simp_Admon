@extends('layouts.app')
@section('title', 'Biblioteca de Digitalización')

@section('content')
<div class="page-default">

  {{-- ENCABEZADO --}}
  <div class="screen-head">
    <div>
      <h2>Biblioteca de Digitalización</h2>
      <p>Trámites y servicios con su estado de flujo, reingeniería y digitalización.</p>
    </div>
  </div>

  {{-- FILTROS --}}
  <div class="bdig-filtros">
    @php
      $filtros = [
        'todos'             => ['label' => 'Todos',             'icono' => 'ti-list'],
        'sin_flujo'         => ['label' => 'Sin flujo',         'icono' => 'ti-alert-circle'],
        'flujo_aprobado'    => ['label' => 'Flujo aprobado',    'icono' => 'ti-circle-check'],
        'en_agenda'         => ['label' => 'En Agenda',         'icono' => 'ti-calendar'],
        'directa'           => ['label' => 'Reingeniería directa', 'icono' => 'ti-bolt'],
        'pendiente_firmas'  => ['label' => 'Pendiente firmas',  'icono' => 'ti-pencil'],
        'firmada'           => ['label' => 'Firmada',           'icono' => 'ti-certificate'],
        'en_digitalizacion' => ['label' => 'En digitalización', 'icono' => 'ti-device-laptop'],
        'digitalizado'      => ['label' => 'Digitalizado',      'icono' => 'ti-check'],
        'con_cambios'       => ['label' => 'Con cambios',        'icono' => 'ti-alert-triangle'],
      ];
    @endphp
    @foreach($filtros as $clave => $info)
      <a href="{{ route('digitalizacion.index', ['filtro' => $clave]) }}"
         class="bdig-filtro {{ $filtro === $clave ? 'activo' : '' }}">
        <i class="ti {{ $info['icono'] }}"></i>
        {{ $info['label'] }}
        @if(isset($contadores[$clave]))
          <span class="bdig-filtro-count">{{ $contadores[$clave] }}</span>
        @endif
      </a>
    @endforeach
  </div>

  {{-- TABLA --}}
  @if($tramites->isEmpty())
    <div class="empty-state" style="padding:48px 24px;text-align:center">
      <i class="ti ti-database-off" style="font-size:48px;color:var(--muted);display:block;margin-bottom:12px"></i>
      <strong>No hay trámites con este filtro</strong>
      <p style="color:var(--muted);font-size:13px">Intenta con otro filtro o verifica que existan trámites registrados.</p>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Naturaleza</th>
            <th>Dependencia</th>
            <th>Flujo</th>
            <th>Origen</th>
            <th>Reingeniería</th>
            <th>Digitalización</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($tramites as $t)
            <tr>
              <td>
                <a href="{{ route('digitalizacion.show', $t) }}" class="bdig-nombre">
                  {{ $t->nombre_oficial }}
                </a>
                @if($t->homoclave)
                  <span class="bdig-homoclave">{{ $t->homoclave }}</span>
                @endif
              </td>
              <td>
                <span class="badge-nat badge-nat-{{ $t->naturaleza }}">
                  {{ $t->naturalezaLegible() }}
                </span>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                {{ $t->dependencia->nombre ?? '' }}
              </td>
              <td>
                @php
                  $flujoClase = match($t->flujo_estado) {
                    'flujo_aprobado' => 'chip-green',
                    'flujo_en_revision', 'flujo_en_captura' => 'chip-amber',
                    'flujo_observado' => 'chip-red',
                    default => 'chip-gray',
                  };
                @endphp
                <span class="chip {{ $flujoClase }}">{{ $t->flujoEstadoLegible() }}</span>
              </td>
              <td style="font-size:12px">
                @if($t->digitalizacion_origen === 'agenda')
                  <span class="chip chip-blue">Agenda</span>
                @elseif($t->digitalizacion_origen === 'directa')
                  <span class="chip chip-amber">Directa</span>
                @else
                  <span style="color:var(--muted)">—</span>
                @endif
              </td>
              <td>
                @if($t->relationLoaded('reingenieriaActiva') && $t->reingenieriaActiva)
                  @php
                    $rClase = match($t->reingenieriaActiva->estado) {
                      'reingenieria_firmada' => 'chip-green',
                      'pendiente_firmas', 'aprobada_para_firma' => 'chip-amber',
                      'reingenieria_observada' => 'chip-red',
                      default => 'chip-gray',
                    };
                  @endphp
                  <span class="chip {{ $rClase }}">{{ $t->reingenieriaActiva->estadoLegible() }}</span>
                @else
                  <span style="color:var(--muted);font-size:12px">Sin reingeniería</span>
                @endif
              </td>
              <td>
                @php
                  $dClase = match($t->digitalizacion_estado) {
                    'digitalizado' => 'chip-green',
                    'en_digitalizacion', 'lista_para_digitalizacion' => 'chip-amber',
                    'requiere_revision_por_cambio' => 'chip-red',
                    default => 'chip-gray',
                  };
                @endphp
                <span class="chip {{ $dClase }}">{{ $t->digitalizacionEstadoLegible() }}</span>
              </td>
              <td>
                <a href="{{ route('digitalizacion.show', $t) }}" class="btn btn-outline" style="font-size:11px;padding:4px 10px">
                  Ver
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{ $tramites->links() }}
  @endif

</div>

<style>
  .bdig-filtros {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 20px;
  }
  .bdig-filtro {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border: 1px solid var(--surface-high);
    border-radius: var(--radius-pill);
    font-size: 12px;
    color: var(--muted);
    text-decoration: none;
    transition: all .15s ease;
  }
  .bdig-filtro:hover { border-color: var(--primary); color: var(--text); }
  .bdig-filtro.activo {
    background: var(--surface-tint);
    border-color: var(--primary);
    color: var(--primary);
    font-weight: 600;
  }
  .bdig-filtro > i { font-size: 14px; }
  .bdig-filtro-count {
    background: var(--surface-high);
    color: var(--muted);
    padding: 0 6px;
    border-radius: var(--radius-pill);
    font-size: 10px;
    font-weight: 600;
    min-width: 18px;
    text-align: center;
  }
  .bdig-filtro.activo .bdig-filtro-count {
    background: var(--primary);
    color: white;
  }
  .bdig-nombre {
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    font-size: 13px;
  }
  .bdig-nombre:hover { text-decoration: underline; }
  .bdig-homoclave {
    display: block;
    font-size: 11px;
    color: var(--muted);
    font-family: var(--font-mono, monospace);
  }
  .badge-nat {
    padding: 2px 8px;
    border-radius: var(--radius-pill);
    font-size: 11px;
    font-weight: 600;
  }
  .badge-nat-tramite  { background: var(--surface-tint); color: var(--primary); }
  .badge-nat-servicio { background: #dbeafe; color: #1d4ed8; }
  .chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: var(--radius-pill);
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }
  .chip-green { background: #d1fae5; color: #065f46; }
  .chip-amber { background: #fef3c7; color: #92400e; }
  .chip-red   { background: #fee2e2; color: #991b1b; }
  .chip-blue  { background: #dbeafe; color: #1d4ed8; }
  .chip-gray  { background: var(--surface-low, #f3f4f6); color: var(--muted); }

  @media (max-width: 900px) {
    .bdig-filtros { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 4px; }
    table { font-size: 12px; }
  }
</style>
@endsection
