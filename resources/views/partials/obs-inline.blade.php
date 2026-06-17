{{--
  Observaciones inline por sección.
  Uso: @include('partials.obs-inline', ['seccion' => 'Datos generales', 'observaciones' => $observacionesPorSeccion])
  Muestra las observaciones de esa sección pegadas al contenido, con autor, fecha y estatus.
--}}
@php
  $obsSeccion = ($observaciones[$seccion] ?? collect())->sortByDesc('created_at');
@endphp
@if($obsSeccion->isNotEmpty())
  <div class="obs-inline-lista">
    @foreach($obsSeccion as $obs)
      <div class="obs-inline-item-detalle obs-inline-{{ $obs->estatus ?? 'pendiente' }}">
        <div class="obs-inline-meta">
          <span class="obs-inline-autor">{{ $obs->realizadaPor->name ?? '—' }}</span>
          <span class="obs-inline-fecha">{{ \Carbon\Carbon::parse($obs->created_at)->format('d/m/Y H:i') }}</span>
          <span class="obs-badge obs-badge-{{ $obs->estatus ?? 'pendiente' }}">{{ $obs->estatusLegible() }}</span>
        </div>
        <p class="obs-inline-texto">{{ $obs->texto }}</p>
      </div>
    @endforeach
  </div>
@endif
