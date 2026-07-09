{{--
  Checklist lateral de observaciones (corrección #18, punto 7).

  Panel derecho que reúne TODAS las observaciones del registro, agrupadas
  por sección, para que el enlace sepa de un vistazo qué falta atender.
  Muestra el estatus de cada una y el progreso general.

  Bug #38: agrega botón "Marcar como atendida" por observación, solo para
  el usuario destinatario de cada una. Usa form= para no anidar <form>
  dentro de <form>: los <form id="obs-atend-{id}"> invisibles se declaran
  al pie de la vista que incluye este partial (tramites/show, agenda/show).

  Espera:
    $observacionesPorSeccion → colección agrupada por sección (todas, no solo pendientes)
    $campos                  → mapa de campos por sección (de config), para el nombre legible
--}}
@php
  $todas = collect($observacionesPorSeccion)->flatten();
  $total = $todas->count();
  $resueltas = $todas->filter(fn ($o) => in_array($o->estatus ?? 'pendiente', ['atendida', 'validada']))->count();
  $usuarioId = auth()->id();
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
          @php
            $camposSeccion  = $campos[$seccion] ?? [];
            $resuelta       = in_array($obs->estatus ?? 'pendiente', ['atendida', 'validada']);
            $esDestinatario = $obs->destinatario_id && $obs->destinatario_id === $usuarioId;
          @endphp
          <div class="obs-checklist-item">
            <span class="obs-check obs-check-{{ $resuelta ? 'ok' : 'pendiente' }}"></span>
            <div class="obs-checklist-texto">
              <span class="obs-checklist-campo">
                {{ ($obs->campo && isset($camposSeccion[$obs->campo])) ? $camposSeccion[$obs->campo] : 'Toda la sección' }}
              </span>
              <span class="obs-badge obs-badge-{{ $obs->estatus ?? 'pendiente' }}">{{ $obs->estatusLegible() }}</span>

              {{-- Botón atender: visible solo para el destinatario de la observación
                   y solo si aún no está resuelta. Usa el atributo form= para
                   enlazarse al <form> invisible declarado al pie de la vista padre,
                   evitando así los problemas de <form> anidados. --}}
              @if($esDestinatario && !$resuelta)
                <button type="submit" form="obs-atend-{{ $obs->id }}"
                  class="btn btn-outline btn-sm" style="margin-top:6px">
                  Marcar como atendida
                </button>
              @endif
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
