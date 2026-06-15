@extends('layouts.app')
@section('title', 'Nuevo Usuario')
@section('content')
<div class="page-narrow">
  <div class="screen-head">
    <div><h2 class="nowrap">Nuevo Usuario</h2><p class="nowrap">Alta de cuenta, rol y permisos por módulo.</p></div>
    <div class="head-actions"><a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline">Cancelar</a></div>
  </div>

  @if($errors->any())
  <div class="assist-box" style="background:#FEF2F2;border-color:#FCA5A5;margin-bottom:8px">
    <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.usuarios.store') }}">
    @csrf

    {{-- Datos básicos --}}
    <div class="card" style="margin-bottom:16px"><div class="card-body-padded">
      <h3 style="margin-bottom:12px">Datos de la cuenta</h3>
      <div class="wizard-fields">
        <div class="field span-2"><label>Nombre completo *</label><input required name="name" value="{{ old('name') }}" placeholder="Nombre completo"></div>
        <div class="field"><label>Correo electrónico *</label><input required name="email" type="email" value="{{ old('email') }}" placeholder="usuario@lapaz.gob.mx"></div>
        <div class="field"><label>Cargo</label><input name="cargo" value="{{ old('cargo') }}" placeholder="Ej. Director de Gobierno Digital"></div>
        <div class="field"><label>Contraseña *</label><input required name="password" type="password" placeholder="Mínimo 8 caracteres"></div>
        <div class="field"><label>Confirmar contraseña *</label><input required name="password_confirmation" type="password"></div>
        <div class="field"><label>Dependencia *</label>
          <select name="dependencia_id">
            <option value="">Seleccione...</option>
            @foreach($dependencias as $d)<option value="{{ $d->id }}" {{ old('dependencia_id')==$d->id?'selected':'' }}>{{ $d->nombre }}</option>@endforeach
          </select>
        </div>
        <div class="field"><label>Rol base *</label>
          <select required name="rol" id="selectRol">
            <option value="">Seleccione...</option>
            @foreach($roles as $r)<option value="{{ $r }}" {{ old('rol')===$r?'selected':'' }}>{{ ucfirst($r) }}</option>@endforeach
          </select>
          <small class="help-small">Al seleccionar un rol, se pre-marcan sus permisos por defecto. Puede ajustarlos abajo.</small>
        </div>
      </div>
    </div></div>

    {{-- Permisos por módulo --}}
    <div class="card"><div class="card-body-padded">
      <h3 style="margin-bottom:4px">Permisos por módulo</h3>
      <p style="color:#667085;font-size:13px;margin-bottom:16px">Marque los permisos específicos que tendrá el usuario. El rol base pre-selecciona los permisos por defecto.</p>

      @php
        $moduloLabels = [
          'tramites' => 'Trámites',
          'agenda' => 'Agenda SyD',
          'agenda_regulatoria' => 'Agenda Regulatoria',
          'regulaciones' => 'Regulaciones',
          'calendario' => 'Calendario',
          'firmas' => 'Firmas',
          'usuarios' => 'Usuarios',
          'acl' => 'Control de Acceso',
          'scian' => 'SCIAN',
          'parametros' => 'Parámetros',
          'umbrales' => 'Umbrales',
          'unidades_valor' => 'Unidades de Valor',
        ];
        $accionLabels = [
          'ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar',
          'eliminar' => 'Eliminar', 'aprobar' => 'Aprobar', 'observar' => 'Observar',
          'gestionar' => 'Gestionar', 'firmar' => 'Firmar',
        ];
      @endphp

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
            <div style="display:flex;flex-wrap:wrap;gap:8px 16px" id="modulo-{{ $modulo }}">
              @foreach($permisosDelModulo as $p)
                <label style="font-size:12px;color:#444;cursor:pointer;display:flex;align-items:center;gap:4px">
                  <input type="checkbox" name="permisos[]" value="{{ $p->id }}" class="permiso-cb" data-modulo="{{ $modulo }}" data-codigo="{{ $p->codigo }}"
                    {{ in_array($p->id, old('permisos', [])) ? 'checked' : '' }}>
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
      <button type="submit" class="btn">Guardar Usuario</button>
    </div></div>

  </form>
</div>

<script>
// Mapa de permisos por rol (viene del controller)
var rolesPermisos = @json($rolesConPermisos);

// Al cambiar rol → pre-marcar permisos
document.getElementById('selectRol').addEventListener('change', function() {
  var rol = this.value;
  var codigos = rolesPermisos[rol] || [];
  var allPermisos = Object.values(rolesPermisos).flat();
  var esAdmin = (rol === 'admin');

  document.querySelectorAll('.permiso-cb').forEach(function(cb) {
    cb.checked = esAdmin || codigos.includes(cb.dataset.codigo);
  });

  // Actualizar toggles de módulo
  document.querySelectorAll('.modulo-toggle').forEach(function(mt) {
    var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + mt.dataset.modulo + '"]');
    var allChecked = Array.from(checks).every(function(c) { return c.checked; });
    mt.checked = allChecked;
  });
});

// Toggle de módulo completo
document.querySelectorAll('.modulo-toggle').forEach(function(mt) {
  mt.addEventListener('change', function() {
    var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + this.dataset.modulo + '"]');
    var val = this.checked;
    checks.forEach(function(cb) { cb.checked = val; });
  });
});

// Actualizar toggle de módulo cuando cambias un permiso individual
document.querySelectorAll('.permiso-cb').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var modulo = this.dataset.modulo;
    var checks = document.querySelectorAll('.permiso-cb[data-modulo="' + modulo + '"]');
    var mt = document.querySelector('.modulo-toggle[data-modulo="' + modulo + '"]');
    if (mt) mt.checked = Array.from(checks).every(function(c) { return c.checked; });
  });
});

// Trigger inicial si hay rol pre-seleccionado
var sel = document.getElementById('selectRol');
if (sel.value) sel.dispatchEvent(new Event('change'));
</script>
@endsection
