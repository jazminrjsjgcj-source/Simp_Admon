{{--
  Checklist lateral de observaciones (corrección #18, punto 7).

  Panel derecho que reúne TODAS las observaciones del registro, agrupadas
  por sección, para que el enlace sepa de un vistazo qué falta atender.
  Muestra el estatus de cada una y el progreso general.

  Espera:
    $observacionesPorSeccion → colección agrupada por sección (todas, no solo pendientes)
    $campos                  → mapa de campos por sección (de config), para el nombre legible
--}}
@php
  $todas = collect($observacionesPorSeccion)->flatten();
  $total = $todas->count();
  $resueltas = $todas->filter(fn ($o) => in_array($o->estatus ?? 'pendiente', ['atendida', 'validada']))->count();
@endphp

@if($total)
  <aside class="obs-checklist">
    <div class="obs-checklist-head">
      <strong>Observaciones por atender</strong>
      <span class="obs-checklist-progreso">{{ $resueltas }}/{{ $total }}</span>
    </div>

    @foreach($observacionesPorSeccion as $seccion => $items)
      <div class="obs-checklist-grupo">
        <p class="obs-checklist-seccion">{{ $seccion }}</p>
        @foreach($items as $obs)
          @php $camposSeccion = $campos[$seccion] ?? []; @endphp
          <div class="obs-checklist-item">
            <span class="obs-check obs-check-{{ in_array($obs->estatus ?? 'pendiente', ['atendida','validada']) ? 'ok' : 'pendiente' }}"></span>
            <div class="obs-checklist-texto">
              <span class="obs-checklist-campo">
                {{ ($obs->campo && isset($camposSeccion[$obs->campo])) ? $camposSeccion[$obs->campo] : 'Toda la sección' }}
              </span>
              <span class="obs-badge obs-badge-{{ $obs->estatus ?? 'pendiente' }}">{{ $obs->estatusLegible() }}</span>
            </div>
          </div>
        @endforeach
      </div>
    @endforeach

    @if($resueltas === $total && $total > 0)
      <p class="obs-checklist-listo">Todas las observaciones están atendidas. Puede reenviar a revisión.</p>
    @endif
  </aside>
@endif
