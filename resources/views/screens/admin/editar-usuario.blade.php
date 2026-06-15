@extends('layouts.app')
@section('title', 'Editar Usuario')
@section('content')
<div class="page-narrow">
  <div class="screen-head">
    <div><h2 class="nowrap">Editar Usuario</h2><p class="nowrap">{{ $usuario->name }} — {{ $usuario->email }}</p></div>
    <div class="head-actions"><a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline">Volver</a></div>
  </div>

  @if($errors->any())
  <div class="assist-box" style="background:#FEF2F2;border-color:#FCA5A5;margin-bottom:8px">
    <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}">
    @csrf @method('PUT')

    <div class="card" style="margin-bottom:16px"><div class="card-body-padded">
      <h3 style="margin-bottom:12px">Datos de la cuenta</h3>
      <div class="wizard-fields">
        <div class="field span-2"><label>Nombre completo *</label><input required name="name" value="{{ old('name', $usuario->name) }}"></div>
        <div class="field"><label>Correo electrónico *</label><input required name="email" type="email" value="{{ old('email', $usuario->email) }}"></div>
        <div class="field"><label>Cargo</label><input name="cargo" value="{{ old('cargo', $usuario->cargo) }}"></div>
        <div class="field"><label>Nueva contraseña <small>(vacío = no cambiar)</small></label><input name="password" type="password"></div>
        <div class="field"><label>Confirmar contraseña</label><input name="password_confirmation" type="password"></div>
        <div class="field"><label>Dependencia</label>
          <select name="dependencia_id">
            <option value="">Sin dependencia</option>
            @foreach($dependencias as $d)<option value="{{ $d->id }}" {{ old('dependencia_id',$usuario->dependencia_id)==$d->id?'selected':'' }}>{{ $d->nombre }}</option>@endforeach
          </select>
        </div>
        <div class="field"><label>Rol base *</label>
          <select required name="rol" id="selectRol">
            @foreach($roles as $r)<option value="{{ $r }}" {{ old('rol',$usuario->rol)===$r?'selected':'' }}>{{ ucfirst($r) }}</option>@endforeach
          </select>
        </div>
        <div class="field"><label>Estatus</label>
          <select name="activo">
            <option value="1" {{ $usuario->activo?'selected':'' }}>Activo</option>
            <option value="0" {{ !$usuario->activo?'selected':'' }}>Inactivo</option>
          </select>
        </div>
      </div>
    </div></div>

    @php
      $moduloLabels = [
        'tramites'=>'Trámites','agenda'=>'Agenda SyD','agenda_regulatoria'=>'Agenda Regulatoria',
        'regulaciones'=>'Regulaciones','calendario'=>'Calendario','firmas'=>'Firmas',
        'usuarios'=>'Usuarios','acl'=>'Control de Acceso','scian'=>'SCIAN',
        'parametros'=>'Parámetros','umbrales'=>'Umbrales','unidades_valor'=>'Unidades de Valor',
      ];
      $accionLabels = [
        'ver'=>'Ver','crear'=>'Crear','editar'=>'Editar','eliminar'=>'Eliminar',
        'aprobar'=>'Aprobar','observar'=>'Observar','gestionar'=>'Gestionar','firmar'=>'Firmar',
      ];
    @endphp

    <div class="card"><div class="card-body-padded">
      <h3 style="margin-bottom:4px">Permisos por módulo</h3>
      <p style="color:#667085;font-size:13px;margin-bottom:16px">Los checkboxes reflejan los permisos actuales del usuario. Modifíquelos según sea necesario.</p>

      <div style="display:grid;gap:12px">
        @foreach($permisos as $modulo => $permisosDelModulo)
          @if(in_array($modulo, ['acl','bitacora','parametros','umbrales','unidades_valor','usuarios'])) @continue @endif
          <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
              <label style="font-weight:700;font-size:13px;color:#1a1a2e;margin:0">
                <input type="checkbox" class="modulo-toggle" data-modulo="{{ $modulo }}" style="margin-right:4px">
                {{ $moduloLabels[$modulo] ?? ucfirst($modulo) }}
              </label>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px 16px">
              @foreach($permisosDelModulo as $p)
                <label style="font-size:12px;color:#444;cursor:pointer;display:flex;align-items:center;gap:4px">
                  <input type="checkbox" name="permisos[]" value="{{ $p->id }}" class="permiso-cb" data-modulo="{{ $modulo }}" data-codigo="{{ $p->codigo }}"
                    {{ in_array($p->id, old('permisos', $permisosUsuario)) ? 'checked' : '' }}>
                  {{ $accionLabels[$p->accion] ?? ucfirst($p->accion) }}
                </label>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="card-actions card-actions-end">
      <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn">Guardar cambios</button>
    </div></div>
  </form>

  @if($usuario->id !== auth()->id())
  <div style="display:flex;justify-content:flex-end;margin-top:8px">
    <button type="button" class="btn btn-outline danger"
      onclick="confirmDelete('{{ route('admin.usuarios.destroy', $usuario) }}','¿Eliminar a {{ addslashes($usuario->name) }}?','El usuario perderá acceso.')">
      Eliminar usuario
    </button>
  </div>
  @endif
</div>

<script>
var rolesPermisos = @json($rolesConPermisos);

document.getElementById('selectRol').addEventListener('change', function() {
  var rol = this.value;
  var codigos = rolesPermisos[rol] || [];
  var esAdmin = (rol === 'admin');
  document.querySelectorAll('.permiso-cb').forEach(function(cb) {
    cb.checked = esAdmin || codigos.includes(cb.dataset.codigo);
  });
  document.querySelectorAll('.modulo-toggle').forEach(function(mt) {
    var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + mt.dataset.modulo + '"]');
    mt.checked = Array.from(checks).every(function(c) { return c.checked; });
  });
});

document.querySelectorAll('.modulo-toggle').forEach(function(mt) {
  mt.addEventListener('change', function() {
    document.querySelectorAll('.permiso-cb[data-modulo="' + this.dataset.modulo + '"]').forEach(function(cb) { cb.checked = mt.checked; });
  });
});

document.querySelectorAll('.permiso-cb').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + this.dataset.modulo + '"]');
    var mt = document.querySelector('.modulo-toggle[data-modulo="' + this.dataset.modulo + '"]');
    if (mt) mt.checked = Array.from(checks).every(function(c) { return c.checked; });
  });
});

// Init: sincronizar toggles de módulo con estado actual
document.querySelectorAll('.modulo-toggle').forEach(function(mt) {
  var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + mt.dataset.modulo + '"]');
  mt.checked = checks.length > 0 && Array.from(checks).every(function(c) { return c.checked; });
});
</script>
@endsection
