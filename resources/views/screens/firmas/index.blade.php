@extends('layouts.app')
@section('title', 'Firmas digitales')

@section('content')
@php
  // La revisora y el jurídico aprueban; el sujeto y el enlace firman.
  $esAprobador = auth()->user()->isAnyRol(['revisora', 'juridico']);
  $accionVerbo = $esAprobador ? 'aprobar' : 'firmar';
@endphp
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Firmas digitales</h2>
      <p class="nowrap">Aceptaciones y validaciones de trámites listos para acuse.</p>
    </div>
  </div>

  <div class="card">
    @if($tramitesFirmables->isEmpty())
      <div class="text-center u-empty-lg">
        <h3>No hay trámites pendientes de firma</h3>
        <p class="text-muted-sm">Los trámites aparecerán aquí cuando sean aprobados.</p>
      </div>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Homoclave</th>
            <th>Trámite</th>
            <th>Dependencia</th>
            <th>Estatus</th>
            <th>Firmas registradas</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tramitesFirmables as $t)
            @php
              $firmasActivas = $t->firmas->where('estatus', 'activa');
            @endphp
            <tr>
              <td><code>{{ $t->homoclave ?? '—' }}</code></td>
              <td><strong>{{ $t->nombre_oficial }}</strong></td>
              <td>{{ $t->dependencia->nombre ?? '—' }}</td>
              <td>
                @if($t->estatus === 'completado')
                  <span class="chip chip-success">Firmado</span>
                @else
                  <span class="chip chip-amber">Pendiente de firma</span>
                @endif
              </td>
              <td>
                @if($firmasActivas->isEmpty())
                  <span class="chip chip-gray">Sin firmas</span>
                @else
                  <span class="chip chip-success">{{ $firmasActivas->count() }} firma(s)</span>
                @endif
              </td>
              <td>
                <a href="{{ route('firmas.mostrar', ['tipo' => 'tramite', 'id' => $t->id]) }}"
                   class="btn btn-outline btn-sm">Ver / {{ $accionVerbo }}</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>
@endsection
