
<?php
  $usuarioNoti   = auth()->user();
  $noLeidas      = $usuarioNoti->unreadNotifications;
  $ultimas       = $usuarioNoti->notifications()->latest()->take(8)->get();
  $totalNoLeidas = $noLeidas->count();
?>

<div class="noti-wrap" style="position:relative;">
  <button type="button" class="noti-bell" onclick="notiToggle()" aria-label="Notificaciones"
    style="position:relative; width:38px; height:38px; display:flex; align-items:center; justify-content:center; border:none; background:transparent; cursor:pointer;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
    <?php if($totalNoLeidas > 0): ?>
      <span class="noti-badge" style="position:absolute; top:-2px; right:-2px; min-width:18px; height:18px; padding:0 5px; background:#dc2626; color:#fff; font-size:11px; font-weight:600; border-radius:9px; display:flex; align-items:center; justify-content:center;"><?php echo e($totalNoLeidas > 9 ? '9+' : $totalNoLeidas); ?></span>
    <?php endif; ?>
  </button>

  <div id="notiPanel" class="noti-panel" style="display:none; position:absolute; right:0; top:46px; width:340px; max-width:90vw; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.12); overflow:hidden; z-index:1000;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #f0f0f0;">
      <span style="font-size:14px; font-weight:600;">Notificaciones</span>
      <?php if($totalNoLeidas > 0): ?>
        <form method="POST" action="<?php echo e(route('notificaciones.leerTodas')); ?>" style="margin:0;">
          <?php echo csrf_field(); ?>
          <button type="submit" style="font-size:12px; color:#2563eb; background:transparent; border:none; cursor:pointer; padding:0;">Marcar todas leídas</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="noti-lista" style="max-height:360px; overflow-y:auto;">
      <?php $__empty_1 = true; $__currentLoopData = $ultimas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $noti): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php $d = $noti->data; $esNoLeida = is_null($noti->read_at); ?>
        <a href="<?php echo e(route('notificaciones.abrir', $noti->id)); ?>"
           style="display:flex; gap:10px; padding:12px 14px; border-bottom:1px solid #f0f0f0; text-decoration:none; color:inherit; <?php echo e($esNoLeida ? 'background:#eff6ff;' : ''); ?>">
        <?php
          $iconoSvg = [
            'ti-eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle>',
            'ti-writing'  => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"></path>',
            'ti-edit'     => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"></path>',
            'ti-calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
            'ti-check'    => '<polyline points="20 6 9 17 4 12"></polyline>',
            'ti-bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
          ];
          $svgPaths = $iconoSvg[$d['icono'] ?? 'ti-bell'] ?? $iconoSvg['ti-bell'];
        ?>
        <span style="margin-top:2px; flex-shrink:0; color:<?php echo e($esNoLeida ? '#2563eb' : '#9ca3af'); ?>;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $svgPaths; ?></svg>
        </span>
          <div style="flex:1; min-width:0;">
            <p style="font-size:13px; margin:0 0 2px; line-height:1.4; <?php echo e($esNoLeida ? 'font-weight:500;' : 'color:#6b7280;'); ?>"><?php echo e($d['titulo'] ?? 'Aviso'); ?></p>
            <p style="font-size:12px; color:#6b7280; margin:0 0 2px; line-height:1.35;"><?php echo e(\Illuminate\Support\Str::limit($d['mensaje'] ?? '', 70)); ?></p>
            <p style="font-size:11px; color:#9ca3af; margin:0;"><?php echo e($noti->created_at->diffForHumans()); ?></p>
          </div>
          <?php if($esNoLeida): ?>
            <span style="width:8px; height:8px; background:#2563eb; border-radius:50%; margin-top:5px; flex-shrink:0;"></span>
          <?php endif; ?>
        </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div style="padding:28px 14px; text-align:center; color:#9ca3af; font-size:13px;">
          No tienes notificaciones.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  function notiToggle() {
    var p = document.getElementById('notiPanel');
    p.style.display = (p.style.display === 'none' || !p.style.display) ? 'block' : 'none';
  }
  // Cerrar el panel al hacer clic fuera de él.
  document.addEventListener('click', function (e) {
    var wrap = e.target.closest('.noti-wrap');
    if (!wrap) {
      var p = document.getElementById('notiPanel');
      if (p) p.style.display = 'none';
    }
  });
</script>
<?php /**PATH C:\laragon\www\punta\resources\views/partials/campanita.blade.php ENDPATH**/ ?>