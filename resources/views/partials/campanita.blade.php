{{--
  Campanita de notificaciones (base, entrega 1).
  - Contador rojo con el número de no leídas.
  - Panel desplegable con las últimas notificaciones.
  - "Marcar todas leídas" y clic en una para ir a su enlace marcándola leída.

  Lee de las notificaciones del usuario (canal database de Laravel):
    auth()->user()->unreadNotifications  → no leídas (para el contador)
    auth()->user()->notifications        → todas (mostramos las últimas)
--}}
@php
  $usuarioNoti   = auth()->user();
  $noLeidas      = $usuarioNoti->unreadNotifications;
  $totalNoLeidas = $noLeidas->count();

  // Bug #12: antes se usaba ->latest()->take(8), que traía las 8 más recientes
  // sin importar si estaban leídas o no. Si el usuario tenía 10 no leídas pero
  // 6 leídas más recientes, el panel mostraba 6 leídas + 2 no leídas, mientras
  // el badge decía "9+". Ahora las no leídas (read_at IS NULL) van primero,
  // y dentro de cada grupo se ordenan por fecha descendente. Así el badge y
  // el contenido del panel quedan alineados.
  $ultimas = $usuarioNoti->notifications()
      ->orderByRaw('read_at IS NOT NULL')  // NULL primero (no leídas arriba)
      ->latest()                            // dentro de cada grupo, más recientes primero
      ->take(8)
      ->get();
@endphp

<div class="noti-wrap" style="position:relative;">
  <button type="button" class="noti-bell" onclick="notiToggle()" aria-label="Notificaciones"
    style="position:relative; width:38px; height:38px; display:flex; align-items:center; justify-content:center; border:none; background:transparent; cursor:pointer;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
    @if($totalNoLeidas > 0)
      <span class="noti-badge" style="position:absolute; top:-2px; right:-2px; min-width:18px; height:18px; padding:0 5px; background:#dc2626; color:#fff; font-size:11px; font-weight:600; border-radius:9px; display:flex; align-items:center; justify-content:center;">{{ $totalNoLeidas > 9 ? '9+' : $totalNoLeidas }}</span>
    @endif
  </button>

  <div id="notiPanel" class="noti-panel" style="display:none; position:absolute; right:0; top:46px; width:340px; max-width:90vw; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.12); overflow:hidden; z-index:1000;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #f0f0f0;">
      <span style="font-size:14px; font-weight:600;">Notificaciones</span>
      @if($totalNoLeidas > 0)
        <form method="POST" action="{{ route('notificaciones.leerTodas') }}" style="margin:0;">
          @csrf
          <button type="submit" style="font-size:12px; color:#2563eb; background:transparent; border:none; cursor:pointer; padding:0;">Marcar todas leídas</button>
        </form>
      @endif
    </div>

    <div class="noti-lista" style="max-height:360px; overflow-y:auto;">
      @forelse($ultimas as $noti)
        @php $d = $noti->data; $esNoLeida = is_null($noti->read_at); @endphp
        <a href="{{ route('notificaciones.abrir', $noti->id) }}"
           style="display:flex; gap:10px; padding:12px 14px; border-bottom:1px solid #f0f0f0; text-decoration:none; color:inherit; {{ $esNoLeida ? 'background:#eff6ff;' : '' }}">
        @php
          $iconoSvg = [
            'ti-eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle>',
            'ti-writing'  => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"></path>',
            'ti-edit'     => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"></path>',
            'ti-calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
            'ti-check'    => '<polyline points="20 6 9 17 4 12"></polyline>',
            'ti-bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
          ];
          $svgPaths = $iconoSvg[$d['icono'] ?? 'ti-bell'] ?? $iconoSvg['ti-bell'];
        @endphp
        <span style="margin-top:2px; flex-shrink:0; color:{{ $esNoLeida ? '#2563eb' : '#9ca3af' }};">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svgPaths !!}</svg>
        </span>
          <div style="flex:1; min-width:0;">
            <p style="font-size:13px; margin:0 0 2px; line-height:1.4; {{ $esNoLeida ? 'font-weight:500;' : 'color:#6b7280;' }}">{{ $d['titulo'] ?? 'Aviso' }}</p>
            <p style="font-size:12px; color:#6b7280; margin:0 0 2px; line-height:1.35;">{{ \Illuminate\Support\Str::limit($d['mensaje'] ?? '', 70) }}</p>
            <p style="font-size:11px; color:#9ca3af; margin:0;">{{ $noti->created_at->diffForHumans() }}</p>
          </div>
          @if($esNoLeida)
            <span style="width:8px; height:8px; background:#2563eb; border-radius:50%; margin-top:5px; flex-shrink:0;"></span>
          @endif
        </a>
      @empty
        <div style="padding:28px 14px; text-align:center; color:#9ca3af; font-size:13px;">
          No tienes notificaciones.
        </div>
      @endforelse
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
