{{--
  Render SOLO LECTURA de un nodo y sus hijos, para los capítulos de referencia
  (preview) en el editor. No lleva botones, formularios ni atributos de arrastre:
  es contexto, no edición. Recibe:
   - $nodo:          el RegulacionNodo a mostrar
   - $hijosPorPadre: colección agrupada por parent_id (armada una vez en la vista)
--}}
@php
  $hijos = $hijosPorPadre[$nodo->id] ?? collect();
  $derogado = $nodo->estaDerogado();
@endphp

<li class="lectura-nodo">
  <span class="{{ $derogado ? 'lectura-derogado' : '' }}" style="white-space:pre-line">
    <span class="lectura-tipo">{{ $nodo->etiquetaTipo() }}</span>
    @if($nodo->numero)
      <span class="lectura-numero">{{ $nodo->numero }}</span>
    @endif
    {{ $nodo->texto ?: '(sin texto)' }}
  </span>

  @if($hijos->isNotEmpty())
    <ul class="lectura-hijos">
      @foreach($hijos as $hijo)
        @include('screens.regulaciones.partials.nodo-lectura-editor', [
          'nodo' => $hijo,
          'hijosPorPadre' => $hijosPorPadre,
        ])
      @endforeach
    </ul>
  @endif
</li>