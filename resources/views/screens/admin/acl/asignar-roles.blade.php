@extends('layouts.app')
@section('title', 'Asignar roles a usuario')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Asignar roles a {{ $usuario->name }}</h2>
      <p class="nowrap">{{ $usuario->email }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.acl.usuarios') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.acl.guardar-roles', $usuario) }}" id="formAsignarRoles">
    @csrf @method('PUT')

    <div class="card">
      <div class="card-body-padded">
        <p class="text-muted-sm mb-4">Un usuario puede tener varios roles. Los permisos del usuario son la unión de los permisos de todos sus roles.</p>

        <div class="check-grid">
          @foreach($roles as $rol)
            <label class="check-card">
              <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
                     {{ in_array($rol->id, $asignados) ? 'checked' : '' }}>
              <div>
                <strong>{{ $rol->nombre }}</strong>
                <span>{{ $rol->descripcion }}</span>
                <small class="help-small">{{ $rol->permisos()->count() }} permisos</small>
              </div>
            </label>
          @endforeach
        </div>
      </div>

      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.acl.usuarios') }}" class="btn btn-outline">Cancelar</a>
        <button type="button" class="btn" onclick="document.getElementById('confirmModal').classList.add('open')">
          Guardar asignación
        </button>
      </div>
    </div>
  </form>

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Confirmar asignación de roles?</h3>
      <p>Se actualizarán los roles de <strong>{{ $usuario->name }}</strong>. La acción quedará registrada en la bitácora ACL.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formAsignarRoles').submit()">Sí, guardar</button>
      </div>
    </div>
  </div>

</div>
@endsection
