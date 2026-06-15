@extends('layouts.app')
@section('title', 'Bitácora ACL')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Bitácora del Control de Acceso</h2>
      <p class="nowrap">Historial de cambios de roles y permisos.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.acl.index') }}" class="btn btn-outline">Volver a ACL</a>
    </div>
  </div>

  <div class="card">
    @if($movimientos->isEmpty())
      <div class="text-center u-empty-lg">
        <h3>Sin movimientos registrados</h3>
        <p class="text-muted-sm">Aquí se mostrarán todas las asignaciones y revocaciones de roles y permisos.</p>
      </div>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Usuario afectado</th>
            <th>Rol / Permiso</th>
            <th>Ejecutado por</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          @foreach($movimientos as $m)
            <tr>
              <td>{{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td>
              <td><strong>{{ ucfirst(str_replace('_', ' ', $m->accion)) }}</strong></td>
              <td>{{ $m->usuario_afectado ?? '—' }}</td>
              <td>
                @if($m->rol_nombre)
                  <span class="chip chip-gray">{{ $m->rol_nombre }}</span>
                @endif
                @if($m->permiso_codigo)
                  <code class="help-small">{{ $m->permiso_codigo }}</code>
                @endif
              </td>
              <td>{{ $m->ejecutor_nombre ?? '—' }}</td>
              <td class="help-small">{{ $m->ip_address ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="card-body-padded">{{ $movimientos->links() }}</div>
    @endif
  </div>

</div>
@endsection
