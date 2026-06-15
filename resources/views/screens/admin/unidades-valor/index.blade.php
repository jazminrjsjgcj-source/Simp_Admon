@extends('layouts.app')
@section('title', 'Unidades de valor')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Unidades de valor de referencia</h2>
      <p class="nowrap">Valores en pesos de UMA, salario mínimo y UDI por año.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.unidades-valor.crear') }}" class="btn">Registrar nuevo</a>
    </div>
  </div>

  <div class="card">
    <div class="card-body-padded">
      <p class="text-muted-sm">Estos valores se usan para convertir umbrales y montos del costo burocrático entre unidades. Deben actualizarse cada año según publicación oficial.</p>
    </div>

    @if($unidades->isEmpty())
      <div class="text-center u-empty-lg">
        <h3>Sin unidades registradas</h3>
        <p class="text-muted-sm">Registre los valores de UMA, salario mínimo y UDI para el año vigente.</p>
        <a href="{{ route('admin.unidades-valor.crear') }}" class="btn mt-4">Registrar el primero</a>
      </div>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Unidad</th>
            <th>Año</th>
            <th>Valor en pesos</th>
            <th>Vigencia</th>
            <th>Fuente</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($unidades as $u)
            <tr>
              <td><strong>{{ $u->unidad }}</strong></td>
              <td>{{ $u->anio }}</td>
              <td>${{ number_format($u->valor_pesos, 4) }}</td>
              <td>
                <small class="help-small">
                  @if($u->vigencia_inicio) Desde {{ $u->vigencia_inicio->format('d/m/Y') }} @endif
                  @if($u->vigencia_fin)    hasta {{ $u->vigencia_fin->format('d/m/Y') }} @endif
                  @if(!$u->vigencia_inicio && !$u->vigencia_fin) Sin fechas @endif
                </small>
              </td>
              <td><small class="help-small">{{ $u->fuente ?? '—' }}</small></td>
              <td>
                @if($u->activo)
                  <span class="chip chip-success">Activo</span>
                @else
                  <span class="chip chip-gray">Inactivo</span>
                @endif
              </td>
              <td>
                <a href="{{ route('admin.unidades-valor.editar', $u) }}" class="btn btn-outline btn-sm">Editar</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>
@endsection
