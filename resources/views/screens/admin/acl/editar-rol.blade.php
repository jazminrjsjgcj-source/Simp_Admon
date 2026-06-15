@extends('layouts.app')
@section('title', 'Editar permisos del rol')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Permisos del rol: {{ $role->nombre }}</h2>
      <p class="nowrap">{{ $role->descripcion }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.acl.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.acl.actualizar-rol', $role) }}" id="formActualizarRol">
    @csrf @method('PUT')

    <div class="card">
      <div class="card-body-padded">
        @foreach($permisos as $modulo => $items)
          <div class="section-divided">
            <span class="label-meta">{{ strtoupper($modulo) }}</span>
            <div class="check-grid mt-2">
              @foreach($items as $p)
                <label class="check-card">
                  <input type="checkbox" name="permisos[]" value="{{ $p->id }}"
                         {{ in_array($p->id, $asignados) ? 'checked' : '' }}>
                  <div>
                    <strong>{{ $p->accion }}</strong>
                    <span>{{ $p->descripcion }}</span>
                  </div>
                </label>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>

      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.acl.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="button" class="btn" onclick="document.getElementById('confirmModal').classList.add('open')">
          Guardar cambios
        </button>
      </div>
    </div>
  </form>

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Confirmar cambios de permisos?</h3>
      <p>Esto afectará a todos los usuarios que tengan el rol <strong>{{ $role->nombre }}</strong>. La acción quedará registrada en la bitácora ACL.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formActualizarRol').submit()">Sí, guardar</button>
      </div>
    </div>
  </div>

</div>
@endsection
