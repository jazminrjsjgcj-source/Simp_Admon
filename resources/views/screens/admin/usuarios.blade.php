@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="page-wide">
  <div class="screen-head">
    <div><h2 class="nowrap">Usuarios</h2><p class="nowrap">Administra usuarios, roles y accesos del sistema.</p></div>
    <div class="head-actions"><a href="{{ route('admin.usuarios.create') }}" class="btn">Nuevo Usuario</a></div>
  </div>
  <div class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Dependencia</th><th>Estatus</th><th class="table-action-cell">Acciones</th></tr></thead><tbody>
    @forelse($usuarios as $u)
      <tr>
        <td>USR-{{ str_pad($u->id,3,'0',STR_PAD_LEFT) }}</td>
        <td><strong>{{ $u->name }}</strong><br><small>{{ $u->email }}</small></td>
        <td>
          <span class="badge">{{ ucfirst($u->rol) }}</span>
          @if($u->roles->count())
            @foreach($u->roles as $r)
              <span class="chip chip-success u-text-xxs">{{ $r->nombre }}</span>
            @endforeach
          @endif
        </td>
        <td>{{ $u->dependencia->nombre ?? '' }}</td>
        <td><span class="badge {{ $u->activo?'success-b':'danger-b' }}">{{ $u->activo?'Activo':'Inactivo' }}</span></td>
        <td class="table-action-cell"><div class="table-actions">
          <a href="{{ route('admin.usuarios.edit',$u) }}" class="btn table-action-btn btn-outline">Editar</a>
          @if($u->id !== auth()->id())
            <button type="button" class="btn table-action-btn danger btn-outline"
              onclick="confirmDelete(
                '{{ route('admin.usuarios.destroy',$u) }}',
                '¿Eliminar usuario?',
                '{{ addslashes($u->name) }} perderá acceso al sistema.'
              )">Eliminar</button>
          @endif
        </div></td>
      </tr>
    @empty
      <tr><td colspan="6" class="u-text-center cal-empty-state">No hay usuarios registrados.</td></tr>
    @endforelse
  </tbody></table></div>
  <div class="u-pad-card-sm">{{ $usuarios->links() }}</div></div>
</div>
@endsection
