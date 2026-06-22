<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>PUNTA — @yield('title', 'Dashboard')</title>
  <link rel="stylesheet" href="{{ asset('css/01-variables.css') }}?v={{ filemtime(public_path('css/01-variables.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/02-base.css') }}?v={{ filemtime(public_path('css/02-base.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/03-layout.css') }}?v={{ filemtime(public_path('css/03-layout.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/04-components.css') }}?v={{ filemtime(public_path('css/04-components.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/05-forms.css') }}?v={{ filemtime(public_path('css/05-forms.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/05-tables.css') }}?v={{ filemtime(public_path('css/05-tables.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/06-wizards.css') }}?v={{ filemtime(public_path('css/06-wizards.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/07-modals.css') }}?v={{ filemtime(public_path('css/07-modals.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/08-screens.css') }}?v={{ filemtime(public_path('css/08-screens.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/09-acuse.css') }}?v={{ filemtime(public_path('css/09-acuse.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/10-calendario.css') }}?v={{ filemtime(public_path('css/10-calendario.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/11-utilities.css') }}?v={{ filemtime(public_path('css/11-utilities.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/12-responsive.css') }}?v={{ filemtime(public_path('css/12-responsive.css')) }}">
  <style>
    .nav a { border:0; background:transparent; color:white; min-height:46px; border-radius:8px;
      display:flex; align-items:center; gap:12px; padding:0 14px; font-size:12px; line-height:1.1;
      font-weight:800; letter-spacing:.04em; text-transform:uppercase; text-decoration:none; cursor:pointer; }
    .nav a:hover, .nav a.active { background:var(--secondary-container); color:var(--on-secondary-container); }

    /* Wizard step numbers */
    .wizard-stepper,
    #propuestaWizard .wizard-sidebar,
    #airWizard .wizard-sidebar { counter-reset: wstep; }
    .wizard-step,
    #propuestaWizard .wizard-step,
    #airWizard .wizard-step { counter-increment: wstep; }
    .wizard-dot::before,
    #propuestaWizard .wizard-dot::before,
    #airWizard .wizard-dot::before { content: counter(wstep); font-size: 13px; }
    .wizard-step.done .wizard-dot,
    .wizard-step.completed .wizard-dot { background: #16a34a !important; }

    /* KPI Filtrar button — rosa claro igual al prototipo */
    .stat > span, .stat > a {
      color: var(--primary);
      background: var(--primary-fixed);
      border: 1px solid rgba(117,0,56,.12);
      border-radius: 8px;
      padding: 7px 10px;
      font-size: 10px;
      line-height: 12px;
      font-weight: 800;
      white-space: nowrap;
      flex-shrink: 0;
      text-decoration: none;
    }

    /* Quick cards: 3 columnas para enlace */
    .grid.quick { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }

    /* Confirm modals */
    .confirm-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; place-items:center; }
    .confirm-modal-backdrop.open { display:grid; }
    .confirm-modal { background:white; border-radius:16px; padding:32px; max-width:420px; width:calc(100% - 48px); box-shadow:0 20px 60px rgba(0,0,0,.2); display:grid; gap:16px; }
    .confirm-modal h3 { margin:0; font-size:18px; }
    .confirm-modal p  { margin:0; color:#667085; font-size:14px; }
    .confirm-modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:4px; }

    /* Period pill */
    .period b { background:var(--primary-container); color:white; border-radius:999px; padding:3px 10px; font-size:11px; margin-left:6px; }
    .period b.vencido { background:#dc2626; }
  </style>
</head>
<body>
<div class="app" id="systemApp">

  <aside class="sidebar">
    <div class="brand">
      @if(file_exists(public_path('img/logo/logo.png')))
        <img src="{{ asset('img/logo/logo.png') }}" alt="Logo" class="brand-logo" style="object-fit:contain;padding:8px">
      @else
        <div class="brand-logo">P</div>
      @endif
      <h1>PUNTA</h1>
      <p class="nowrap">Administración Pública</p>
    </div>
    <nav class="nav">
      @php
        $navItems = [
          ['label'=>'Dashboard',         'route'=>'dashboard',               'permiso'=>null],
          ['label'=>'Trámites',           'route'=>'tramites.index',           'permiso'=>'tramites.ver'],
          ['label'=>'Agenda SyD',         'route'=>'agenda.index',             'permiso'=>'agenda.ver'],
          ['label'=>'Agenda Regulatoria', 'route'=>'agenda-regulatoria.index', 'permiso'=>'agenda_regulatoria.ver'],
          ['label'=>'Dictámenes AIR',     'route'=>'dictamenes-air.index',     'permiso'=>'agenda_regulatoria.aprobar'],
          ['label'=>'Regulaciones',       'route'=>'regulaciones.index',       'permiso'=>'regulaciones.ver'],
          ['label'=>'Calendario',         'route'=>'calendario',               'permiso'=>'calendario.ver'],
          ['label'=>'Firmas',             'route'=>'firmas.index',             'permiso'=>'firmas.firmar'],
          ['label'=>'Configuración',      'route'=>'admin.configuracion',      'permiso'=>'_admin'],
          ['label'=>'Usuarios',           'route'=>'admin.usuarios.index',     'permiso'=>'_admin'],
          ['label'=>'Periodos',           'route'=>'admin.periodos',           'permiso'=>'_admin'],
          ['label'=>'Bitácora',           'route'=>'admin.bitacora',           'permiso'=>'_admin'],
        ];
      @endphp
      @foreach($navItems as $item)
        @if($item['permiso'] === null || ($item['permiso'] === '_admin' && auth()->user()->rol === 'admin') || ($item['permiso'] !== '_admin' && auth()->user()->tienePermiso($item['permiso'])))
          <a href="{{ route($item['route']) }}"
             class="{{ request()->routeIs($item['route'].'*') ? 'active' : '' }}">
            <span class="nowrap">{{ $item['label'] }}</span>
          </a>
        @endif
      @endforeach
    </nav>
    <button class="profile" type="button" onclick="document.getElementById('logoutModal').classList.add('open')">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}{{ strtoupper(substr(explode(' ',auth()->user()->name)[1]??'',0,1)) }}</div>
      <div class="profile-copy">
        <strong class="nowrap">{{ auth()->user()->name }}</strong>
        <span class="nowrap">{{ Str::limit(auth()->user()->dependencia->nombre ?? 'PUNTA', 28) }}</span>
      </div>
    </button>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-spacer"></div>
      @php
        // Periodo activo de cada agenda (solo puede haber uno activo por tipo).
        $periodosActivos = \App\Models\Periodo::where('estatus', 'activo')
            ->orderBy('tipo')
            ->get();
      @endphp
      @if($periodosActivos->isNotEmpty())
        <div class="period nowrap">
          @foreach($periodosActivos as $pa)
            @php
              $paDias = (int)\Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($pa->fecha_fin), false);
              $paTipo = $pa->tipo === 'agenda_syd' ? 'SyD' : 'Regulatoria';
            @endphp
            <span class="period-grupo nowrap" title="{{ $pa->nombre }}">
              <span class="nowrap">{{ $paTipo }}</span>
              <b class="{{ ($paDias < 0) ? 'vencido' : '' }}">
                {{ ($paDias >= 0) ? $paDias.' días' : 'Vencido' }}
              </b>
            </span>
            @if(!$loop->last)<span class="period-divisor"></span>@endif
          @endforeach
        </div>
      @endif
      <div class="role-pill">{{ ucfirst(auth()->user()->rol) }}</div>
      <div class="top-actions">
        @include('partials.campanita')
      </div>
    </header>

    <main class="canvas">
      @if(session('success'))
        <div class="toast toast-success u-toast-inline">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="toast toast-error u-toast-inline">{{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="toast toast-error u-toast-inline">{{ $errors->first() }}</div>
      @endif
      @yield('content')
    </main>
  </div>

</div>

<div class="confirm-modal-backdrop" id="logoutModal">
  <div class="confirm-modal">
    <h3>¿Cerrar sesión?</h3>
    <p>Tu sesión quedará cerrada y deberás iniciar sesión nuevamente.</p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn btn-outline" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancelar</button>
      <form method="POST" action="{{ route('logout') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-danger">Sí, cerrar sesión</button>
      </form>
    </div>
  </div>
</div>

<div class="confirm-modal-backdrop" id="deleteModal">
  <div class="confirm-modal">
    <h3 id="deleteModalTitle">¿Eliminar este registro?</h3>
    <p id="deleteModalText">Esta acción no se puede deshacer.</p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteModal').classList.remove('open')">Cancelar</button>
      <form id="deleteModalForm" method="POST" class="d-inline">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Sí, eliminar</button>
      </form>
    </div>
  </div>
</div>

<div class="confirm-modal-backdrop" id="confirmModal">
  <div class="confirm-modal">
    <h3 id="confirmModalTitle">¿Desea continuar?</h3>
    <p id="confirmModalText"></p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">No</button>
      <button type="button" class="btn" id="confirmModalOk">Sí, continuar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"></div></div>

<script src="{{ asset('js/core/helpers.js') }}"></script>
<script src="{{ asset('js/data/help-texts.js') }}"></script>
<script src="{{ asset('js/core/help.js') }}"></script>
<script src="{{ asset('js/core/ejemplo-llenado.js') }}"></script>
<script src="{{ asset('js/carga-archivos.js') }}"></script>
<script>
function confirmDelete(action, title, text) {
  document.getElementById('deleteModalForm').action = action;
  if (title) document.getElementById('deleteModalTitle').textContent = title;
  if (text)  document.getElementById('deleteModalText').textContent  = text;
  document.getElementById('deleteModal').classList.add('open');
}

// Paquete UX: modal genérico de confirmación que reemplaza confirm() nativo.
// Uso en un botón de submit:  onclick="return confirmarAccion(this, '¿Mensaje?')"
// Devuelve siempre false (cancela el submit inmediato); si el usuario acepta en
// el modal, dispara el submit real del formulario del botón.
function confirmarAccion(boton, mensaje, titulo) {
  var modal = document.getElementById('confirmModal');
  document.getElementById('confirmModalTitle').textContent = titulo || '¿Desea continuar?';
  document.getElementById('confirmModalText').textContent  = mensaje || '';
  var ok = document.getElementById('confirmModalOk');
  // Reemplazar el botón OK para limpiar listeners previos.
  var okNuevo = ok.cloneNode(true);
  ok.parentNode.replaceChild(okNuevo, ok);
  okNuevo.addEventListener('click', function () {
    modal.classList.remove('open');
    var form = boton.closest('form');
    if (form) form.submit();
  });
  modal.classList.add('open');
  return false;
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.confirm-modal-backdrop.open').forEach(function(m) { m.classList.remove('open'); });
  }
});
</script>
@stack('scripts')
  <script src="{{ asset('js/validacion-inputs.js') }}" defer></script>
</body>
</html>