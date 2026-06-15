@extends('layouts.app')
@section('title', 'Control de Acceso (ACL)')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Control de Acceso</h2>
      <p class="nowrap">Gestiona roles, permisos y asignaciones del sistema.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.acl.usuarios') }}" class="btn btn-outline">Roles por usuario</a>
      <a href="{{ route('admin.acl.bitacora') }}" class="btn btn-outline">Bitácora ACL</a>
    </div>
  </div>

  <div class="card">
    <div class="card-body-padded">
      <h3>Roles del sistema</h3>
      <p class="text-muted-sm">Los roles marcados como "sistema" no pueden eliminarse pero sí ajustarse sus permisos.</p>

      <table class="data-table mt-4">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Usuarios</th>
            <th>Permisos</th>
            <th>Tipo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($roles as $rol)
            <tr>
              <td><code>{{ $rol->codigo }}</code></td>
              <td><strong>{{ $rol->nombre }}</strong><br><small class="help-small">{{ $rol->descripcion }}</small></td>
              <td>{{ $rol->usuarios_count }}</td>
              <td>{{ count($matriz[$rol->id] ?? []) }}</td>
              <td>
                @if($rol->esDeSistema())
                  <span class="chip chip-gray">Sistema</span>
                @else
                  <span class="chip chip-success">Personalizado</span>
                @endif
              </td>
              <td>
                <a href="{{ route('admin.acl.editar-rol', $rol) }}" class="btn btn-outline btn-sm">Editar permisos</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-body-padded">
      <h3>Catálogo de permisos por módulo</h3>
      <p class="text-muted-sm">Permisos disponibles agrupados por módulo. Total: {{ $permisos->flatten()->count() }} permisos.</p>

      @foreach($permisos as $modulo => $items)
        <div class="section-divided">
          <span class="label-meta">{{ strtoupper($modulo) }}</span>
          <ul style="margin:8px 0 0;padding-left:16px">
            @foreach($items as $p)
              <li><code>{{ $p->codigo }}</code> — <span class="text-muted-sm">{{ $p->descripcion }}</span></li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  </div>

</div>
@endsection
