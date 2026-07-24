@extends('layouts.app')
@section('title', 'Parámetros del costo burocrático')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Parámetros del cálculo</h2>
      <p class="nowrap">Variables configurables que usa el sistema para calcular el costo burocrático.</p>
    </div>
  </div>

  <div class="card">
    <div class="card-body-padded">
      <p class="text-muted-sm">Si un parámetro está inactivo, el sistema usa el valor por defecto del código. Modificar estos valores recalculará automáticamente los costos cuando se editen los trámites.</p>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Clave</th>
          <th>Valor</th>
          <th>Unidad</th>
          <th>Fuente</th>
          <th>Estado</th>
          <th>Actualizado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        @foreach($parametros as $p)
          <tr>
            <td><code>{{ $p->clave }}</code></td>
            <td><strong>{{ number_format($p->valor, 4) }}</strong></td>
            <td>{{ $p->unidad }}</td>
            <td><small class="help-small">{{ $p->fuente ?? '' }}</small></td>
            <td>
              @if($p->activo)
                <span class="chip chip-success">Activo</span>
              @else
                <span class="chip chip-gray">Inactivo</span>
              @endif
            </td>
            <td>
              <small class="help-small">
                {{ $p->updated_at->format('d/m/Y') }}<br>
                {{ $p->actualizadoPor->name ?? '' }}
              </small>
            </td>
            <td>
              <a href="{{ route('admin.parametros.editar', $p) }}" class="btn btn-outline btn-sm">Editar</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

</div>
@endsection
