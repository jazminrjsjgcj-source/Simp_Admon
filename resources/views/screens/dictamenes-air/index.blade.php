@extends('layouts.app')
@section('title', 'Dictámenes AIR')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Dictámenes AIR</h2>
      <p class="nowrap">Análisis de Impacto Regulatorio y exenciones pendientes de tu dictamen.</p>
    </div>
  </div>

  {{-- ───────────────────────────────────────────────
       AIR enviados, esperando dictamen favorable/no favorable
  ─────────────────────────────────────────────── --}}
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>AIR pendientes de dictamen ({{ $airsPendientes->count() }})</h3>
        <p>Análisis enviados por las dependencias que esperan resolución.</p>
      </div>
    </div>

    @if($airsPendientes->isEmpty())
      <div class="text-center u-pad-card">
        <p class="text-muted-sm">No hay AIR pendientes de dictamen.</p>
      </div>
    @else
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Folio AIR</th>
              <th>Propuesta</th>
              <th>Dependencia</th>
              <th>Fecha de envío</th>
              <th class="table-action-cell">Acción</th>
            </tr>
          </thead>
          <tbody>
            @foreach($airsPendientes as $air)
              <tr>
                <td><code>{{ $air->folio ?? '' }}</code></td>
                <td><strong>{{ Str::limit($air->propuesta->nombre ?? 'Sin propuesta', 50) }}</strong></td>
                <td>{{ $air->propuesta->dependencia->nombre ?? '' }}</td>
                <td>{{ $air->updated_at->format('d/m/Y') }}</td>
                <td class="table-action-cell">
                  <div class="table-actions">
                    <a href="{{ route('propuestas.show', $air->propuesta) }}" class="btn table-action-btn">
                      Dictaminar
                    </a>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{-- ───────────────────────────────────────────────
       Exenciones solicitadas, esperando aprobar/rechazar
  ─────────────────────────────────────────────── --}}
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Exenciones pendientes ({{ $exencionesPendientes->count() }})</h3>
        <p>Solicitudes de exención del AIR (Art. 36 LNETB) que esperan resolución.</p>
      </div>
    </div>

    @if($exencionesPendientes->isEmpty())
      <div class="text-center u-pad-card">
        <p class="text-muted-sm">No hay exenciones pendientes de resolución.</p>
      </div>
    @else
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Propuesta</th>
              <th>Dependencia</th>
              <th>Fracciones invocadas</th>
              <th>Fecha de solicitud</th>
              <th class="table-action-cell">Acción</th>
            </tr>
          </thead>
          <tbody>
            @foreach($exencionesPendientes as $exencion)
              <tr>
                <td><strong>{{ Str::limit($exencion->propuesta->nombre ?? 'Sin propuesta', 50) }}</strong></td>
                <td>{{ $exencion->propuesta->dependencia->nombre ?? '' }}</td>
                <td>{{ Str::limit($exencion->fraccionesTexto(), 40) }}</td>
                <td>{{ $exencion->updated_at->format('d/m/Y') }}</td>
                <td class="table-action-cell">
                  <div class="table-actions">
                    <a href="{{ route('air.exencion.formulario', $exencion->propuesta) }}" class="btn table-action-btn">
                      Resolver
                    </a>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

</div>
@endsection
