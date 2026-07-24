@extends('layouts.app')
@section('title', 'Umbrales configurados')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Umbrales configurados</h2>
      <p class="nowrap">Montos contra los cuales el sistema clasifica el impacto de cada trámite.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.umbrales.crear') }}" class="btn">Cargar nuevo umbral</a>
    </div>
  </div>

  <form method="GET" action="{{ route('admin.umbrales.index') }}" class="card">
    <div class="card-body-padded wizard-fields">
      <div class="field">
        <label class="label-meta">Sector</label>
        <select name="sector" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($sectores as $s)
            <option value="{{ $s->id }}" {{ request('sector') == $s->id ? 'selected' : '' }}>
              {{ $s->codigo }} — {{ Str::limit($s->nombre, 50) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label class="label-meta">Estado</label>
        <select name="estatus" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="activo"   {{ request('estatus') === 'activo'   ? 'selected' : '' }}>Activo</option>
          <option value="inactivo" {{ request('estatus') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>
    </div>
  </form>

  <div class="card">
    @if($umbrales->isEmpty())
      <div class="text-center u-empty-lg">
        <h3>Sin umbrales configurados</h3>
        <p class="text-muted-sm">Sin umbral configurado, los trámites quedan con impacto "no determinado".</p>
        <a href="{{ route('admin.umbrales.crear') }}" class="btn mt-4">Cargar el primer umbral</a>
      </div>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Sector / Subsector</th>
            <th>Año</th>
            <th>Monto base</th>
            <th>Monto en pesos</th>
            <th>UMA</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($umbrales as $u)
            <tr>
              <td>
                @if($u->sector)
                  <strong>{{ $u->sector->codigo }}</strong>
                  <small class="help-small">{{ Str::limit($u->sector->nombre, 40) }}</small>
                @else
                  <em class="help-small">Todos los sectores</em>
                @endif
                @if($u->subsector)
                  <br><small class="help-small">→ {{ $u->subsector->codigo }} {{ Str::limit($u->subsector->nombre, 35) }}</small>
                @endif
              </td>
              <td>{{ $u->anio }}</td>
              <td>{{ number_format($u->monto_base, 2) }} <small class="help-small">{{ $u->unidad_base }}</small></td>
              <td><strong>${{ number_format($u->monto_pesos, 2) }}</strong></td>
              <td>{{ $u->monto_uma ? number_format($u->monto_uma, 2) : '' }}</td>
              <td>
                @if($u->estaActivo())
                  <span class="chip chip-success">Activo</span>
                @else
                  <span class="chip chip-gray">Inactivo</span>
                @endif
              </td>
              <td>
                <a href="{{ route('admin.umbrales.editar', $u) }}" class="btn btn-outline btn-sm">Editar</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="card-body-padded">{{ $umbrales->links() }}</div>
    @endif
  </div>

</div>
@endsection
