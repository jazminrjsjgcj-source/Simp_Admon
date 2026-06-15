@extends('layouts.app')
@section('title', 'Roles por usuario')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Roles asignados por usuario</h2>
      <p class="nowrap">Asigna o revoca roles individualmente.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.acl.index') }}" class="btn btn-outline">Volver a ACL</a>
    </div>
  </div>

  <div class="card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Email</th>
          <th>Dependencia</th>
          <th>Roles ACL</th>
          <th>Rol legacy</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        @foreach($usuarios as $u)
          <tr>
            <td><strong>{{ $u->name }}</strong></td>
            <td>{{ $u->email }}</td>
            <td>{{ $u->dependencia->nombre ?? '—' }}</td>
            <td>
              @forelse($u->roles as $r)
                <span class="chip chip-success">{{ $r->nombre }}</span>
              @empty
                <span class="chip chip-gray">Sin roles</span>
              @endforelse
            </td>
            <td><code class="help-small">{{ $u->rol }}</code></td>
            <td>
              <a href="{{ route('admin.acl.asignar-roles', $u) }}" class="btn btn-outline btn-sm">Asignar roles</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="card-body-padded">{{ $usuarios->links() }}</div>
  </div>

</div>
@endsection
