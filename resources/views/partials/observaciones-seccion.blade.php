{{--
  Aviso de observaciones de una sección, dentro del formulario de edición.
  (Corrección #18 — el enlace ve qué corregir junto a la sección.)

  Espera:
    $seccion  → nombre de la sección (ej. 'Datos generales')
    $items    → colección de observaciones de esa sección (puede ser null)
    $campos   → mapa campo=>etiqueta de esa sección, para mostrar el nombre
                legible del campo observado (de config).
--}}
@php
  $items = $items ?? collect();
@endphp

@if($items->count())
  <div class="obs-aviso">
    <div class="obs-aviso-head">
      <strong>Observaciones de esta sección</strong>
      <span class="obs-aviso-conteo">{{ $items->count() }}</span>
    </div>

    @foreach($items as $obs)
      <div class="obs-aviso-item obs-estatus-{{ $obs->estatus ?? 'pendiente' }}">
        <div class="obs-aviso-item-top">
          @if($obs->campo && isset($campos[$obs->campo]))
            <span class="obs-campo">{{ $campos[$obs->campo] }}</span>
          @else
            <span class="obs-campo">Toda la sección</span>
          @endif
          <span class="obs-badge obs-badge-{{ $obs->estatus ?? 'pendiente' }}">
            {{ $obs->estatusLegible() }}
          </span>
        </div>

        <p class="obs-texto">{{ $obs->texto }}</p>
        <small class="obs-meta">— {{ $obs->realizadaPor->name ?? 'Revisor' }}, {{ $obs->created_at->format('d/m/Y') }}</small>

        @unless($obs->estaResuelta())
          {{-- El form real vive FUERA del formulario de edición (los <form>
               anidados son inválidos en HTML y el navegador los descarta, por
               eso el clic enviaba el form padre y la observación nunca se
               marcaba). Con el atributo form= el botón se asocia a su propio
               form, declarado al final de la vista, sin anidarlo. --}}
          <button type="submit" form="obs-atendida-{{ $obs->id }}"
            class="btn btn-outline btn-sm">Marcar como atendida</button>
        @endunless
      </div>
    @endforeach
  </div>
@endif
