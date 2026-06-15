<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <title>PUNTA — <?php echo $__env->yieldContent('title', 'Dashboard'); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/01-variables.css')); ?>?v=<?php echo e(filemtime(public_path('css/01-variables.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/02-base.css')); ?>?v=<?php echo e(filemtime(public_path('css/02-base.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/03-layout.css')); ?>?v=<?php echo e(filemtime(public_path('css/03-layout.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/04-components.css')); ?>?v=<?php echo e(filemtime(public_path('css/04-components.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/05-forms.css')); ?>?v=<?php echo e(filemtime(public_path('css/05-forms.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/05-tables.css')); ?>?v=<?php echo e(filemtime(public_path('css/05-tables.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/06-wizards.css')); ?>?v=<?php echo e(filemtime(public_path('css/06-wizards.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/07-modals.css')); ?>?v=<?php echo e(filemtime(public_path('css/07-modals.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/08-screens.css')); ?>?v=<?php echo e(filemtime(public_path('css/08-screens.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/09-acuse.css')); ?>?v=<?php echo e(filemtime(public_path('css/09-acuse.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/10-calendario.css')); ?>?v=<?php echo e(filemtime(public_path('css/10-calendario.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/11-utilities.css')); ?>?v=<?php echo e(filemtime(public_path('css/11-utilities.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/12-responsive.css')); ?>?v=<?php echo e(filemtime(public_path('css/12-responsive.css'))); ?>">
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
      <?php if(file_exists(public_path('img/logo/logo.png'))): ?>
        <img src="<?php echo e(asset('img/logo/logo.png')); ?>" alt="Logo" class="brand-logo" style="object-fit:contain;padding:8px">
      <?php else: ?>
        <div class="brand-logo">P</div>
      <?php endif; ?>
      <h1>PUNTA</h1>
      <p class="nowrap">Administración Pública</p>
    </div>
    <nav class="nav">
      <?php
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
      ?>
      <?php $__currentLoopData = $navItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($item['permiso'] === null || ($item['permiso'] === '_admin' && auth()->user()->rol === 'admin') || ($item['permiso'] !== '_admin' && auth()->user()->tienePermiso($item['permiso']))): ?>
          <a href="<?php echo e(route($item['route'])); ?>"
             class="<?php echo e(request()->routeIs($item['route'].'*') ? 'active' : ''); ?>">
            <span class="nowrap"><?php echo e($item['label']); ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
    <button class="profile" type="button" onclick="document.getElementById('logoutModal').classList.add('open')">
      <div class="avatar"><?php echo e(strtoupper(substr(auth()->user()->name,0,1))); ?><?php echo e(strtoupper(substr(explode(' ',auth()->user()->name)[1]??'',0,1))); ?></div>
      <div class="profile-copy">
        <strong class="nowrap"><?php echo e(auth()->user()->name); ?></strong>
        <span class="nowrap"><?php echo e(Str::limit(auth()->user()->dependencia->nombre ?? 'PUNTA', 28)); ?></span>
      </div>
    </button>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-spacer"></div>
      <?php
        $periodoActivo = \Illuminate\Support\Facades\DB::table('periodos')->where('estatus','activo')->first();
        $diasRestantes = $periodoActivo ? (int)\Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($periodoActivo->fecha_fin), false) : null;
      ?>
      <?php if($periodoActivo): ?>
        <div class="period nowrap">
          <span class="nowrap"><?php echo e($periodoActivo->nombre); ?></span>
          <b class="<?php echo e(($diasRestantes !== null && $diasRestantes < 0) ? 'vencido' : ''); ?>">
            <?php echo e(($diasRestantes !== null && $diasRestantes >= 0) ? $diasRestantes.' días' : 'Vencido'); ?>

          </b>
        </div>
      <?php endif; ?>
      <div class="role-pill"><?php echo e(ucfirst(auth()->user()->rol)); ?></div>
      <div class="top-actions">
        <?php echo $__env->make('partials.campanita', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>
    </header>

    <main class="canvas">
      <?php if(session('success')): ?>
        <div class="toast toast-success u-toast-inline"><?php echo e(session('success')); ?></div>
      <?php endif; ?>
      <?php if(session('error')): ?>
        <div class="toast toast-error u-toast-inline"><?php echo e(session('error')); ?></div>
      <?php endif; ?>
      <?php if($errors->any()): ?>
        <div class="toast toast-error u-toast-inline"><?php echo e($errors->first()); ?></div>
      <?php endif; ?>
      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>

</div>

<div class="confirm-modal-backdrop" id="logoutModal">
  <div class="confirm-modal">
    <h3>¿Cerrar sesión?</h3>
    <p>Tu sesión quedará cerrada y deberás iniciar sesión nuevamente.</p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn btn-outline" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancelar</button>
      <form method="POST" action="<?php echo e(route('logout')); ?>" class="d-inline">
        <?php echo csrf_field(); ?>
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
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit" class="btn btn-danger">Sí, eliminar</button>
      </form>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"></div></div>

<script src="<?php echo e(asset('js/core/helpers.js')); ?>"></script>
<script src="<?php echo e(asset('js/data/help-texts.js')); ?>"></script>
<script src="<?php echo e(asset('js/core/help.js')); ?>"></script>
<script src="<?php echo e(asset('js/core/ejemplo-llenado.js')); ?>"></script>
<script src="<?php echo e(asset('js/core/carga-archivos.js')); ?>"></script>
<script>
function confirmDelete(action, title, text) {
  document.getElementById('deleteModalForm').action = action;
  if (title) document.getElementById('deleteModalTitle').textContent = title;
  if (text)  document.getElementById('deleteModalText').textContent  = text;
  document.getElementById('deleteModal').classList.add('open');
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.confirm-modal-backdrop.open').forEach(function(m) { m.classList.remove('open'); });
  }
});
</script>
<?php echo $__env->yieldPushContent('scripts'); ?>
  <script src="<?php echo e(asset('js/validacion-inputs.js')); ?>" defer></script>
</body>
</html>
<?php /**PATH C:\laragon\www\punta\resources\views/layouts/app.blade.php ENDPATH**/ ?>